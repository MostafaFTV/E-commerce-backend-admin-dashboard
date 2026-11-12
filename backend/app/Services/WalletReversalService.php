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

class WalletReversalService
{
    /**
     * Reject irreversible cashback conflicts before requesting
     * a gateway refund.
     */
    public function assertCanReverseEarned(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet = Wallet::query()
                ->where('user_id', $lockedOrder->user_id)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                return;
            }

            $credits = WalletCredit::query()
                ->where('source_order_id', $lockedOrder->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($credits as $credit) {
                if ((int) $credit->wallet_id !== (int) $wallet->id) {
                    throw new LogicException(
                        'Cashback credit ownership mismatch.'
                    );
                }

                if ($credit->status === 'reversed') {
                    continue;
                }

                if (
                    WalletAllocation::query()
                        ->where('wallet_credit_id', $credit->id)
                        ->whereIn('status', ['reserved', 'spent'])
                        ->exists()
                ) {
                    throw new LogicException(
                        'Source cashback has active allocations; refund requires manual reconciliation.'
                    );
                }
            }
        });
    }

    public function reverseEarned(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = $this->terminalOrder($order);

            $wallet = Wallet::query()
                ->where('user_id', $order->user_id)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                return;
            }

            $credits = WalletCredit::query()
                ->where('source_order_id', $order->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($credits as $credit) {
                if ((int) $credit->wallet_id !== (int) $wallet->id) {
                    throw new LogicException(
                        'Cashback credit ownership mismatch.'
                    );
                }

                if ($credit->status === 'reversed') {
                    continue;
                }

                $activeAllocations = WalletAllocation::query()
                    ->where('wallet_credit_id', $credit->id)
                    ->whereIn('status', ['reserved', 'spent'])
                    ->exists();

                if ($activeAllocations) {
                    throw new LogicException(
                        'Cashback already reserved or spent; manual reconciliation required.'
                    );
                }
            }

            foreach ($credits as $credit) {
                if ($credit->status === 'reversed') {
                    continue;
                }

                $remaining = $this->minor(
                    (string) $credit->remaining_amount
                );

                $credit->update([
                    'status' => 'reversed',
                    'remaining_amount' => '0.00',
                ]);

                WalletTransaction::query()->firstOrCreate(
                    [
                        'idempotency_key' =>
                            'cashback:reversed:'.$credit->id,
                    ],
                    [
                        'wallet_id' => $wallet->id,
                        'order_id' => $order->id,
                        'type' => 'cashback_reversed',
                        'amount' => $this->money($remaining),
                        'metadata' => [
                            'original_amount' =>
                                $credit->original_amount,
                        ],
                    ]
                );
            }
        });
    }

    public function restoreSpent(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = $this->terminalOrder($order);

            $wallet = Wallet::query()
                ->where('user_id', $order->user_id)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                return;
            }

            $allocations = WalletAllocation::query()
                ->where('order_id', $order->id)
                ->where('status', 'spent')
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

                if ($credit->status === 'reversed') {
                    throw new LogicException(
                        'Cannot restore reversed source cashback.'
                    );
                }

                $amount = $this->minor(
                    (string) $allocation->amount
                );

                $valid = $credit->expires_at !== null
                    && $credit->expires_at->gt(now())
                    && $credit->available_at !== null;

                if ($valid) {
                    $remaining = $this->minor(
                        (string) $credit->remaining_amount
                    );

                    $original = $this->minor(
                        (string) $credit->original_amount
                    );

                    if ($remaining + $amount > $original) {
                        throw new LogicException(
                            'Restoration exceeds original credit.'
                        );
                    }

                    $credit->update([
                        'remaining_amount' =>
                            $this->money($remaining + $amount),
                        'status' => 'available',
                    ]);
                }

                $allocation->update([
                    'status' => 'restored',
                ]);

                WalletTransaction::query()->firstOrCreate(
                    [
                        'idempotency_key' =>
                            'allocation:credit_restored:'.$allocation->id,
                    ],
                    [
                        'wallet_id' => $wallet->id,
                        'wallet_allocation_id' => $allocation->id,
                        'order_id' => $order->id,
                        'type' => 'credit_restored',
                        'amount' => $allocation->amount,
                        'metadata' => [
                            'restored_to_available' => $valid,
                            'original_expires_at' =>
                                $credit->expires_at?->toISOString(),
                        ],
                    ]
                );
            }
        });
    }

    private function terminalOrder(Order $order): Order
    {
        $locked = Order::query()
            ->whereKey($order->id)
            ->lockForUpdate()
            ->firstOrFail();

        if (!in_array($locked->status, [
            OrderStatus::CANCELLED,
            OrderStatus::RETURNED,
        ], true)) {
            throw new LogicException(
                'Wallet reversal requires a terminal order.'
            );
        }

        return $locked;
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
        return sprintf(
            '%d.%02d',
            intdiv($amount, 100),
            $amount % 100
        );
    }
}