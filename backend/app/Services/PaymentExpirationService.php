<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentExpirationService
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {}

    public function expirePendingPayments(
        int $timeoutMinutes = 30
    ): int {
        $cutoff = now()->subMinutes($timeoutMinutes);

        // Expire orders, not payment attempts. This includes orders that
        // never reached the gateway and orders with only failed attempts.
        $orderIds = Order::query()
            ->where('status', OrderStatus::PENDING_PAYMENT)
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->pluck('id');

        $expiredCount = 0;
        foreach ($orderIds as $orderId) {
            if ($this->expireOrder((int) $orderId, $timeoutMinutes)) {
                $expiredCount++;
            }
        }
        return $expiredCount;
    }

    /** Expire an order regardless of whether it ever had a payment. */
    public function expireOrder(int $orderId, int $timeoutMinutes = 30): bool
    {
        if ($timeoutMinutes < 1) {
            throw new RuntimeException('Invalid payment timeout.');
        }

        // Reuse the established payment expiration path if a pending
        // payment exists; this preserves its audit contract.
        $pending = Payment::query()
            ->where('order_id', $orderId)
            ->where('status', PaymentStatus::PENDING)
            ->latest('id')->first();
        if ($pending) {
            return $this->expirePayment((int) $pending->id);
        }

        return DB::transaction(function () use ($orderId, $timeoutMinutes): bool {
            $order = Order::query()->whereKey($orderId)
                ->lockForUpdate()->first();
            if (!$order || !$order->isPendingPayment()
                || $order->created_at->addMinutes($timeoutMinutes)->gt(now())) {
                return false;
            }
            // Another request may have created a pending payment.
            $pending = Payment::query()->where('order_id', $order->id)
                ->where('status', PaymentStatus::PENDING)->lockForUpdate()->first();
            if ($pending) {
                return false; // Next scheduler tick processes the payment.
            }

            app(OrderPaymentReservationService::class)->releaseStock($order);
            app(WalletSpendingService::class)->release($order);
            $order->update(['status' => OrderStatus::CANCELLED]);
            $note = 'Order cancelled automatically because payment expired.';
            $order->statusHistory()->create([
                'from_status' => OrderStatus::PENDING_PAYMENT,
                'to_status' => OrderStatus::CANCELLED,
                'changed_by' => null,
                'note' => $note,
            ]);
            $this->activityLogService->log(
                action: 'orders.status_changed',
                description: 'Order status changed.',
                properties: [
                    'order' => ['id' => $order->id, 'order_number' => $order->order_number],
                    'from_status' => OrderStatus::PENDING_PAYMENT->value,
                    'to_status' => OrderStatus::CANCELLED->value,
                    'changed_by' => null,
                    'note' => $note,
                    'reason' => 'payment_timeout',
                    'payment_id' => null,
                ],
                subject: $order
            );
            return true;
        });
    }

    public function expirePayment(
        int $paymentId
    ): bool {
        return DB::transaction(
            function () use ($paymentId): bool {
                $payment = Payment::query()
                    ->whereKey($paymentId)
                    ->lockForUpdate()
                    ->first();

                if (! $payment) {
                    return false;
                }

                if (! $payment->isPending()) {
                    return false;
                }

                $order = Order::query()
                    ->whereKey($payment->order_id)
                    ->lockForUpdate()
                    ->first();

                if (! $order) {
                    return false;
                }

                if (! $order->isPendingPayment()
                    || $order->created_at->addMinutes(30)->gt(now())) {
                    return false;
                }

                app(OrderPaymentReservationService::class)->releaseStock($order);
                app(WalletSpendingService::class)->release($order);
                $expiredAt = now();

                $payment->update([
                    'status' =>
                        PaymentStatus::FAILED,

                    'gateway_response' =>
                        array_merge(
                            $payment->gateway_response ?? [],
                            [
                                'expiration' => [
                                    'expired_at' =>
                                        $expiredAt->toISOString(),

                                    'reason' =>
                                        'payment_timeout',
                                ],
                            ]
                        ),
                ]);

                $this->activityLogService->log(
                    action: 'payments.expired',
                    description: 'Payment expired automatically.',
                    properties: [
                        'payment' => [
                            'id' =>
                                $payment->id,

                            'order_id' =>
                                $order->id,

                            'order_number' =>
                                $order->order_number,

                            'amount' =>
                                (float) $payment->amount,

                            'currency' =>
                                $payment->currency,

                            'previous_status' =>
                                PaymentStatus::PENDING->value,

                            'status' =>
                                PaymentStatus::FAILED->value,
                        ],

                        'reason' =>
                            'payment_timeout',

                        'timeout_minutes' =>
                            max(
                                0,
                                now()->diffInMinutes(
                                    $payment->created_at,
                                    false
                                )
                            ),

                        'expired_at' =>
                            $expiredAt->toISOString(),
                    ],
                    subject: $payment
                );

                $fromStatus =
                    $order->status;

                $order->update([
                    'status' =>
                        OrderStatus::CANCELLED,
                ]);

                $order->statusHistory()->create([
                    'from_status' =>
                        $fromStatus,

                    'to_status' =>
                        OrderStatus::CANCELLED,

                    'changed_by' =>
                        null,

                    'note' =>
                        'Order cancelled automatically because payment expired.',
                ]);

                $this->activityLogService->log(
                    action: 'orders.status_changed',
                    description: 'Order status changed.',
                    properties: [
                        'order' => [
                            'id' =>
                                $order->id,

                            'order_number' =>
                                $order->order_number,
                        ],

                        'from_status' =>
                            $fromStatus->value,

                        'to_status' =>
                            OrderStatus::CANCELLED->value,

                        'changed_by' =>
                            null,

                        'note' =>
                            'Order cancelled automatically because payment expired.',

                        'reason' =>
                            'payment_timeout',

                        'payment_id' =>
                            $payment->id,
                    ],
                    subject: $order
                );

                return true;
            }
        );
    }
}
