<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    private const SUBJECTS = [
        'product',
        'order',
        'delivery',
        'payment',
        'returns',
        'other',
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $tickets = SupportTicket::query()
            ->where('customer_id', $request->user()->id)
            ->with('latestMessage')
            ->withCount([
                'messages as unread_count' => fn ($query) => $query
                    ->where('sender_role', 'admin')
                    ->where(function ($query) {
                        $query->whereNull('support_tickets.customer_last_read_message_id')
                            ->orWhereColumn(
                                'support_ticket_messages.id',
                                '>',
                                'support_tickets.customer_last_read_message_id'
                            );
                    }),
            ])
            ->orderByDesc('updated_at')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'data' => collect($tickets->items())
                ->map(fn (SupportTicket $ticket) => $this->transformTicket($ticket))
                ->values(),
            'meta' => $this->paginationMeta($tickets),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', Rule::in(self::SUBJECTS)],
            'message' => ['required', 'string', 'max:5000'],
            'locale' => ['required', 'string', Rule::in(['fa', 'en'])],
            'client_message_id' => ['required', 'uuid'],
        ]);

        $existing = SupportTicketMessage::query()
            ->where('client_message_id', $validated['client_message_id'])
            ->with('ticket')
            ->first();

        if ($existing) {
            abort_unless(
                $existing->ticket->customer_id === $request->user()->id
                    && $existing->sender_role === 'customer',
                409,
                'This message submission has already been used.'
            );

            return response()->json([
                'data' => [
                    'ticket' => $this->transformTicket($existing->ticket->load('latestMessage')),
                    'messages' => [$this->transformMessage($existing)],
                ],
            ]);
        }

        try {
            $ticket = DB::transaction(function () use ($request, $validated) {
                $ticket = SupportTicket::create([
                    'customer_id' => $request->user()->id,
                    'subject' => $validated['subject'],
                    'locale' => $validated['locale'],
                    'status' => 'open',
                ]);

                $message = $ticket->messages()->create([
                    'sender_id' => $request->user()->id,
                    'sender_role' => 'customer',
                    'sender_name' => $this->customerName($request->user()),
                    'client_message_id' => $validated['client_message_id'],
                    'message' => $validated['message'],
                ]);

                $ticket->forceFill([
                    'customer_last_read_message_id' => $message->id,
                ])->save();

                return $ticket->load('latestMessage');
            });
        } catch (QueryException $exception) {
            $duplicate = SupportTicketMessage::query()
                ->where('client_message_id', $validated['client_message_id'])
                ->with('ticket')
                ->first();

            if (! $duplicate) {
                throw $exception;
            }

            abort_unless(
                $duplicate->ticket->customer_id === $request->user()->id
                    && $duplicate->sender_role === 'customer',
                409,
                'This message submission has already been used.'
            );

            return response()->json([
                'data' => [
                    'ticket' => $this->transformTicket($duplicate->ticket->load('latestMessage')),
                    'messages' => [$this->transformMessage($duplicate)],
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'ticket' => $this->transformTicket($ticket),
                'messages' => [$this->transformMessage($ticket->latestMessage)],
            ],
        ], 201);
    }

    public function show(Request $request, int $ticketId): JsonResponse
    {
        $ticket = $this->ownedTicket($request, $ticketId);
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $messages = $ticket->messages()
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 50);
        $ticket->forceFill([
            'customer_last_read_message_id' => max(
                (int) ($ticket->customer_last_read_message_id ?? 0),
                (int) ($messages->max('id') ?? 0)
            ),
        ])->save();
        $messages->setCollection(
            $messages->getCollection()->sortBy('id')->values()
        );

        return response()->json([
            'data' => [
                'ticket' => $this->transformTicket($ticket->load('latestMessage')),
                'messages' => $messages->getCollection()
                    ->map(fn (SupportTicketMessage $message) => $this->transformMessage($message))
                    ->values(),
                'meta' => $this->paginationMeta($messages),
            ],
        ]);
    }

    public function reply(Request $request, int $ticketId): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'client_message_id' => ['required', 'uuid'],
        ]);

        $ticket = $this->ownedTicket($request, $ticketId);
        $existing = SupportTicketMessage::query()
            ->where('client_message_id', $validated['client_message_id'])
            ->first();

        if ($existing) {
            abort_unless(
                $existing->support_ticket_id === $ticket->id
                    && $existing->sender_role === 'customer',
                409,
                'This message submission has already been used.'
            );

            return response()->json([
                'data' => $this->transformMessage($existing),
            ]);
        }

        try {
            $message = DB::transaction(function () use ($request, $ticketId, $validated) {
                $ticket = SupportTicket::query()
                    ->whereKey($ticketId)
                    ->where('customer_id', $request->user()->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_if($ticket->status !== 'open', 409, 'Closed tickets cannot be replied to.');

                $duplicate = $ticket->messages()
                    ->where('client_message_id', $validated['client_message_id'])
                    ->first();

                if ($duplicate) {
                    return $duplicate;
                }

                $message = $ticket->messages()->create([
                    'sender_id' => $request->user()->id,
                    'sender_role' => 'customer',
                    'sender_name' => $this->customerName($request->user()),
                    'client_message_id' => $validated['client_message_id'],
                    'message' => $validated['message'],
                ]);

                $ticket->forceFill([
                    'customer_last_read_message_id' => $message->id,
                ])->touch();

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
                $duplicate->support_ticket_id === $ticket->id
                    && $duplicate->sender_role === 'customer',
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

    private function ownedTicket(Request $request, int $ticketId): SupportTicket
    {
        return SupportTicket::query()
            ->where('customer_id', $request->user()->id)
            ->findOrFail($ticketId);
    }

    private function customerName(object $user): string
    {
        return trim($user->first_name . ' ' . $user->last_name) ?: $user->email;
    }

    private function transformTicket(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'locale' => $ticket->locale,
            'status' => $ticket->status,
            'created_at' => $ticket->created_at?->toISOString(),
            'updated_at' => $ticket->updated_at?->toISOString(),
            'last_message_at' => $ticket->latestMessage?->created_at?->toISOString(),
            'last_message' => $ticket->latestMessage?->message,
            'unread_count' => (int) ($ticket->unread_count ?? 0),
        ];
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
