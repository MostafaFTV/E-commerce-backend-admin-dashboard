<?php

namespace App\Services\ShoppingAssistant;

use Illuminate\Support\Facades\DB;

class ShoppingAssistantUsageLogger
{
    public function record(
        string $conversationId,
        string $status,
        int $inputTokens = 0,
        int $outputTokens = 0,
        ?int $budgetReservationId = null,
        array $diagnostics = []
    ): ?float {
        $cost = null;
        if ((string) config('shopping_assistant.driver') === 'mock') {
            $cost = '0.000000000000';
        } elseif ($status === 'success') {
            if ($inputTokens < 1 || $outputTokens < 1) {
                throw new \App\Exceptions\ShoppingAssistantUnavailable(
                    'Provider token usage is unavailable.',
                    diagnostics: [
                        'failure_stage' => 'usage_accounting',
                        'failure_category' => 'provider_usage_unavailable',
                        'dispatch_state' => 'response_received',
                    ]
                );
            }

            try {
                $cost = app(ShoppingAssistantCostCalculator::class)->calculate(
                    $inputTokens,
                    $outputTokens
                );
            } catch (\App\Exceptions\ShoppingAssistantUnavailable) {
                $cost = null;
            }
        }

        if ($budgetReservationId !== null && $status === 'success' && $cost === null) {
            throw new \App\Exceptions\ShoppingAssistantUnavailable('Budget settlement is unavailable.');
        }

        return DB::transaction(function () use (
            $conversationId,
            $status,
            $inputTokens,
            $outputTokens,
            $budgetReservationId,
            $cost,
            $diagnostics
        ): ?float {
            if ($budgetReservationId !== null && $status === 'success') {
                if (! DB::table('shopping_assistant_budget_locks')->where('id', 1)->lockForUpdate()->first()) {
                    throw new \App\Exceptions\ShoppingAssistantUnavailable('Budget settlement is unavailable.');
                }

                $updated = DB::table('shopping_assistant_budget_reservations')
                    ->where('id', $budgetReservationId)
                    ->where('status', 'reserved')
                    ->update([
                        'status' => 'settled',
                        'actual_cost_usd' => $cost,
                        'updated_at' => now(),
                    ]);

                if ($updated !== 1) {
                    throw new \App\Exceptions\ShoppingAssistantUnavailable('Budget settlement is unavailable.');
                }
            }

            DB::table('shopping_assistant_usage_logs')->insert([
                'conversation_fingerprint' => hash('sha256', $conversationId),
                'provider' => (string) config('shopping_assistant.driver', 'mock'),
                'model' => (string) config('shopping_assistant.model', 'gpt-6.1-terra'),
                'input_tokens' => max(0, $inputTokens),
                'output_tokens' => max(0, $outputTokens),
                'estimated_cost_usd' => $cost,
                'status' => $status,
                'budget_reservation_id' => $budgetReservationId,
                'failure_stage' => $diagnostics['failure_stage'] ?? null,
                'failure_category' => $diagnostics['failure_category'] ?? null,
                'dispatch_state' => $diagnostics['dispatch_state'] ?? null,
                'provider_http_status' => $diagnostics['provider_http_status'] ?? null,
                'elapsed_ms' => $diagnostics['elapsed_ms'] ?? null,
                'provider_request_id' => $this->safeRequestId($diagnostics['provider_request_id'] ?? null),
                'created_at' => now(),
            ]);

            return $cost === null ? null : (float) $cost;
        }, 3);
    }

    private function safeRequestId(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $requestId = trim($value);

        return preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $requestId) === 1
            ? $requestId
            : null;
    }
}
