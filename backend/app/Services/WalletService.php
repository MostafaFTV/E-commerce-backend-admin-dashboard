<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletCredit;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use LogicException;

class WalletService
{
    public function issuePending(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($order->status, [
                OrderStatus::PAID,
                OrderStatus::PROCESSING,
                OrderStatus::SHIPPED,
                OrderStatus::DELIVERED,
            ], true)) {
                throw new LogicException(
                    'Cashback requires a paid or fulfilled order.'
                );
            }

            if ($order->cashback_snapshot_completed_at === null) {
                throw new LogicException(
                    'Cashback snapshots are not completed.'
                );
            }

            $user = User::query()
                ->whereKey($order->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet = Wallet::query()
                ->firstOrCreate(
                    ['user_id' => $user->id],
                    ['currency' => 'IRT']
                );

            $wallet = Wallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($wallet->currency !== $order->currency) {
                throw new LogicException(
                    'Wallet and order currencies differ.'
                );
            }

            $snapshots = DB::table('cashback_rule_order_items')
                ->join(
                    'order_items',
                    'order_items.id',
                    '=',
                    'cashback_rule_order_items.order_item_id'
                )
                ->where('order_items.order_id', $order->id)
                ->select('cashback_rule_order_items.*')
                ->orderBy('cashback_rule_order_items.id')
                ->get();

            foreach ($snapshots as $snapshot) {
                $amount = $this->toMinorUnits(
                    (string) $snapshot->cashback_amount
                );

                if ($amount <= 0) {
                    continue;
                }

                $credit = WalletCredit::query()
                    ->firstOrCreate(
                        [
                            'cashback_rule_order_item_id' =>
                                $snapshot->id,
                        ],
                        [
                            'wallet_id' => $wallet->id,
                            'source_order_id' => $order->id,
                            'original_amount' =>
                                $snapshot->cashback_amount,
                            'remaining_amount' =>
                                $snapshot->cashback_amount,
                            'status' => 'pending',
                            'expires_after_days_snapshot' =>
                                $snapshot->expires_after_days_snapshot,
                        ]
                    );

                if (
                    (int) $credit->wallet_id !== (int) $wallet->id ||
                    (int) $credit->source_order_id !== (int) $order->id
                ) {
                    throw new LogicException(
                        'Cashback credit ownership mismatch.'
                    );
                }

                WalletTransaction::query()->firstOrCreate(
                    [
                        'idempotency_key' =>
                            'cashback:pending:'.$credit->id,
                    ],
                    [
                        'wallet_id' => $wallet->id,
                        'order_id' => $order->id,
                        'type' => 'cashback_pending',
                        'amount' => $credit->original_amount,
                    ]
                );
            }
        });
    }

    public function activateDelivered(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== OrderStatus::DELIVERED) {
                throw new LogicException(
                    'Only delivered orders can activate cashback.'
                );
            }

            $this->issuePending($order);

            $credits = WalletCredit::query()
                ->where('source_order_id', $order->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($credits as $credit) {
                if ($credit->status !== 'pending') {
                    continue;
                }

                $activatedAt = now();

                $credit->update([
                    'status' => 'available',
                    'available_at' => $activatedAt,
                    'expires_at' => $activatedAt->copy()
                        ->addDays(
                            $credit->expires_after_days_snapshot
                        ),
                ]);

                WalletTransaction::query()->firstOrCreate(
                    [
                        'idempotency_key' =>
                            'cashback:available:'.$credit->id,
                    ],
                    [
                        'wallet_id' => $credit->wallet_id,
                        'order_id' => $order->id,
                        'type' => 'cashback_available',
                        'amount' => $credit->original_amount,
                    ]
                );
            }
        });
    }

    public function balances(User $user): array
    {
        $wallet = Wallet::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $wallet) {
            return [
                'pending' => '0.00',
                'available' => '0.00',
                'currency' => 'IRT',
            ];
        }

        $pending = 0;
        $available = 0;

        foreach ($wallet->credits as $credit) {
            if ($credit->status === 'pending') {
                $pending += $this->toMinorUnits(
                    (string) $credit->remaining_amount
                );
            }

            if ($credit->isAvailable()) {
                $available += $this->toMinorUnits(
                    (string) $credit->remaining_amount
                );
            }
        }

        return [
            'pending' => $this->fromMinorUnits($pending),
            'available' => $this->fromMinorUnits($available),
            'currency' => $wallet->currency,
        ];
    }

    private function toMinorUnits(string $amount): int
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new LogicException('Invalid monetary amount.');
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
}