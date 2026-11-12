<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['open', 'closed'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tickets = SupportTicket::query()
            ->with(['customer:id,first_name,last_name,email,phone', 'latestMessage'])
            ->withCount([
                'messages as unread_count' => fn ($query) => $query
                    ->where('sender_role', 'customer')
                    ->where(function ($query) {
                        $query->whereNull('support_tickets.admin_last_read_message_id')
                            ->orWhereColumn(
                                'support_ticket_messages.id',
                                '>',
                                'support_tickets.admin_last_read_message_id'
                            );
                    }),
            ])
            ->when(
                $validated['status'] ?? null,
                fn ($query, string $status) => $query->where('status', $status)
            )
            ->orderByDesc('updated_at')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => collect($tickets->items())
                ->map(fn (SupportTicket $ticket) => $this->transformTicket($ticket))
                ->values(),
            'meta' => $this->paginationMeta($tickets),
        ]);
    }

    public function show(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $messages = $supportTicket->messages()
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 50);
        $supportTicket->forceFill([
            'admin_last_read_message_id' => max(
                (int) ($supportTicket->admin_last_read_message_id ?? 0),
                (int) ($messages->max('id') ?? 0)
            ),
        ])->save();
        $messages->setCollection(
            $messages->getCollection()->sortBy('id')->values()
        );

        return response()->json([
            'data' => [
                'ticket' => $this->transformTicket(
                    $supportTicket->load([
                        'customer:id,first_name,last_name,email,phone',
                        'latestMessage',
                    ])
                ),
                'messages' => $messages->getCollection()
                    ->map(fn (SupportTicketMessage $message) => $this->transformMessage($message))
                    ->values(),
                'meta' => $this->paginationMeta($messages),
            ],
        ]);
    }

    public function reply(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'client_message_id' => ['required', 'uuid'],
        ]);

        $existing = SupportTicketMessage::query()
            ->where('client_message_id', $validated['client_message_id'])
            ->first();

        if ($existing) {
            abort_unless(
                $existing->support_ticket_id === $supportTicket->id
                    && $existing->sender_role === 'admin',
                409,
                'This message submission has already been used.'
            );

            return response()->json([
                'data' => $this->transformMessage($existing),
            ]);
        }

        try {
            $message = DB::transaction(function () use ($request, $supportTicket, $validated) {
                $ticket = SupportTicket::query()
                    ->whereKey($supportTicket->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_if($ticket->status !== 'open', 409, 'Closed tickets cannot be replied to.');

                $duplicate = $ticket->messages()
                    ->where('client_message_id', $validated['client_message_id'])
                    ->first();

                if ($duplicate) {
                    return $duplicate;
                }

                $user = $request->user();
                $name = trim($user->first_name . ' ' . $user->last_name) ?: $user->email;

                $message = $ticket->messages()->create([
                    'sender_id' => $user->id,
                    'sender_role' => 'admin',
                    'sender_name' => $name,
                    'client_message_id' => $validated['client_message_id'],
                    'message' => $validated['message'],
                ]);

                $ticket->touch();

                return $message;
            });
        } catch (QueryException $exception) {
            $duplicate = SupportTicketMessage::query()
                ->where('client_message_id', $validated['client_message_id'])
                ->first();

            if (! $duplicate) {
                throw $exception;
            }

            abort_unless(
                $duplicate->support_ticket_id === $supportTicket->id
                    && $duplicate->sender_role === 'admin',
                409,
                'This message submission has already been used.'
            );

            return response()->json([
                'data' => $this->transformMessage($duplicate),
            ]);
        }

        return response()->json([
            'data' => $this->transformMessage($message),
        ], 201);
    }

    public function close(SupportTicket $supportTicket): JsonResponse
    {
        DB::transaction(function () use ($supportTicket) {
            $ticket = SupportTicket::query()
                ->whereKey($supportTicket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($ticket->status === 'open') {
                $ticket->forceFill([
                    'status' => 'closed',
                    'closed_at' => now(),
                ])->save();
            }
        });

        return response()->json([
            'message' => 'Support ticket closed.',
            'data' => $this->transformTicket(
                $supportTicket->fresh(['customer', 'latestMessage'])
            ),
        ]);
    }

    private function transformTicket(SupportTicket $ticket): array
    {
        $data = [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'locale' => $ticket->locale,
            'status' => $ticket->status,
            'created_at' => $ticket->created_at?->toISOString(),
            'updated_at' => $ticket->updated_at?->toISOString(),
            'closed_at' => $ticket->closed_at?->toISOString(),
            'last_message_at' => $ticket->latestMessage?->created_at?->toISOString(),
            'last_message' => $ticket->latestMessage?->message,
            'unread_count' => (int) ($ticket->unread_count ?? 0),
        ];

        if ($ticket->relationLoaded('customer') && $ticket->customer) {
            $data['customer'] = [
                'id' => $ticket->customer->id,
                'name' => trim($ticket->customer->first_name . ' ' . $ticket->customer->last_name),
                'email' => $ticket->customer->email,
                'phone' => $ticket->customer->phone,
            ];
        }

        return $data;
    }

    private function transformMessage(SupportTicketMessage $message): array
    {
        return [
            'id' => $message->id,
            'sender_role' => $message->sender_role,
            'sender_name' => $message->sender_name,
            'message' => $message->message,
            'created_at' => $message->created_at?->toISOString(),
        ];
    }

    private function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
