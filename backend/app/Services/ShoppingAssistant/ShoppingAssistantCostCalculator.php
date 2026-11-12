<?php

namespace App\Services\ShoppingAssistant;

use App\Exceptions\ShoppingAssistantUnavailable;

class ShoppingAssistantCostCalculator
{
    public function calculate(int $inputTokens, int $outputTokens): string
    {
        if (! function_exists('bcmul') || $inputTokens < 1 || $outputTokens < 1) {
            throw new ShoppingAssistantUnavailable(
                'Token pricing is unavailable.',
                diagnostics: [
                    'failure_stage' => 'budget_pricing',
                    'failure_category' => 'pricing_unavailable',
                    'dispatch_state' => 'not_dispatched',
                ]
            );
        }

        $inputRate = $this->rate('input_cost_per_million_tokens');
        $outputRate = $this->rate('output_cost_per_million_tokens');

        return bcadd(
            $this->tokenCost($inputTokens, $inputRate),
            $this->tokenCost($outputTokens, $outputRate),
            12
        );
    }

    public function normalizeLimit(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (! function_exists('bccomp')) {
            throw new ShoppingAssistantUnavailable(
                'Decimal budget enforcement is unavailable.',
                diagnostics: [
                    'failure_stage' => 'budget_configuration',
                    'failure_category' => 'decimal_arithmetic_unavailable',
                    'dispatch_state' => 'not_dispatched',
                ]
            );
        }

        $limit = trim((string) $value);
        if (! preg_match('/^\d+(?:\.\d{1,12})?$/', $limit) || bccomp($limit, '0', 12) <= 0) {
            throw new ShoppingAssistantUnavailable(
                'Budget configuration is invalid.',
                diagnostics: [
                    'failure_stage' => 'budget_configuration',
                    'failure_category' => 'invalid_budget_limit',
                    'dispatch_state' => 'not_dispatched',
                ]
            );
        }

        return bcadd($limit, '0', 12);
    }

    private function rate(string $key): string
    {
        $rate = trim((string) config('shopping_assistant.' . $key));

        if (
            ! preg_match('/^\d+(?:\.\d{1,12})?$/', $rate)
            || bccomp($rate, '0', 12) <= 0
        ) {
            throw new ShoppingAssistantUnavailable(
                'Token pricing is unavailable.',
                diagnostics: [
                    'failure_stage' => 'budget_pricing',
                    'failure_category' => 'pricing_unavailable',
                    'dispatch_state' => 'not_dispatched',
                ]
            );
        }

        return bcadd($rate, '0', 12);
    }

    private function tokenCost(int $tokens, string $rate): string
    {
        $tokenRateProduct = bcmul((string) $tokens, $rate, 18);
        $cost = bcdiv($tokenRateProduct, '1000000', 12);

        if (bccomp(bcmul($cost, '1000000', 18), $tokenRateProduct, 18) < 0) {
            $cost = bcadd($cost, '0.000000000001', 12);
        }

        return $cost;
    }
}
