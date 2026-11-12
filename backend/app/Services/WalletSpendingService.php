<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\WalletAllocation;
use App\Models\WalletCredit;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use LogicException;

class WalletSpendingService
{
    public function reserve(Order $order, string $amount): void
    {
        $requested = $this->minor($amount);

        if ($requested <= 0) {
            throw new LogicException('Wallet reservation must be positive.');
        }

        DB::transaction(function () use ($order, $requested): void {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== OrderStatus::PENDING_PAYMENT) {
                throw new LogicException(
                    'Wallet reservation requires pending payment.'
                );
            }

            /*
             * Legacy test orders have no wallet request.
             * New Checkout orders persist the exact requested
             * amount and pre-wallet total.
             */
            $recorded = $this->minor(
                (string) ($order->wallet_credit_used ?? '0.00')
            );

            $beforeWallet = $order->total_before_wallet !== null
                ? $this->minor(
                    (string) $order->total_before_wallet
                )
                : $this->minor((string) $order->total);

            if (
                $requested > $beforeWallet ||
                ($recorded > 0 && $requested !== $recorded)
            ) {
                throw new LogicException(
                    'Wallet reservation does not match order amounts.'
                );
            }

            $wallet = Wallet::query()
                ->where('user_id', $order->user_id)
                ->lockForUpdate()
                ->first();

            if (!$wallet || $wallet->currency !== $order->currency) {
                throw new LogicException('Compatible wallet not found.');
            }

            $existing = WalletAllocation::query()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->get();

            if ($existing->isNotEmpty()) {
                $sum = 0;
                foreach ($existing as $allocation) {
                    if ($allocation->status === 'spent' || $allocation->status === 'restored') {
                        throw new LogicException('Order has settled wallet allocations.');
                    }
                    if ($allocation->status === 'reserved') {
                        $sum += $this->minor((string) $allocation->amount);
                    }
                }
                if ($sum === $requested) { return; }
                if ($sum !== 0) {
                    throw new LogicException('Partial wallet reservation cannot be retried.');
                }
                // Previously released allocations remain as immutable history.
                // A retry creates new allocations with distinct idempotency keys.
            }

            $credits = WalletCredit::query()
                ->where('wallet_id', $wallet->id)
                ->where('status', 'available')
                ->where('remaining_amount', '>', 0)
                ->where('available_at', '<=', now())
                ->where('expires_at', '>', now())
                ->orderBy('expires_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $available = 0;

            foreach ($credits as $credit) {
                $available += $this->minor(
                    (string) $credit->remaining_amount
                );
            }

            if ($available < $requested) {
                throw new LogicException('Insufficient wallet balance.');
            }

            $left = $requested;

            foreach ($credits as $credit) {
                if ($left === 0) {
                    break;
                }

                $current = $this->minor(
                    (string) $credit->remaining_amount
                );

                $take = min($current, $left);
                $remaining = $current - $take;

                $credit->update([
                    'remaining_amount' => $this->money($remaining),
                    'status' => $remaining === 0
                        ? 'exhausted'
                        : 'available',
                ]);

                $allocation = WalletAllocation::query()->create([
                    'wallet_credit_id' => $credit->id,
                    'order_id' => $order->id,
                    'amount' => $this->money($take),
                    'status' => 'reserved',
                ]);

                $this->record(
                    $wallet,
                    $allocation,
                    'credit_reserved'
                );

                $left -= $take;
            }
        });
    }

    public function settle(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($order->status, [
                OrderStatus::PAID,
                OrderStatus::PROCESSING,
                OrderStatus::SHIPPED,
                OrderStatus::DELIVERED,
            ], true)) {
                throw new LogicException(
                    'Wallet settlement requires a paid order.'
                );
            }

            $allocations = WalletAllocation::query()
                ->where('order_id', $order->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            // Released allocations belong to earlier failed attempts.
            // They must never be charged again or counted toward settlement.
            $expected = $this->minor((string) ($order->wallet_credit_used ?? '0.00'));
            $active = 0;
            foreach ($allocations as $allocation) {
                if (in_array($allocation->status, ['reserved', 'spent'], true)) {
                    $active += $this->minor((string) $allocation->amount);
                } elseif (!in_array($allocation->status, ['released'], true)) {
                    throw new LogicException('Unexpected wallet allocation state.');
                }
            }
            if ($order->total_before_wallet === null && $expected === 0) {
                // Legacy test/manual orders did not persist wallet_credit_used.
                $expected = $active;
            }
            if ($active !== $expected) {
                throw new LogicException('Wallet settlement amount mismatch.');
            }

            foreach ($allocations as $allocation) {
                if (in_array($allocation->status, ['spent', 'released'], true)) {
                    continue;
                }
                $allocation->update(['status' => 'spent']);

                $this->record(
                    $allocation->walletCredit->wallet,
                    $allocation,
                    'credit_spent'
                );
            }
        });
    }

    public function release(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($order->status, [
                OrderStatus::PENDING_PAYMENT,
                OrderStatus::CANCELLED,
                OrderStatus::RETURNED,
            ], true)) {
                throw new LogicException(
                    'Cannot release wallet credit on an active paid order.'
                );
            }

            $wallet = Wallet::query()
                ->where('user_id', $order->user_id)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                return;
            }

            $allocations = WalletAllocation::query()
                ->where('order_id', $order->id)
                ->where('status', 'reserved')
                ->orderBy('wallet_credit_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($allocations as $allocation) {
                $credit = WalletCredit::query()
                    ->whereKey($allocation->wallet_credit_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $credit->wallet_id !== (int) $wallet->id) {
                    throw new LogicException(
                        'Wallet allocation ownership mismatch.'
                    );
                }

                $amount = $this->minor(
                    (string) $allocation->amount
                );

                $expired = $credit->expires_at === null
                    || $credit->expires_at->lte(now())
                    || $credit->status === 'expired'
                    || $credit->status === 'reversed';

                if (!$expired) {
                    $remaining = $this->minor(
                        (string) $credit->remaining_amount
                    ) + $amount;

                    $credit->update([
                        'remaining_amount' => $this->money($remaining),
                        'status' => 'available',
                    ]);
                }

                $allocation->update(['status' => 'released']);

                $this->record(
                    $wallet,
                    $allocation,
                    'credit_released',
                    ['restored_to_available' => !$expired]
                );
            }
        });
    }

    public function expireAvailableCredits(int $batchSize = 100): int
    {
        if ($batchSize < 1 || $batchSize > 1000) {
            throw new LogicException('Invalid expiration batch size.');
        }

        $ids = WalletCredit::query()
            ->where('status', 'available')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($batchSize)
            ->pluck('id');

        $expiredCount = 0;

        foreach ($ids as $id) {
            DB::transaction(function () use (
                $id,
                &$expiredCount
            ): void {
                $initial = WalletCredit::query()
                    ->find($id);

                if (!$initial) {
                    return;
                }

                $wallet = Wallet::query()
                    ->whereKey($initial->wallet_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $credit = WalletCredit::query()
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $credit->status !== 'available'
                    || !$credit->isExpired()
                ) {
                    return;
                }

                $amount = $this->minor(
                    (string) $credit->remaining_amount
                );

                $credit->update([
                    'status' => 'expired',
                    'remaining_amount' => '0.00',
                ]);

                WalletTransaction::query()->firstOrCreate(
                    ['idempotency_key' => 'credit:expired:'.$id],
                    [
                        'wallet_id' => $wallet->id,
                        'order_id' => $credit->source_order_id,
                        'type' => 'credit_expired',
                        'amount' => $this->money($amount),
                    ]
                );

                $expiredCount++;
            });
        }

        return $expiredCount;
    }

    private function record(
        Wallet $wallet,
        WalletAllocation $allocation,
        string $type,
        array $metadata = []
    ): void {
        WalletTransaction::query()->firstOrCreate(
            [
                'idempotency_key' =>
                    'allocation:'.$type.':'.$allocation->id,
            ],
            [
                'wallet_id' => $wallet->id,
                'wallet_allocation_id' => $allocation->id,
                'order_id' => $allocation->order_id,
                'type' => $type,
                'amount' => $allocation->amount,
                'metadata' => $metadata,
            ]
        );
    }

    private function minor(string $amount): int
    {
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new LogicException('Invalid monetary amount.');
        }

        [$whole, $fraction] = array_pad(
            explode('.', $amount, 2),
            2,
            '0'
        );

        if (strlen($whole) > 10) {
            throw new LogicException('Monetary amount exceeds range.');
        }

        return ((int) $whole * 100)
            + (int) str_pad($fraction, 2, '0');
    }

    private function money(int $amount): string
    {
        if ($amount < 0) {
            throw new LogicException('Negative wallet balance.');
        }

        return sprintf(
            '%d.%02d',
            intdiv($amount, 100),
            $amount % 100
        );
    }
}
