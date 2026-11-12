<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CashbackRule;
use App\Models\CashbackRuleOrderItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use LogicException;

class CashbackService
{
    public function __construct(
        private readonly CashbackRuleService $rules
    ) {}

    /**
     * Create immutable cashback snapshots for an order.
     *
     * Call only after order items are persisted.
     * This method does not issue wallet credit.
     */
    public function snapshotOrder(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $items = $lockedOrder->items()
                ->orderBy('id')
                ->get();

            if ($items->isEmpty()) {
                return;
            }

            $existingCount = CashbackRuleOrderItem::query()
                ->whereIn('order_item_id', $items->pluck('id'))
                ->count();

            if ($lockedOrder->cashback_snapshot_completed_at !== null) {
                return;
            }

            if ($existingCount > 0) {
                throw new LogicException(
                    'Partial cashback snapshots detected for order.'
                );
            }

            $subtotal = $this->toMinorUnits(
                (string) $lockedOrder->subtotal
            );

            $discount = $this->toMinorUnits(
                (string) $lockedOrder->discount
            );

            $walletUsed = $this->toMinorUnits(
                (string) ($lockedOrder->wallet_credit_used ?? '0')
            );

            if (
                $discount < 0 ||
                $walletUsed < 0 ||
                $discount > $subtotal ||
                $walletUsed > ($subtotal - $discount)
            ) {
                throw new LogicException(
                    'Invalid cashback financial amounts.'
                );
            }

            $eligibleTotal =
                $subtotal - $discount - $walletUsed;

            $weights = [];

            foreach ($items as $item) {
                $weights[$item->id] = $this->toMinorUnits(
                    (string) $item->total
                );
            }

            $bases = $this->distribute(
                $eligibleTotal,
                $weights
            );

            foreach ($items as $item) {
                $product = $item->product;

                if (! $product) {
                    throw new LogicException(
                        'Cashback order item has no product.'
                    );
                }

                $rule = $this->rules->resolveForProduct($product);

                if (! $rule) {
                    continue;
                }

                $base = $bases[$item->id] ?? 0;

                $percentageBasisPoints = (int) round(
                    ((float) $rule->percentage) * 100
                );

                $cashback = intdiv(
                    $base * $percentageBasisPoints + 5000,
                    10000
                );

                CashbackRuleOrderItem::query()->create([
                    'order_item_id' => $item->id,
                    'cashback_rule_id' => $rule->id,
                    'rule_name_snapshot' => $rule->name,
                    'percentage_snapshot' => $rule->percentage,
                    'eligible_amount' => $this->fromMinorUnits($base),
                    'cashback_amount' => $this->fromMinorUnits($cashback),
                    'expires_after_days_snapshot' =>
                        $rule->expires_after_days,
                ]);
            }

            $lockedOrder->forceFill([
                'cashback_snapshot_completed_at' => now(),
            ])->save();
        });
    }

    /**
     * Convert a decimal amount to integer hundredths.
     */
    private function toMinorUnits(string $amount): int
    {
        if (! preg_match(
            '/^\d+(?:\.\d{1,2})?$/',
            $amount
        )) {
            throw new LogicException(
                'Invalid monetary amount: ' . $amount
            );
        }

        [$whole, $fraction] = array_pad(
            explode('.', $amount, 2),
            2,
            '0'
        );

        return ((int) $whole * 100)
            + (int) str_pad($fraction, 2, '0');
    }

    private function fromMinorUnits(int $amount): string
    {
        return sprintf(
            '%d.%02d',
            intdiv($amount, 100),
            $amount % 100
        );
    }

    /**
     * Proportionally distribute money across order items.
     * The final item receives any rounding remainder.
     *
     * @param array<int, int> $weights
     * @return array<int, int>
     */
    private function distribute(
        int $total,
        array $weights
    ): array {
        $sum = array_sum($weights);

        if ($sum <= 0) {
            throw new LogicException(
                'Cannot distribute over zero subtotal.'
            );
        }

        $result = [];
        $allocated = 0;
        $lastId = array_key_last($weights);

        foreach ($weights as $id => $weight) {
            $amount = $id === $lastId
                ? $total - $allocated
                : intdiv($total * $weight, $sum);

            $result[$id] = $amount;
            $allocated += $amount;
        }

        return $result;
    }
}