<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ShoppingAssistantUnavailable;
use App\Exceptions\ShoppingAssistantBudgetExceeded;
use App\Exceptions\ShoppingAssistantDailyQuotaExceeded;
use App\Http\Controllers\Controller;
use App\Services\ShoppingAssistant\ShoppingAssistantService;
use App\Services\ShoppingAssistant\ShoppingAssistantBudgetGuard;
use App\Services\ShoppingAssistant\ShoppingAssistantUsageLogger;
use App\Services\ShoppingAssistant\ShoppingAssistantLanguage;
use App\Services\ShoppingAssistant\ShoppingAssistantConsultationMemory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ShoppingAssistantController extends Controller
{
    public function __invoke(
        Request $request,
        ShoppingAssistantService $assistant,
        ShoppingAssistantUsageLogger $usageLogger,
        ShoppingAssistantBudgetGuard $budgetGuard
    ): JsonResponse {
        abort_unless(
            config('shopping_assistant.enabled')
                && (
                    app()->environment(['local', 'testing'])
                    || config('shopping_assistant.driver') !== 'mock'
                ),
            404
        );

        $validated = $request->validate([
            'message' => [
                'required',
                'string',
                'min:1',
                'max:' . max(1, (int) config('shopping_assistant.max_message_characters', 1200)),
            ],
            'locale' => ['sometimes', 'string', 'in:fa,en'],
            'conversation_id' => ['sometimes', 'uuid'],
            'session_token' => ['sometimes', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ]);

        if (
            array_key_exists('conversation_id', $validated)
            !== array_key_exists('session_token', $validated)
        ) {
            throw ValidationException::withMessages([
                'conversation_id' => ['conversation_id and session_token must be provided together.'],
            ]);
        }

        $requestedLocale = $validated['locale']
            ?? (string) $request->attributes->get('locale', app()->getLocale());
        $locale = ShoppingAssistantLanguage::detect($validated['message'], $requestedLocale);
        app()->setLocale($locale);

        $isNewConversation = ! array_key_exists('conversation_id', $validated);
        $conversationId = $isNewConversation
            ? (string) Str::uuid()
            : strtolower($validated['conversation_id']);
        $sessionToken = $isNewConversation
            ? bin2hex(random_bytes(32))
            : $validated['session_token'];
        $cacheKey = 'shopping-assistant:session:' . $conversationId;
        $ttlMinutes = min(60, max(1, (int) config('shopping_assistant.session_ttl_minutes', 60)));

        $state = $isNewConversation
            ? ['token_hash' => hash('sha256', $sessionToken), 'messages' => [], 'turns' => 0]
            : Cache::get($cacheKey);

        if (! is_array($state) || ! hash_equals(
            (string) ($state['token_hash'] ?? ''),
            hash('sha256', $sessionToken)
        )) {
            return response()->json([
                'message' => 'This assistant conversation is unavailable. Start a new conversation.',
            ], 404);
        }

        $history = is_array($state['messages'] ?? null) ? $state['messages'] : [];
        $previousProductIds = is_array($state['product_ids'] ?? null)
            ? array_values(array_filter(
                array_map('intval', $state['product_ids']),
                static fn (int $id): bool => $id > 0
            ))
            : [];
        $consultation = ShoppingAssistantConsultationMemory::observe(
            is_array($state['consultation'] ?? null) ? $state['consultation'] : [],
            $validated['message'],
            $locale
        );
        $authenticatedUser = $request->user('sanctum');
        $quotaSubject = $authenticatedUser !== null
            ? 'user:' . $authenticatedUser->getAuthIdentifier()
            : 'ip:' . (string) $request->ip();

        try {
            $result = $assistant->respond(
                $validated['message'],
                $locale,
                $history,
                $conversationId,
                $previousProductIds,
                $quotaSubject,
                $consultation
            );
        } catch (ShoppingAssistantDailyQuotaExceeded $exception) {
            return response()->json(['message' => $exception->getMessage()], 429);
        } catch (ShoppingAssistantBudgetExceeded) {
            return response()->json([
                'message' => 'The shopping assistant has reached its current spending limit.',
            ], 429);
        } catch (ShoppingAssistantUnavailable $exception) {
            try {
                $usageLogger->record(
                    $conversationId,
                    'failed',
                    budgetReservationId: $exception->reservationId(),
                    diagnostics: $exception->diagnostics()
                );
            } catch (Throwable $loggingException) {
                Log::error('Shopping assistant failure diagnostics could not be persisted.', [
                    ...$exception->diagnostics(),
                    'budget_reservation_id' => $exception->reservationId(),
                    'logging_exception_type' => $loggingException::class,
                ]);
            }

            return response()->json([
                'message' => 'The shopping assistant is temporarily unavailable. Please try again later.',
            ], 503);
        }

        $inputTokens = max(0, (int) ($result['input_tokens'] ?? 0));
        $outputTokens = max(0, (int) ($result['output_tokens'] ?? 0));
        $reservationId = $result['budget_reservation_id'] ?? null;
        try {
            $estimatedCost = $usageLogger->record(
                $conversationId,
                $result['billing_status'] ?? 'success',
                $inputTokens,
                $outputTokens,
                $reservationId,
                $result['_provider_diagnostics'] ?? []
            );
        } catch (Throwable $exception) {
            $diagnostics = [
                ...($result['_provider_diagnostics'] ?? []),
                'failure_stage' => 'usage_settlement',
                'failure_category' => 'usage_settlement_failed',
                'dispatch_state' => 'response_received',
            ];
            $failure = new ShoppingAssistantUnavailable(
                'Assistant usage settlement failed.',
                previous: $exception,
                diagnostics: $diagnostics,
                reservationId: $reservationId
            );

            if ($reservationId !== null) {
                try {
                    $budgetGuard->markUnknown($reservationId);
                } catch (Throwable $reconciliationException) {
                    Log::error('Shopping assistant reservation reconciliation failed.', [
                        ...$failure->diagnostics(),
                        'budget_reservation_id' => $reservationId,
                        'reconciliation_exception_type' => $reconciliationException::class,
                    ]);
                }
            }

            try {
                $usageLogger->record(
                    $conversationId,
                    'failed',
                    budgetReservationId: $reservationId,
                    diagnostics: $failure->diagnostics()
                );
            } catch (Throwable $loggingException) {
                Log::error('Shopping assistant settlement failure could not be persisted.', [
                    ...$failure->diagnostics(),
                    'budget_reservation_id' => $reservationId,
                    'logging_exception_type' => $loggingException::class,
                ]);
            }

            return response()->json([
                'message' => 'The shopping assistant is temporarily unavailable. Please try again later.',
            ], 503);
        }
        $createdAt = now()->toIso8601String();
        $userMessage = [
            'id' => (string) Str::uuid(),
            'role' => 'user',
            'content' => $validated['message'],
            'locale' => $locale,
            'created_at' => $createdAt,
        ];
        $assistantMessage = [
            'id' => (string) Str::uuid(),
            'role' => 'assistant',
            'content' => $result['content'],
            'locale' => $locale,
            'created_at' => now()->toIso8601String(),
        ];
        $messages = [
            ...$history,
            ['role' => 'user', 'content' => $validated['message'], 'locale' => $locale],
            ['role' => 'assistant', 'content' => $result['content'], 'locale' => $locale],
        ];
        $contextLimit = max(2, (int) config('shopping_assistant.max_context_messages', 8));
        $messages = array_slice($messages, -$contextLimit);
        $productIds = is_array($result['remembered_product_ids'] ?? null)
            ? array_values(array_unique(array_filter(
                array_map('intval', $result['remembered_product_ids']),
                static fn (int $id): bool => $id > 0
            )))
            : array_values(array_unique(array_filter(
                array_map(
                    'intval',
                    array_column($result['product_cards'] ?? [], 'id')
                ),
                static fn (int $id): bool => $id > 0
            )));
        $rememberedProductIds = array_values(array_unique([
            ...$productIds,
            ...$previousProductIds,
        ]));
        $state = [
            'token_hash' => hash('sha256', $sessionToken),
            'messages' => $messages,
            'turns' => (int) ($state['turns'] ?? 0) + 1,
            'product_ids' => array_slice($rememberedProductIds, 0, 5),
            'consultation' => ShoppingAssistantConsultationMemory::commit(
                $consultation, (string) $result['content'], $result
            ),
        ];
        Cache::put($cacheKey, $state, now()->addMinutes($ttlMinutes));

        return response()->json([
            'data' => [
                'conversation' => [
                    'id' => $conversationId,
                    'session_token' => $sessionToken,
                    'remaining_messages' => null,
                    'expires_in_minutes' => $ttlMinutes,
                ],
                'messages' => [$userMessage, $assistantMessage],
                'product_cards' => $result['product_cards'],
                'contact_links' => $result['contact_links'] ?? [],
                'contact_locations' => $result['contact_locations'] ?? [],
                'locale' => $locale,
                'catalog_data' => $result['catalog_data'],
                'tool' => $result['tool'],
                'usage' => [
                    'provider' => (string) config('shopping_assistant.driver', 'mock'),
                    'model' => (string) config('shopping_assistant.model', 'gpt-6.1-terra'),
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'estimated_cost_usd' => $estimatedCost,
                ],
            ],
        ]);
    }
}
