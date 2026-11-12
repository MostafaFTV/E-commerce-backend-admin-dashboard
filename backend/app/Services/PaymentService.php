<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentGatewayException;
use App\Exceptions\PaymentRefundException;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        protected PaymentGatewayInterface $gateway,
        protected ActivityLogService $activityLogService
    ) {}

    /**
     * Create a pending payment for an order and
     * request a payment session from the gateway.
     */
    public function createPayment(Order $order): array
    {
        return DB::transaction(function () use ($order): array {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $order->isPendingPayment()) {
                throw ValidationException::withMessages([
                    'order' =>
                        'This order is not awaiting payment.',
                ]);
            }

            if ($order->created_at->addMinutes(30)->lte(now())) {
                throw ValidationException::withMessages([
                    'order' => 'Order payment window has expired.',
                ]);
            }
            if ((float) $order->total <= 0) {
                throw ValidationException::withMessages([
                    'payment' => 'Wallet-only payments are not supported yet.',
                ]);
            }

            $existingPayment = Payment::query()
                ->where(
                    'order_id',
                    $order->id
                )
                ->where(
                    'status',
                    PaymentStatus::PENDING
                )
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($existingPayment) {
                return $this->paymentResponse(
                    $existingPayment
                );
            }

            // Checkout already reserved the first payment attempt.
            // Re-acquire inventory and wallet only after a failed attempt.
            $hasFailedAttempt = Payment::query()
                ->where('order_id', $order->id)
                ->where('status', PaymentStatus::FAILED)
                ->exists();
            if ($hasFailedAttempt) {
                app(OrderPaymentReservationService::class)->ensureReserved($order);
            }

            $payment = Payment::create([
                'order_id' =>
                    $order->id,

                'transaction_id' =>
                    null,

                'gateway_reference' =>
                    null,

                'amount' =>
                    $order->total,

                'currency' =>
                    $order->currency,

                'method' =>
                    'gateway',

                'gateway_response' =>
                    null,

                'status' =>
                    PaymentStatus::PENDING,

                'paid_at' =>
                    null,
            ]);

            $result = $this->gateway->requestPayment(
                $payment
            );

            /*
            |--------------------------------------------------------------------------
            | Gateway Response Validation
            |--------------------------------------------------------------------------
            */

            if (
                ! is_array($result) ||
                empty($result['reference']) ||
                empty($result['payment_url'])
            ) {
                throw new PaymentGatewayException(
                    'The payment gateway returned an invalid payment response.'
                );
            }

            $payment->update([
                'gateway_reference' =>
                    $result['reference'],

                'gateway_response' =>
                    $result,
            ]);

            $this->activityLogService->log(
                action: 'payments.created',
                description: 'Payment created.',
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

                        'method' =>
                            $payment->method,

                        'status' =>
                            $payment->status->value,
                    ],

                    'gateway_reference' =>
                        $payment->gateway_reference,
                ],
                subject: $payment
            );

            return $this->paymentResponse(
                $payment->fresh()
            );
        });
    }

    /**
     * Handle a gateway callback and finalize a successful payment.
     */
    public function handleCallback(
        array $callbackData
    ): Payment {
        $reference = $this->gateway->extractReference(
            $callbackData
        );

        if ($reference === '') {
            throw ValidationException::withMessages([
                'payment' =>
                    'Payment reference is missing.',
            ]);
        }

        $payment = Payment::query()
            ->where(
                'gateway_reference',
                $reference
            )
            ->first();

        if (! $payment) {
            throw ValidationException::withMessages([
                'payment' =>
                    'Payment could not be found.',
            ]);
        }

        if ($payment->isPaid()) {
            return $payment->fresh([
                'order',
            ]);
        }

        if (! $payment->isPending()) {
            // A timeout marks the attempt failed, but its gateway may
            // subsequently confirm that real money was collected.
            if ($payment->isFailed()
                && data_get($payment->gateway_response, 'expiration.reason') === 'payment_timeout') {
                $late = $this->gateway->verifyPayment($payment, $callbackData);
                if ($late['success'] ?? false) {
                    $this->validateSuccessfulVerification($payment, $late);
                    $this->recordReconciliation($payment, $late, 'expired_payment');
                }
            }
            throw ValidationException::withMessages([
                'payment' => 'This payment is no longer payable.',
            ]);
        }

        $verification = $this->gateway->verifyPayment(
            $payment,
            $callbackData
        );

        if (! ($verification['success'] ?? false)) {
            return $this->markPaymentFailed(
                $payment,
                $verification
            );
        }

        $this->validateSuccessfulVerification(
            $payment,
            $verification
        );

        // Persist the reconciliation outside the paid-order transaction.
        // Throwing inside the latter would roll this audit back too.
        $currentOrder = $payment->order()->firstOrFail();
        // A re-entrant callback may have finalized this very payment
        // during gateway verification. Do not flag it as late or duplicate.
        if ($payment->fresh()->isPaid()) {
            return $payment->fresh(['order']);
        }
        if ($currentOrder->created_at->addMinutes(30)->lte(now())
            || !$currentOrder->isPendingPayment()) {
            $this->recordReconciliation($payment, $verification, 'late_or_closed_order');
            throw ValidationException::withMessages([
                $currentOrder->isPendingPayment() ? 'order' : 'order' =>
                    $currentOrder->isPendingPayment()
                        ? 'Order payment window has expired; payment requires reconciliation.'
                        : 'This order is no longer awaiting payment.',
            ]);
        }

        return DB::transaction(function () use (
            $payment,
            $verification
        ): Payment {
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->isPaid()) {
                return $lockedPayment->fresh([
                    'order',
                ]);
            }

            if (! $lockedPayment->isPending()) {
                throw ValidationException::withMessages([
                    'payment' =>
                        'This payment is no longer payable.',
                ]);
            }

            $order = Order::query()
                ->whereKey($lockedPayment->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $order->isPendingPayment()) {
                throw ValidationException::withMessages([
                    'order' =>
                        'This order is no longer awaiting payment.',
                ]);
            }

            if ($order->created_at->addMinutes(30)->lte(now())) {
                throw ValidationException::withMessages([
                    'order' => 'Order payment window has expired; payment requires reconciliation.',
                ]);
            }

            $lockedPayment->update([
                'status' =>
                    PaymentStatus::PAID,

                'transaction_id' =>
                    $verification['transaction_id'],

                'gateway_response' =>
                    $verification['gateway_response']
                    ?? $verification,

                'paid_at' =>
                    now(),
            ]);

            $inventoryService = app(
                InventoryService::class
            );

            $order->load(
                'items.variant.inventory'
            );

            foreach ($order->items as $orderItem) {
                if (! $orderItem->variant) {
                    throw ValidationException::withMessages([
                        'payment' =>
                            'The order variant could not be found.',
                    ]);
                }

                $inventory =
                    $orderItem->variant->inventory;

                if (! $inventory) {
                    throw ValidationException::withMessages([
                        'payment' =>
                            'The order inventory could not be found.',
                    ]);
                }

                if (
                    $inventory->reserved_quantity <
                    $orderItem->quantity
                ) {
                    throw ValidationException::withMessages([
                        'payment' =>
                            'Reserved inventory is insufficient.',
                    ]);
                }

                $inventoryService->sellStock(
                    $inventory,
                    $orderItem->quantity,
                    $order,
                    $orderItem
                );
            }

            $fromStatus = $order->status;

            $order->update([
                'status' =>
                    OrderStatus::PAID,
            ]);

            /*
             * Award pending cashback only after the gateway
             * has verified payment and the order is paid.
             *
             * Legacy orders without cashback snapshots are
             * intentionally left unchanged.
             */
            if ((float) $order->wallet_credit_used > 0) {
                app(WalletSpendingService::class)->settle($order);
            }

            if ($order->cashback_snapshot_completed_at !== null) {
                app(WalletService::class)->issuePending($order);
            }

            OrderStatusHistory::create([
                'order_id' =>
                    $order->id,

                'from_status' =>
                    $fromStatus,

                'to_status' =>
                    OrderStatus::PAID,

                'changed_by' =>
                    null,

                'note' =>
                    'Payment verified successfully by gateway.',
            ]);

            $this->activityLogService->log(
                action: 'payments.paid',
                description:
                    'Payment completed successfully.',
                properties: [
                    'payment' => [
                        'id' =>
                            $lockedPayment->id,

                        'order_id' =>
                            $order->id,

                        'order_number' =>
                            $order->order_number,

                        'amount' =>
                            (float) $lockedPayment->amount,

                        'currency' =>
                            $lockedPayment->currency,

                        'status' =>
                            $lockedPayment->status->value,
                    ],

                    'transaction_id' =>
                        $lockedPayment->transaction_id,

                    'paid_at' =>
                        $lockedPayment->paid_at?->toISOString(),
                ],
                subject: $lockedPayment
            );

            $this->activityLogService->log(
                action: 'orders.status_changed',
                description:
                    'Order status changed.',
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
                        OrderStatus::PAID->value,

                    'changed_by' =>
                        null,

                    'note' =>
                        'Payment verified successfully by gateway.',
                ],
                subject: $order
            );

            return $lockedPayment->fresh([
                'order',
            ]);
        });
    }

    /**
     * Refund a successful payment.
     *
     * This method only changes the payment state and communicates
     * with the gateway. Order status and inventory restoration
     * remain the responsibility of OrderStatusService.
     */
    public function refundPayment(
        Payment $payment
    ): Payment {
        return DB::transaction(function () use (
            $payment
        ): Payment {
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
            |--------------------------------------------------------------------------
            | Idempotency
            |--------------------------------------------------------------------------
            */

            if ($lockedPayment->isRefunded()) {
                return $lockedPayment->fresh([
                    'order',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Only PAID payments may be refunded.
            |--------------------------------------------------------------------------
            */

            if (! $lockedPayment->isPaid()) {
                throw ValidationException::withMessages([
                    'payment' =>
                        'Only paid payments can be refunded.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Gateway refund
            |--------------------------------------------------------------------------
            */

            $result = $this->gateway->refundPayment(
                $lockedPayment
            );

            if (! ($result['success'] ?? false)) {
                throw new PaymentRefundException(
                    'The payment gateway could not refund the payment.'
                );
            }

            $refundTransactionId =
                $result['refund_transaction_id']
                ?? null;

            $gatewayResponse =
                $result['gateway_response']
                ?? null;

            /*
            |--------------------------------------------------------------------------
            | Store refund result
            |--------------------------------------------------------------------------
            */

            $existingGatewayResponse =
                is_array(
                    $lockedPayment->gateway_response
                )
                    ? $lockedPayment->gateway_response
                    : [];

            $refundData = [
                'transaction_id' =>
                    $refundTransactionId,

                'status' =>
                    'refunded',
            ];

            if ($gatewayResponse !== null) {
                $existingGatewayResponse[
                    'refund_gateway_response'
                ] = $gatewayResponse;
            }

            $existingGatewayResponse['refund'] =
                $refundData;

            $lockedPayment->update([
                'status' =>
                    PaymentStatus::REFUNDED,

                'gateway_response' =>
                    $existingGatewayResponse,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Activity Log
            |--------------------------------------------------------------------------
            */

            $this->activityLogService->log(
                action: 'payments.refunded',
                description:
                    'Payment refunded successfully.',
                properties: [
                    'payment' => [
                        'id' =>
                            $lockedPayment->id,

                        'order_id' =>
                            $lockedPayment->order_id,

                        'order_number' =>
                            $lockedPayment->order?->order_number,

                        'amount' =>
                            (float) $lockedPayment->amount,

                        'currency' =>
                            $lockedPayment->currency,

                        'status' =>
                            $lockedPayment->status->value,
                    ],

                    'original_transaction_id' =>
                        $lockedPayment->transaction_id,

                    'refund_transaction_id' =>
                        $refundTransactionId,

                    'refunded_at' =>
                        now()->toISOString(),
                ],
                subject: $lockedPayment
            );

            return $lockedPayment->fresh([
                'order',
            ]);
        });
    }

    protected function validateSuccessfulVerification(
        Payment $payment,
        array $verification
    ): void {
        if (
            array_key_exists(
                'amount',
                $verification
            ) &&
            $verification['amount'] !== null
        ) {
            if (
                (float) $verification['amount'] !==
                (float) $payment->amount
            ) {
                throw ValidationException::withMessages([
                    'payment' =>
                        'The verified payment amount does not match the order amount.',
                ]);
            }
        }

        if (
            array_key_exists(
                'currency',
                $verification
            ) &&
            $verification['currency'] !== null
        ) {
            if (
                strtoupper(
                    (string) $verification['currency']
                ) !==
                strtoupper(
                    (string) $payment->currency
                )
            ) {
                throw ValidationException::withMessages([
                    'payment' =>
                        'The verified payment currency does not match the order currency.',
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Transaction ID is mandatory for successful payments.
        |--------------------------------------------------------------------------
        */

        if (
            ! array_key_exists(
                'transaction_id',
                $verification
            ) ||
            $verification['transaction_id'] === null ||
            trim(
                (string) $verification['transaction_id']
            ) === ''
        ) {
            throw ValidationException::withMessages([
                'payment' =>
                    'The gateway returned an invalid transaction ID.',
            ]);
        }
    }

    protected function markPaymentFailed(
        Payment $payment,
        array $verification
    ): Payment {
        return DB::transaction(function () use (
            $payment,
            $verification
        ): Payment {
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->isPaid()) {
                return $lockedPayment->fresh([
                    'order',
                ]);
            }

            if (! $lockedPayment->isPending()) {
                return $lockedPayment->fresh([
                    'order',
                ]);
            }

            $order = Order::query()
                ->whereKey($lockedPayment->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            app(OrderPaymentReservationService::class)->releaseStock($order);
            $lockedPayment->update([
                'status' =>
                    PaymentStatus::FAILED,

                'gateway_response' =>
                    $verification['gateway_response']
                    ?? $verification,
            ]);

            /*
             * Failed payment cannot retain wallet reservations.
             * A later payment attempt will reserve fresh wallet credit.
             */
            app(WalletSpendingService::class)->release($order);

            $this->activityLogService->log(
                action: 'payments.failed',
                description:
                    'Payment failed.',
                properties: [
                    'payment' => [
                        'id' =>
                            $lockedPayment->id,

                        'order_id' =>
                            $order->id,

                        'order_number' =>
                            $order->order_number,

                        'amount' =>
                            (float) $lockedPayment->amount,

                        'currency' =>
                            $lockedPayment->currency,

                        'status' =>
                            $lockedPayment->status->value,
                    ],
                ],
                subject: $lockedPayment
            );

            return $lockedPayment->fresh([
                'order',
            ]);
        });
    }

    /** Record verified bank funds which cannot complete this order. */
    protected function recordReconciliation(Payment $payment, array $verification, string $reason): void
    {
        DB::table('payment_reconciliations')->updateOrInsert(
            ['payment_id' => $payment->id],
            [
                'order_id' => $payment->order_id,
                'gateway_transaction_id' => (string) $verification['transaction_id'],
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'reason' => $reason,
                'status' => 'requires_review',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    protected function paymentResponse(
        Payment $payment
    ): array {
        return [
            'payment' =>
                $payment,

            'payment_url' =>
                data_get(
                    $payment->gateway_response,
                    'payment_url'
                ),

            'gateway_reference' =>
                $payment->gateway_reference,
        ];
    }
}
