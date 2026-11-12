<?php

namespace App\Services\ShoppingAssistant;

use App\Exceptions\ShoppingAssistantBudgetExceeded;
use App\Exceptions\ShoppingAssistantUnavailable;
use Illuminate\Support\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ShoppingAssistantBudgetGuard
{
    public function __construct(
        private readonly ShoppingAssistantCostCalculator $costCalculator
    ) {
    }

    public function estimateInputTokens(
        string $message,
        array $history,
        array $catalogContext
    ): int {
        $serialized = json_encode(
            [$history, $message, $catalogContext],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if (! is_string($serialized)) {
            throw new ShoppingAssistantUnavailable(
                'Request token estimation is unavailable.',
                diagnostics: [
                    'failure_stage' => 'request_estimation',
                    'failure_category' => 'request_estimation_failed',
                    'dispatch_state' => 'not_dispatched',
                ]
            );
        }

        // UTF-8 bytes conservatively bound content tokens; the fixed allowance covers instructions and framing.
        return strlen($serialized) + 4096 + (count($history) + 2) * 32;
    }

    public function reserve(
        string $conversationId,
        int $inputTokenUpperBound,
        int $maxOutputTokens
    ): ?int {
        if ((string) config('shopping_assistant.driver') === 'mock') {
            return null;
        }

        $localTestLimit = app()->environment('local')
            ? $this->costCalculator->normalizeLimit(config('shopping_assistant.local_test_budget_usd'))
            : null;
        $budgetScope = $localTestLimit === null ? 'standard' : 'local_test';
        $dailyLimit = $localTestLimit ?? $this->costCalculator->normalizeLimit(
            config('shopping_assistant.daily_budget_usd')
        );
        $monthlyLimit = $localTestLimit === null
            ? $this->costCalculator->normalizeLimit(config('shopping_assistant.monthly_budget_usd'))
            : null;

        if ($dailyLimit === null && $monthlyLimit === null) {
            return null;
        }

        if ($inputTokenUpperBound < 1 || $maxOutputTokens < 1) {
            throw new ShoppingAssistantUnavailable(
                'Request token estimation is unavailable.',
                diagnostics: [
                    'failure_stage' => 'request_estimation',
                    'failure_category' => 'request_estimation_failed',
                    'dispatch_state' => 'not_dispatched',
                ]
            );
        }

        try {
            $reservationCost = $this->costCalculator->calculate(
                $inputTokenUpperBound,
                $maxOutputTokens
            );
        } catch (ShoppingAssistantUnavailable $exception) {
            throw new ShoppingAssistantUnavailable(
                $exception->getMessage(),
                previous: $exception,
                diagnostics: [
                    'failure_stage' => 'budget_pricing',
                    'failure_category' => 'budget_pricing_unavailable',
                    'dispatch_state' => 'not_dispatched',
                ]
            );
        }
        $now = now();

        try {
            return DB::transaction(function () use (
                $conversationId,
                $dailyLimit,
                $monthlyLimit,
                $reservationCost,
                $budgetScope,
                $now
            ): int {
                if (! DB::table('shopping_assistant_budget_locks')->where('id', 1)->lockForUpdate()->first()) {
                    throw new ShoppingAssistantUnavailable(
                        'Budget reservation state is unavailable.',
                        diagnostics: [
                            'failure_stage' => 'budget_reservation',
                            'failure_category' => 'budget_lock_unavailable',
                            'dispatch_state' => 'not_dispatched',
                        ]
                    );
                }

                $today = $now->toDateString();
                $month = $now->format('Y-m');

                foreach ([
                    ['limit' => $dailyLimit, 'column' => 'budget_date', 'period' => $today],
                    ['limit' => $monthlyLimit, 'column' => 'budget_month', 'period' => $month],
                ] as $budget) {
                    if ($budget['limit'] === null) {
                        continue;
                    }

                    $isolatedLocalTest = $budgetScope === 'local_test';
                    if (! $isolatedLocalTest) {
                        $this->assertKnownLegacySpend($budget['column'], $budget['period']);
                    }

                    $spent = $isolatedLocalTest
                        ? '0'
                        : $this->successfulUsageSpend($budget['column'], $budget['period']);
                    $reserved = $this->reservationSpend(
                        $budget['column'],
                        $budget['period'],
                        $budgetScope
                    );
                    $committed = bcadd(bcadd($spent, $reserved, 12), $reservationCost, 12);

                    if (bccomp($committed, $budget['limit'], 12) > 0) {
                        throw new ShoppingAssistantBudgetExceeded('Shopping assistant budget is exhausted.');
                    }
                }

                return (int) DB::table('shopping_assistant_budget_reservations')->insertGetId([
                    'conversation_fingerprint' => hash('sha256', $conversationId),
                    'budget_date' => $today,
                    'budget_month' => $month,
                    'budget_scope' => $budgetScope,
                    'reserved_cost_usd' => $reservationCost,
                    'actual_cost_usd' => null,
                    'status' => 'reserved',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }, 3);
        } catch (ShoppingAssistantBudgetExceeded|ShoppingAssistantUnavailable $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            throw new ShoppingAssistantUnavailable(
                'Budget reservation state is unavailable.',
                previous: $exception,
                diagnostics: [
                    'failure_stage' => 'budget_reservation',
                    'failure_category' => 'budget_database_error',
                    'dispatch_state' => 'not_dispatched',
                ]
            );
        }
    }

    public function releaseBeforeDispatch(int $reservationId): void
    {
        try {
            DB::transaction(function () use ($reservationId): void {
                if (! DB::table('shopping_assistant_budget_locks')->where('id', 1)->lockForUpdate()->first()) {
                    throw new ShoppingAssistantUnavailable(
                        'Budget reservation state is unavailable.',
                        diagnostics: [
                            'failure_stage' => 'budget_reconciliation',
                            'failure_category' => 'budget_lock_unavailable',
                        ]
                    );
                }

                $updated = DB::table('shopping_assistant_budget_reservations')
                    ->where('id', $reservationId)
                    ->where('status', 'reserved')
                    ->update(['status' => 'released', 'updated_at' => now()]);

                if ($updated !== 1) {
                    throw new ShoppingAssistantUnavailable(
                        'Budget reservation state is unavailable.',
                        diagnostics: [
                            'failure_stage' => 'budget_reconciliation',
                            'failure_category' => 'reservation_state_conflict',
                        ]
                    );
                }
            }, 3);
        } catch (QueryException $exception) {
            throw new ShoppingAssistantUnavailable(
                'Budget reservation state is unavailable.',
                previous: $exception,
                diagnostics: [
                    'failure_stage' => 'budget_reconciliation',
                    'failure_category' => 'budget_database_error',
                ]
            );
        }
    }

    public function markUnknown(int $reservationId): void
    {
        try {
            DB::transaction(function () use ($reservationId): void {
                if (! DB::table('shopping_assistant_budget_locks')->where('id', 1)->lockForUpdate()->first()) {
                    throw new ShoppingAssistantUnavailable(
                        'Budget reservation state is unavailable.',
                        diagnostics: [
                            'failure_stage' => 'budget_reconciliation',
                            'failure_category' => 'budget_lock_unavailable',
                        ]
                    );
                }

                $updated = DB::table('shopping_assistant_budget_reservations')
                    ->where('id', $reservationId)
                    ->where('status', 'reserved')
                    ->update(['status' => 'unknown', 'updated_at' => now()]);

                if ($updated !== 1) {
                    throw new ShoppingAssistantUnavailable(
                        'Budget reservation state is unavailable.',
                        diagnostics: [
                            'failure_stage' => 'budget_reconciliation',
                            'failure_category' => 'reservation_state_conflict',
                        ]
                    );
                }
            }, 3);
        } catch (QueryException $exception) {
            throw new ShoppingAssistantUnavailable(
                'Budget reservation state is unavailable.',
                previous: $exception,
                diagnostics: [
                    'failure_stage' => 'budget_reconciliation',
                    'failure_category' => 'budget_database_error',
                ]
            );
        }
    }

    private function assertKnownLegacySpend(string $periodColumn, string $period): void
    {
        $query = $this->usagePeriodQuery($periodColumn, $period);

        $unknownSuccessfulUsage = (clone $query)
            ->where('status', 'success')
            ->whereNull('estimated_cost_usd');
        $unknownFailedUsage = (clone $query)
            ->where('status', 'failed')
            ->whereNull('estimated_cost_usd')
            ->where(static function ($query): void {
                $query->whereNull('dispatch_state')
                    ->orWhere('dispatch_state', '!=', 'not_dispatched');
            });

        if ($unknownSuccessfulUsage->exists() || $unknownFailedUsage->exists()) {
            throw new ShoppingAssistantUnavailable(
                'Existing provider spend is unknown.',
                diagnostics: [
                    'failure_stage' => 'budget_accounting',
                    'failure_category' => 'legacy_spend_unknown',
                    'dispatch_state' => 'not_dispatched',
                ]
            );
        }
    }

    private function successfulUsageSpend(string $periodColumn, string $period): string
    {
        $query = $this->usagePeriodQuery($periodColumn, $period);

        $costs = $query
            ->where('status', 'success')
            ->whereNotNull('estimated_cost_usd')
            ->pluck('estimated_cost_usd');

        return $this->sumCosts($costs->all());
    }

    private function reservationSpend(string $periodColumn, string $period, string $budgetScope): string
    {
        $query = DB::table('shopping_assistant_budget_reservations')
            ->whereIn('status', ['reserved', 'unknown', 'settled']);
        $query->where('budget_scope', $budgetScope);

        if ($periodColumn === 'budget_date') {
            $query->where('budget_date', $period);
        } else {
            $query->where('budget_month', $period);
        }

        return $this->sumCosts($query->get(['status', 'reserved_cost_usd', 'actual_cost_usd'])
            ->map(static fn (object $reservation): mixed => $reservation->status === 'settled'
                ? $reservation->actual_cost_usd
                : $reservation->reserved_cost_usd)
            ->all());
    }

    /**
     * @param array<int, mixed> $costs
     */
    private function sumCosts(array $costs): string
    {
        $total = '0.000000000000';

        foreach ($costs as $cost) {
            $amount = trim((string) $cost);

            if (! preg_match('/^\d+(?:\.\d{1,12})?$/', $amount)) {
                throw new ShoppingAssistantUnavailable(
                    'Existing provider spend is unknown.',
                    diagnostics: [
                        'failure_stage' => 'budget_accounting',
                        'failure_category' => 'stored_cost_invalid',
                        'dispatch_state' => 'not_dispatched',
                    ]
                );
            }

            $total = bcadd($total, $amount, 12);
        }

        return $total;
    }

    private function usagePeriodQuery(string $periodColumn, string $period): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('shopping_assistant_usage_logs')
            ->where('provider', 'gapgpt')
            ->whereNull('budget_reservation_id');

        if ($periodColumn === 'budget_date') {
            return $query->whereDate('created_at', $period);
        }

        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();

        return $query
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $start->copy()->addMonth());
    }
}
