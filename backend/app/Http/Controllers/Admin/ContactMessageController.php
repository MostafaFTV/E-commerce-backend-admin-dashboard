<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactMessageController extends Controller
{
    private const STATUSES = [
        'new',
        'read',
        'resolved',
    ];

    public function index(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],

            'status' => [
                'nullable',
                'string',
                Rule::in(
                    self::STATUSES
                ),
            ],

            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $messages = ContactMessage::query()
            ->when(
                $validated['status'] ?? null,
                function (
                    $query,
                    string $status
                ) {
                    $query->where(
                        'status',
                        $status
                    );
                }
            )

            ->when(
                $validated['search'] ?? null,
                function (
                    $query,
                    string $search
                ) {
                    $query->where(
                        function ($searchQuery) use ($search) {
                            $searchQuery
                                ->where(
                                    'full_name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'contact',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'subject',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'message',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )

            ->latest('id')

            ->paginate(
                $validated['per_page'] ?? 25
            );

        return response()->json([
            'data' =>
                collect(
                    $messages->items()
                )
                    ->map(
                        fn (
                            ContactMessage $message
                        ) =>
                            $this->transformMessage(
                                $message
                            )
                    )
                    ->values(),

            'meta' => [
                'current_page' =>
                    $messages->currentPage(),

                'last_page' =>
                    $messages->lastPage(),

                'per_page' =>
                    $messages->perPage(),

                'total' =>
                    $messages->total(),
            ],
        ]);
    }

    public function show(
        ContactMessage $contactMessage
    ): JsonResponse {
        return response()->json([
            'data' =>
                $this->transformMessage(
                    $contactMessage
                ),
        ]);
    }

    public function updateStatus(
        Request $request,
        ContactMessage $contactMessage
    ): JsonResponse {
        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in(
                    self::STATUSES
                ),
            ],
        ]);

        $contactMessage->update([
            'status' =>
                $validated['status'],
        ]);

        return response()->json([
            'message' =>
                'Contact message status updated successfully.',

            'data' =>
                $this->transformMessage(
                    $contactMessage
                ),
        ]);
    }

    private function transformMessage(
        ContactMessage $message
    ): array {
        return [
            'id' =>
                $message->id,

            'full_name' =>
                $message->full_name,

            'contact' =>
                $message->contact,

            'subject' =>
                $message->subject,

            'message' =>
                $message->message,

            'locale' =>
                $message->locale,

            'status' =>
                $message->status,

            'created_at' =>
                $message->created_at
                    ?->toISOString(),

            'updated_at' =>
                $message->updated_at
                    ?->toISOString(),
        ];
    }
}