<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\CartStatus;
use App\Enums\CheckoutSessionStatus;
use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Exceptions\PaymentRefundException;
use App\Models\ActivityLog;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CheckoutSession;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\CheckoutService;
use App\Services\PaymentExpirationService;
use App\Services\PaymentService;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_payment_creates_pending_payment_and_gateway_reference(): void
    {
        $data = $this->preparePendingOrder();

        $service = $this->paymentService();

        $result = $service->createPayment(
            $data['order']
        );

        $payment = $result['payment'];

        $this->assertSame(
            PaymentStatus::PENDING,
            $payment->status
        );

        $this->assertSame(
            $data['order']->id,
            $payment->order_id
        );

        $this->assertSame(
            220.0,
            (float) $payment->amount
        );

        $this->assertNotEmpty(
            $payment->gateway_reference
        );

        $this->assertSame(
            'http://fake-gateway.test/pay/' . $payment->id,
            $result['payment_url']
        );

        $this->assertSame(
            $payment->gateway_reference,
            $result['gateway_reference']
        );
    }

    public function test_concurrent_create_payment_does_not_create_duplicate_pending_payment(): void
    {
        $data = $this->preparePendingOrder();

        $service = $this->paymentService();

        $firstResult = $service->createPayment(
            $data['order']
        );

        $secondResult = $service->createPayment(
            $data['order']->fresh()
        );

        $this->assertSame(
            $firstResult['payment']->id,
            $secondResult['payment']->id
        );

        $this->assertSame(
            PaymentStatus::PENDING,
            $firstResult['payment']->fresh()->status
        );

        $this->assertSame(
            PaymentStatus::PENDING,
            $secondResult['payment']->fresh()->status
        );

        $this->assertSame(
            1,
            Payment::query()
                ->where(
                    'order_id',
                    $data['order']->id
                )
                ->where(
                    'status',
                    PaymentStatus::PENDING
                )
                ->count()
        );

        $this->assertSame(
            1,
            ActivityLog::query()
                ->where(
                    'action',
                    'payments.created'
                )
                ->where(
                    'properties->payment->order_id',
                    $data['order']->id
                )
                ->count()
        );

        $this->assertNotEmpty(
            $firstResult['gateway_reference']
        );

        $this->assertSame(
            $firstResult['gateway_reference'],
            $secondResult['gateway_reference']
        );
    }

    public function test_create_payment_creates_activity_log(): void
    {
        $data = $this->preparePendingOrder();

        $service = $this->paymentService();

        $result = $service->createPayment(
            $data['order']
        );

        $payment = $result['payment'];

        $log = ActivityLog::query()
            ->where(
                'action',
                'payments.created'
            )
            ->where(
                'properties->payment->id',
                $payment->id
            )
            ->latest('id')
            ->first();

        $this->assertNotNull(
            $log
        );

        $this->assertSame(
            'payments.created',
            $log->action
        );

        $this->assertSame(
            'Payment created.',
            $log->description
        );

        $this->assertSame(
            Payment::class,
            $log->properties['subject']['type']
        );

        $this->assertSame(
            $payment->id,
            $log->properties['subject']['id']
        );

        $this->assertSame(
            $payment->id,
            $log->properties['payment']['id']
        );

        $this->assertSame(
            $data['order']->id,
            $log->properties['payment']['order_id']
        );

        $this->assertSame(
            'pending',
            $log->properties['payment']['status']
        );

        $this->assertSame(
            220.0,
            (float) $log->properties['payment']['amount']
        );

        $this->assertSame(
            'IRT',
            $log->properties['payment']['currency']
        );

        $this->assertSame(
            'gateway',
            $log->properties['payment']['method']
        );

        $this->assertNotEmpty(
            $log->properties['gateway_reference']
        );

        $this->assertArrayNotHasKey(
            'gateway_response',
            $log->properties
        );
    }

    public function test_successful_callback_verification_marks_payment_paid(): void
    {
        $data = $this->preparePendingOrder();

        $service = $this->paymentService();

        $paymentResult = $service->createPayment(
            $data['order']
        );

        $payment = $paymentResult['payment'];

        $callback = [
            'reference' =>
                $payment->gateway_reference,

            'gateway_status' =>
                'success',

            'transaction_id' =>
                'FAKE-TXN-001',

            'amount' =>
                220,

            'currency' =>
                'IRT',
        ];

        $paidPayment = $service->handleCallback(
            $callback
        );

        $this->assertSame(
            PaymentStatus::PAID,
            $paidPayment->status
        );

        $this->assertSame(
            'FAKE-TXN-001',
            $paidPayment->transaction_id
        );

        $this->assertNotNull(
            $paidPayment->paid_at
        );

        $this->assertSame(
            OrderStatus::PAID,
            $paidPayment->order->status
        );

        $this->assertSame(
            8,
            $data['inventory']->fresh()->quantity
        );

        $this->assertSame(
            0,
            $data['inventory']->fresh()->reserved_quantity
        );

        $this->assertDatabaseHas('inventory_movements', [
            'order_id' =>
                $data['order']->id,

            'inventory_id' =>
                $data['inventory']->id,

            'type' =>
                InventoryMovementType::SALE->value,

            'quantity_delta' =>
                -2,

            'reserved_delta' =>
                -2,
        ]);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' =>
                $data['order']->id,

            'from_status' =>
                OrderStatus::PENDING_PAYMENT->value,

            'to_status' =>
                OrderStatus::PAID->value,
        ]);
    }

    public function test_successful_payment_creates_payment_and_order_activity_logs(): void
    {
        $data = $this->preparePendingOrder();

        $service = $this->paymentService();

        $paymentResult = $service->createPayment(
            $data['order']
        );

        $payment = $service->handleCallback([
            'reference' =>
                $paymentResult['payment']->gateway_reference,

            'gateway_status' =>
                'success',

            'transaction_id' =>
                'AUDIT-TXN-001',

            'amount' =>
                220,

            'currency' =>
                'IRT',
        ]);

        $paymentLog = ActivityLog::query()
            ->where(
                'action',
                'payments.paid'
            )
            ->where(
                'properties->payment->id',
                $payment->id
            )
            ->latest('id')
            ->first();

        $this->assertNotNull(
            $paymentLog
        );

        $this->assertSame(
            'payments.paid',
            $paymentLog->action
        );

        $this->assertSame(
            'Payment completed successfully.',
            $paymentLog->description
        );

        $this->assertSame(
            Payment::class,
            $paymentLog->properties['subject']['type']
        );

        $this->assertSame(
            $payment->id,
            $paymentLog->properties['subject']['id']
        );

        $this->assertSame(
            $payment->id,
            $paymentLog->properties['payment']['id']
        );

        $this->assertSame(
            $data['order']->id,
            $paymentLog->properties['payment']['order_id']
        );

        $this->assertSame(
            PaymentStatus::PAID->value,
            $paymentLog->properties['payment']['status']
        );

        $this->assertSame(
            'AUDIT-TXN-001',
            $paymentLog->properties['transaction_id']
        );

        $this->assertNotNull(
            $paymentLog->properties['paid_at']
        );

        $orderLog = ActivityLog::query()
            ->where(
                'action',
                'orders.status_changed'
            )
            ->where(
                'properties->order->id',
                $data['order']->id
            )
            ->where(
                'properties->to_status',
                OrderStatus::PAID->value
            )
            ->latest('id')
            ->first();

        $this->assertNotNull(
            $orderLog
        );

        $this->assertSame(
            OrderStatus::PENDING_PAYMENT->value,
            $orderLog->properties['from_status']
        );

        $this->assertSame(
            OrderStatus::PAID->value,
            $orderLog->properties['to_status']
        );

        $this->assertNull(
            $orderLog->properties['changed_by']
        );

        $this->assertSame(
            'Payment verified successfully by gateway.',
            $orderLog->properties['note']
        );
    }

    public function test_failed_gateway_verification_marks_payment_failed_and_releases_reserved_inventory(): void
    {
        $data = $this->preparePendingOrder();

        $service = $this->paymentService();

        $paymentResult = $service->createPayment(
            $data['order']
        );

        $payment = $service->handleCallback([
            'reference' =>
                $paymentResult['payment']->gateway_reference,

            'gateway_status' =>
                'failed',
        ]);

        $this->assertSame(
            PaymentStatus::FAILED,
            $payment->status
        );

        $this->assertNull(
            $payment->paid_at
        );

        $this->assertSame(
            OrderStatus::PENDING_PAYMENT,
            $payment->order->status
        );

        $this->assertSame(
            10,
            $data['inventory']->fresh()->quantity
        );

        $this->assertSame(
            0,
            $data['inventory']->fresh()->reserved_quantity
        );

        $this->assertDatabaseHas('inventory_movements', [
            'order_id' =>
                $data['order']->id,

            'inventory_id' =>
                $data['inventory']->id,

            'type' =>
                InventoryMovementType::RELEASE->value,

            'quantity_delta' =>
                0,

            'reserved_delta' =>
                -2,
        ]);
    }

    public function test_failed_payment_creates_payment_failed_activity_log(): void
    {
        $data = $this->preparePendingOrder();

        $service = $this->paymentService();

        $paymentResult = $service->createPayment(
            $data['order']
        );

        $payment = $service->handleCallback([
            'reference' =>
                $paymentResult['payment']->gateway_reference,

            'gateway_status' =>
                'failed',
        ]);

        $log = ActivityLog::query()
            ->where(
                'action',
                'payments.failed'
            )
            ->where(
                'properties->payment->id',
                $payment->id
            )
            ->latest('id')
            ->first();

        $this->assertNotNull(
            $log
        );

        $this->assertSame(
            'payments.failed',
            $log->action
        );

        $this->assertSame(
            'Payment failed.',
            $log->description
        );

        $this->assertSame(
            Payment::class,
            $log->properties['subject']['type']
        );

        $this->assertSame(
            $payment->id,
            $log->properties['subject']['id']
        );

        $this->assertSame(
            $data['order']->id,
            $log->properties['payment']['order_id']
        );

        $this->assertSame(
            PaymentStatus::FAILED->value,
            $log->properties['payment']['status']
        );
    }

    public function test_expired_pending_payment_releases_reserved_inventory_and_cancels_order(): void
    {
        $data = $this->preparePendingOrder();

        $service = $this->paymentService();

        $paymentResult = $service->createPayment(
            $data['order']
        );

        $payment = $paymentResult['payment'];

        $this->assertSame(
            PaymentStatus::PENDING,
            $payment->status
        );

        $this->assertSame(
            OrderStatus::PENDING_PAYMENT,
            $data['order']->fresh()->status
        );

        $this->assertSame(
            10,
            $data['inventory']->fresh()->quantity
        );

        $this->assertSame(
            2,
            $data['inventory']->fresh()->reserved_quantity
        );

        $expiredAt = now()->subMinutes(31);

        $data['order']->forceFill([
            'created_at' => $expiredAt,
        ])->saveQuietly();

        $this->assertTrue(
            $data['order']->fresh()->created_at->lte(now()->subMinutes(30))
        );

        $expirationService = app(
            PaymentExpirationService::class
        );

        $expiredCount = $expirationService->expirePendingPayments(
            30
        );

        $this->assertSame(
            1,
            $expiredCount
        );

        $this->assertSame(
            PaymentStatus::FAILED,
            $payment->fresh()->status
        );

        $this->assertSame(
            OrderStatus::CANCELLED,
            $data['order']->fresh()->status
        );

        $this->assertSame(
            10,
            $data['inventory']->fresh()->quantity
        );

        $this->assertSame(
            0,
            $data['inventory']->fresh()->reserved_quantity
        );

        $this->assertDatabaseHas('inventory_movements', [
            'order_id' =>
                $data['order']->id,

            'inventory_id' =>
                $data['inventory']->id,

            'type' =>
                InventoryMovementType::RELEASE->value,

            'quantity_delta' =>
                0,

            'reserved_delta' =>
                -2,
        ]);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' =>
                $data['order']->id,

            'from_status' =>
                OrderStatus::PENDING_PAYMENT->value,

            'to_status' =>
                OrderStatus::CANCELLED->value,

            'note' =>
                'Order cancelled automatically because payment expired.',
        ]);
    }

    public function test_expired_payment_creates_payment_and_order_activity_logs(): void
    {
        $data = $this->preparePendingOrder();

        $service = $this->paymentService();

        $paymentResult = $service->createPayment(
            $data['order']
        );

        $payment = $paymentResult['payment'];

        $expiredAt = now()->subMinutes(31);

        $data['order']->forceFill([
            'created_at' => $expiredAt,
        ])->saveQuietly();

        $expirationService = app(
            PaymentExpirationService::class
        );

        $result = $expirationService->expirePayment(
            $payment->id
        );

        $this->assertTrue(
            $result
        );

        $paymentLog = ActivityLog::query()
            ->where(
                'action',
                'payments.expired'
            )
            ->where(
                'properties->payment->id',
                $payment->id
            )
            ->latest('id')
            ->first();

        $this->assertNotNull(
            $paymentLog
        );

        $this->assertSame(
            'payments.expired',
            $paymentLog->action
        );

        $this->assertSame(
            'Payment expired automatically.',
            $paymentLog->description
        );

        $this->assertSame(
            Payment::class,
            $paymentLog->properties['subject']['type']
        );

        $this->assertSame(
            $payment->id,
            $paymentLog->properties['subject']['id']
        );

        $this->assertSame(
            PaymentStatus::PENDING->value,
            $paymentLog->properties['payment']['previous_status']
        );

        $this->assertSame(
            PaymentStatus::FAILED->value,
            $paymentLog->properties['payment']['status']
        );

        $this->assertSame(
            'payment_timeout',
            $paymentLog->properties['reason']
        );

        $this->assertNotEmpty(
            $paymentLog->properties['expired_at']
        );

        $orderLog = ActivityLog::query()
            ->where(
                'action',
                'orders.status_changed'
            )
            ->where(
                'properties->order->id',
                $data['order']->id
            )
            ->where(
                'properties->to_status',
                OrderStatus::CANCELLED->value
            )
            ->latest('id')
            ->first();

        $this->assertNotNull(
            $orderLog
        );

        $this->assertSame(
            OrderStatus::PENDING_PAYMENT->value,
            $orderLog->properties['from_status']
        );

        $this->assertSame(
            OrderStatus::CANCELLED->value,
            $orderLog->properties['to_status']
        );

        $this->assertNull(
            $orderLog->properties['changed_by']
        );

        $this->assertSame(
            'payment_timeout',
            $orderLog->properties['reason']
        );

        $this->assertSame(
            $payment->id,
            $orderLog->properties['payment_id']
        );
    }

    public function test_wrong_amount_is_rejected(): void
    {
        $data = $this->preparePendingOrder();

        $service = $this->paymentService();

        $paymentResult = $service->createPayment(
            $data['order']
        );

        $this->expectException(
            ValidationException::class
        );

        $service->handleCallback([
            'reference' =>
                $paymentResult['payment']->gateway_reference,

            'gateway_status' =>
                'success',

            'transaction_id' =>
                'FAKE-TXN-002',

            'amount' =>
                999,

            'currency' =>
                'IRT',
        ]);
    }

    public function test_wrong_currency_is_rejected(): void
    {
        $data = $this->preparePendingOrder();

        $service = $this->paymentService();

        $paymentResult = $service->createPayment(
            $data['order']
        );

        $this->expectException(
            ValidationException::class
        );

        $service->handleCallback([
            'reference' =>
                $paymentResult['payment']->gateway_reference,

            'gateway_status' =>
                'success',

            'transaction_id' =>
                'FAKE-TXN-CURRENCY-001',

            'amount' =>
                220,

            'currency' =>
                'EUR',
        ]);
    }

    public function test_blank_transaction_id_is_rejected(): void
    {
        $data = $this->preparePendingOrder();

        $service = $this->paymentService();

        $paymentResult = $service->createPayment(
            $data['order']
        );

        $this->expectException(
            ValidationException::class
        );

        $service->handleCallback([
            'reference' =>
                $paymentResult['payment']->gateway_reference,

            'gateway_status' =>
                'success',

            'transaction_id' =>
                '   ',

            'amount' =>
                220,

            'currency' =>
                'IRT',
        ]);
    }

    public function test_missing_transaction_id_is_rejected_without_payment_side_effects(): void
    {
        $data = $this->preparePendingOrder();

        $gateway = new class implements PaymentGatewayInterface {
            public function requestPayment(
                Payment $payment
            ): array {
                return [
                    'reference' =>
                        'TEST-MISSING-TXN-REF-' . $payment->id,

                    'payment_url' =>
                        'http://fake-gateway.test/pay/' .
                        $payment->id,
                ];
            }

            public function extractReference(
                array $callbackData
            ): string {
                return (string) (
                    $callbackData['reference']
                    ?? ''
                );
            }

            public function verifyPayment(
                Payment $payment,
                array $callbackData
            ): array {
                return [
                    'success' =>
                        true,

                    'amount' =>
                        $payment->amount,

                    'currency' =>
                        $payment->currency,
                ];
            }

            public function refundPayment(
                Payment $payment
            ): array {
                return [
                    'success' =>
                        true,

                    'refund_transaction_id' =>
                        'TEST-REFUND-TXN',
                ];
            }
        };

        $service = new PaymentService(
            $gateway,
            app(ActivityLogService::class)
        );

        $paymentResult = $service->createPayment(
            $data['order']
        );

        $paymentId = $paymentResult['payment']->id;

        try {
            $service->handleCallback([
                'reference' =>
                    $paymentResult['payment']->gateway_reference,

                'gateway_status' =>
                    'success',
            ]);

            $this->fail(
                'Expected ValidationException was not thrown.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'payment',
                $exception->errors()
            );

            $this->assertSame(
                'The gateway returned an invalid transaction ID.',
                $exception->errors()['payment'][0]
            );
        }

        $payment = Payment::find(
            $paymentId
        );

        $this->assertNotNull(
            $payment
        );

        $this->assertSame(
            PaymentStatus::PENDING,
            $payment->status
        );

        $this->assertNull(
            $payment->transaction_id
        );

        $this->assertNull(
            $payment->paid_at
        );

        $this->assertSame(
            OrderStatus::PENDING_PAYMENT,
            $data['order']->fresh()->status
        );

        $this->assertSame(
            10,
            $data['inventory']->fresh()->quantity
        );

        $this->assertSame(
            2,
            $data['inventory']->fresh()->reserved_quantity
        );

        $this->assertSame(
            0,
            InventoryMovement::query()
                ->where(
                    'order_id',
                    $data['order']->id
                )
                ->where(
                    'type',
                    InventoryMovementType::SALE
                )
                ->count()
        );

        $this->assertSame(
            0,
            OrderStatusHistory::query()
                ->where(
                    'order_id',
                    $data['order']->id
                )
                ->where(
                    'to_status',
                    OrderStatus::PAID
                )
                ->count()
        );

        $this->assertSame(
            0,
            ActivityLog::query()
                ->where(
                    'action',
                    'payments.paid'
                )
                ->where(
                    'properties->payment->id',
                    $paymentId
                )
                ->count()
        );

        $this->assertSame(
            0,
            ActivityLog::query()
                ->where(
                    'action',
                    'orders.status_changed'
                )
                ->where(
                    'properties->order->id',
                    $data['order']->id
                )
                ->where(
                    'properties->to_status',
                    OrderStatus::PAID->value
                )
                ->count()
        );
    }

    public function test_successful_payment_fails_when_order_inventory_is_missing_and_rolls_back(): void
    {
        $data = $this->preparePendingOrder();

        $missingInventoryVariant = ProductVariant::factory()->create([
            'product_id' =>
                $data['product']->id,

            'status' =>
                VariantStatus::ACTIVE,

            'price' =>
                100,
        ]);

        $orderItem = $data['order']
            ->items()
            ->first();

        $orderItem->update([
            'product_variant_id' =>
                $missingInventoryVariant->id,
        ]);

        $service = $this->paymentService();

        $paymentResult = $service->createPayment(
            $data['order']
        );

        $paymentId = $paymentResult['payment']->id;

        try {
            $service->handleCallback([
                'reference' =>
                    $paymentResult['payment']->gateway_reference,

                'gateway_status' =>
                    'success',

                'transaction_id' =>
                    'FAKE-TXN-MISSING-INVENTORY',

                'amount' =>
                    220,

                'currency' =>
                    'IRT',
            ]);

            $this->fail(
                'Expected ValidationException was not thrown.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'payment',
                $exception->errors()
            );

            $this->assertSame(
                'The order inventory could not be found.',
                $exception->errors()['payment'][0]
            );
        }

        $payment = Payment::find(
            $paymentId
        );

        $this->assertNotNull(
            $payment
        );

        $this->assertSame(
            PaymentStatus::PENDING,
            $payment->status
        );

        $this->assertNull(
            $payment->transaction_id
        );

        $this->assertNull(
            $payment->paid_at
        );

        $this->assertSame(
            OrderStatus::PENDING_PAYMENT,
            $data['order']->fresh()->status
        );

        $this->assertSame(
            10,
            $data['inventory']->fresh()->quantity
        );

        $this->assertSame(
            2,
            $data['inventory']->fresh()->reserved_quantity
        );

        $this->assertSame(
            0,
            InventoryMovement::query()
                ->where(
                    'order_id',
                    $data['order']->id
                )
                ->where(
                    'type',
                    InventoryMovementType::SALE
                )
                ->count()
        );

        $this->assertSame(
            0,
            OrderStatusHistory::query()
                ->where(
                    'order_id',
                    $data['order']->id
                )
                ->where(
                    'to_status',
                    OrderStatus::PAID
                )
                ->count()
        );

        $this->assertSame(
            0,
            ActivityLog::query()
                ->where(
                    'action',
                    'payments.paid'
                )
                ->where(
                    'properties->payment->id',
                    $paymentId
                )
                ->count()
        );

        $this->assertSame(
            0,
            ActivityLog::query()
                ->where(
                    'action',
                    'orders.status_changed'
                )
                ->where(
                    'properties->order->id',
                    $data['order']->id
                )
                ->where(
                    'properties->to_status',
                    OrderStatus::PAID->value
                )
                ->count()
        );
    }

    public function test_duplicate_successful_callback_is_idempotent(): void
    {
        $data = $this->preparePendingOrder();

        $service = $this->paymentService();

        $paymentResult = $service->createPayment(
            $data['order']
        );

        $callback = [
            'reference' =>
                $paymentResult['payment']->gateway_reference,

            'gateway_status' =>
                'success',

            'transaction_id' =>
                'FAKE-TXN-003',

            'amount' =>
                220,

            'currency' =>
                'IRT',
        ];

        $first = $service->handleCallback(
            $callback
        );

        $second = $service->handleCallback(
            $callback
        );

        $this->assertSame(
            PaymentStatus::PAID,
            $first->status
        );

        $this->assertSame(
            PaymentStatus::PAID,
            $second->status
        );

        $this->assertSame(
            1,
            Payment::query()->count()
        );

        $this->assertSame(
            1,
            InventoryMovement::query()
                ->where(
                    'order_id',
                    $data['order']->id
                )
                ->where(
                    'type',
                    InventoryMovementType::SALE
                )
                ->count()
        );

        $this->assertSame(
            1,
            OrderStatusHistory::query()
                ->where(
                    'order_id',
                    $data['order']->id
                )
                ->where(
                    'to_status',
                    OrderStatus::PAID
                )
                ->count()
        );

        $this->assertSame(
            8,
            $data['inventory']->fresh()->quantity
        );

        $this->assertSame(
            0,
            $data['inventory']->fresh()->reserved_quantity
        );

        $this->assertSame(
            1,
            ActivityLog::query()
                ->where(
                    'action',
                    'payments.paid'
                )
                ->where(
                    'properties->payment->id',
                    $first->id
                )
                ->count()
        );

        $this->assertSame(
            1,
            ActivityLog::query()
                ->where(
                    'action',
                    'orders.status_changed'
                )
                ->where(
                    'properties->order->id',
                    $data['order']->id
                )
                ->where(
                    'properties->to_status',
                    OrderStatus::PAID->value
                )
                ->count()
        );
    }

    public function test_interleaved_successful_callbacks_process_payment_only_once(): void
    {
        $data = $this->preparePendingOrder();

        $gateway = new class implements PaymentGatewayInterface {
            public ?PaymentService $service = null;

            public bool $triggeredSecondCallback = false;

            public int $verifyCalls = 0;

            public function requestPayment(
                Payment $payment
            ): array {
                return [
                    'reference' =>
                        'INTERLEAVED-REF-' . $payment->id,

                    'payment_url' =>
                        'http://fake-gateway.test/pay/' .
                        $payment->id,
                ];
            }

            public function extractReference(
                array $callbackData
            ): string {
                return (string) (
                    $callbackData['reference']
                    ?? ''
                );
            }

            public function verifyPayment(
                Payment $payment,
                array $callbackData
            ): array {
                $this->verifyCalls++;

                if (
                    ! $this->triggeredSecondCallback &&
                    $this->service !== null
                ) {
                    $this->triggeredSecondCallback = true;

                    $this->service->handleCallback(
                        $callbackData
                    );
                }

                return [
                    'success' =>
                        true,

                    'transaction_id' =>
                        'INTERLEAVED-TXN-001',

                    'amount' =>
                        $payment->amount,

                    'currency' =>
                        $payment->currency,
                ];
            }

            public function refundPayment(
                Payment $payment
            ): array {
                return [
                    'success' =>
                        true,

                    'refund_transaction_id' =>
                        'INTERLEAVED-REFUND-001',
                ];
            }
        };

        $service = new PaymentService(
            $gateway,
            app(ActivityLogService::class)
        );

        $gateway->service = $service;

        $paymentResult = $service->createPayment(
            $data['order']
        );

        $callback = [
            'reference' =>
                $paymentResult['payment']->gateway_reference,

            'gateway_status' =>
                'success',

            'transaction_id' =>
                'INTERLEAVED-TXN-001',

            'amount' =>
                $paymentResult['payment']->amount,

            'currency' =>
                $paymentResult['payment']->currency,
        ];

        $result = $service->handleCallback(
            $callback
        );

        $this->assertSame(
            2,
            $gateway->verifyCalls
        );

        $payment = $result->fresh();

        $this->assertSame(
            PaymentStatus::PAID,
            $payment->status
        );

        $this->assertSame(
            'INTERLEAVED-TXN-001',
            $payment->transaction_id
        );

        $this->assertNotNull(
            $payment->paid_at
        );

        $this->assertSame(
            OrderStatus::PAID,
            $data['order']->fresh()->status
        );

        $this->assertSame(
            8,
            $data['inventory']->fresh()->quantity
        );

        $this->assertSame(
            0,
            $data['inventory']->fresh()->reserved_quantity
        );

        $this->assertSame(
            1,
            InventoryMovement::query()
                ->where(
                    'order_id',
                    $data['order']->id
                )
                ->where(
                    'type',
                    InventoryMovementType::SALE
                )
                ->count()
        );

        $this->assertSame(
            1,
            OrderStatusHistory::query()
                ->where(
                    'order_id',
                    $data['order']->id
                )
                ->where(
                    'to_status',
                    OrderStatus::PAID
                )
                ->count()
        );

        $this->assertSame(
            1,
            ActivityLog::query()
                ->where(
                    'action',
                    'payments.paid'
                )
                ->where(
                    'properties->payment->id',
                    $payment->id
                )
                ->count()
        );

        $this->assertSame(
            1,
            ActivityLog::query()
                ->where(
                    'action',
                    'orders.status_changed'
                )
                ->where(
                    'properties->order->id',
                    $data['order']->id
                )
                ->where(
                    'properties->to_status',
                    OrderStatus::PAID->value
                )
                ->count()
        );
    }

    public function test_successful_callback_cannot_reopen_expired_or_cancelled_order(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Expired payment
        |--------------------------------------------------------------------------
        */

        $expiredData = $this->preparePendingOrder();

        $service = $this->paymentService();

        $expiredPaymentResult = $service->createPayment(
            $expiredData['order']
        );

        $expiredPayment = $expiredPaymentResult['payment'];

        $expiredAt = now()->subMinutes(31);

        $expiredData['order']->forceFill([
            'created_at' => $expiredAt,
        ])->saveQuietly();

        $expirationService = app(
            PaymentExpirationService::class
        );

        $this->assertTrue(
            $expirationService->expirePayment(
                $expiredPayment->id
            )
        );

        $this->assertSame(
            PaymentStatus::FAILED,
            $expiredPayment->fresh()->status
        );

        $this->assertSame(
            OrderStatus::CANCELLED,
            $expiredData['order']->fresh()->status
        );

        try {
            $service->handleCallback([
                'reference' =>
                    $expiredPayment->gateway_reference,

                'gateway_status' =>
                    'success',

                'transaction_id' =>
                    'AFTER-EXPIRATION-TXN',

                'amount' =>
                    220,

                'currency' =>
                    'IRT',
            ]);

            $this->fail(
                'Expected ValidationException for expired payment was not thrown.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'payment',
                $exception->errors()
            );

            $this->assertSame(
                'This payment is no longer payable.',
                $exception->errors()['payment'][0]
            );
        }

        $this->assertSame(
            PaymentStatus::FAILED,
            $expiredPayment->fresh()->status
        );

        $this->assertSame(
            OrderStatus::CANCELLED,
            $expiredData['order']->fresh()->status
        );

        $this->assertSame(
            10,
            $expiredData['inventory']->fresh()->quantity
        );

        $this->assertSame(
            0,
            $expiredData['inventory']->fresh()->reserved_quantity
        );

        $this->assertSame(
            0,
            InventoryMovement::query()
                ->where(
                    'order_id',
                    $expiredData['order']->id
                )
                ->where(
                    'type',
                    InventoryMovementType::SALE
                )
                ->count()
        );

        $this->assertSame(
            0,
            ActivityLog::query()
                ->where(
                    'action',
                    'payments.paid'
                )
                ->where(
                    'properties->payment->id',
                    $expiredPayment->id
                )
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Cancelled order with pending payment
        |--------------------------------------------------------------------------
        */

        $cancelledData = $this->preparePendingOrder();

        $cancelledPaymentResult = $service->createPayment(
            $cancelledData['order']
        );

        $cancelledPayment = $cancelledPaymentResult['payment'];

        $orderStatusService = app(
            \App\Services\OrderStatusService::class
        );

        $orderStatusService->changeStatus(
            $cancelledData['order'],
            OrderStatus::CANCELLED
        );

        $this->assertSame(
            PaymentStatus::PENDING,
            $cancelledPayment->fresh()->status
        );

        $this->assertSame(
            OrderStatus::CANCELLED,
            $cancelledData['order']->fresh()->status
        );

        try {
            $service->handleCallback([
                'reference' =>
                    $cancelledPayment->gateway_reference,

                'gateway_status' =>
                    'success',

                'transaction_id' =>
                    'AFTER-CANCELLATION-TXN',

                'amount' =>
                    220,

                'currency' =>
                    'IRT',
            ]);

            $this->fail(
                'Expected ValidationException for cancelled order was not thrown.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'order',
                $exception->errors()
            );

            $this->assertSame(
                'This order is no longer awaiting payment.',
                $exception->errors()['order'][0]
            );
        }

        $this->assertSame(
            PaymentStatus::PENDING,
            $cancelledPayment->fresh()->status
        );

        $this->assertSame(
            OrderStatus::CANCELLED,
            $cancelledData['order']->fresh()->status
        );

        $this->assertSame(
            10,
            $cancelledData['inventory']->fresh()->quantity
        );

        $this->assertSame(
            0,
            $cancelledData['inventory']->fresh()->reserved_quantity
        );

        $this->assertSame(
            0,
            InventoryMovement::query()
                ->where(
                    'order_id',
                    $cancelledData['order']->id
                )
                ->where(
                    'type',
                    InventoryMovementType::SALE
                )
                ->count()
        );

        $this->assertSame(
            0,
            ActivityLog::query()
                ->where(
                    'action',
                    'payments.paid'
                )
                ->where(
                    'properties->payment->id',
                    $cancelledPayment->id
                )
                ->count()
        );
    }

    public function test_unknown_gateway_reference_is_rejected(): void
    {
        $service = $this->paymentService();

        $this->expectException(
            ValidationException::class
        );

        $service->handleCallback([
            'reference' =>
                'UNKNOWN-REFERENCE',

            'gateway_status' =>
                'success',

            'transaction_id' =>
                'FAKE-TXN-004',

            'amount' =>
                220,

            'currency' =>
                'IRT',
        ]);
    }

    public function test_paid_payment_can_be_refunded(): void
    {
        $data = $this->preparePendingOrder();

        $service = $this->paymentService();

        $paymentResult = $service->createPayment(
            $data['order']
        );

        $paidPayment = $service->handleCallback([
            'reference' =>
                $paymentResult['payment']->gateway_reference,

            'gateway_status' =>
                'success',

            'transaction_id' =>
                'REFUND-TXN-001',

            'amount' =>
                220,

            'currency' =>
                'IRT',
        ]);

        $this->assertSame(
            PaymentStatus::PAID,
            $paidPayment->status
        );

        $refundedPayment = $service->refundPayment(
            $paidPayment
        );

        $this->assertSame(
            PaymentStatus::REFUNDED,
            $refundedPayment->status
        );

        $this->assertSame(
            'REFUND-TXN-001',
            $refundedPayment->transaction_id
        );

        $this->assertSame(
            'refunded',
            data_get(
                $refundedPayment->gateway_response,
                'refund.status'
            )
        );

        $this->assertNotEmpty(
            data_get(
                $refundedPayment->gateway_response,
                'refund.transaction_id'
            )
        );
    }

    public function test_refund_creates_activity_log_without_raw_gateway_response(): void
    {
        $data = $this->preparePendingOrder();

        $service = $this->paymentService();

        $paymentResult = $service->createPayment(
            $data['order']
        );

        $paidPayment = $service->handleCallback([
            'reference' =>
                $paymentResult['payment']->gateway_reference,

            'gateway_status' =>
                'success',

            'transaction_id' =>
                'REFUND-AUDIT-001',

            'amount' =>
                220,

            'currency' =>
                'IRT',
        ]);

        $refundedPayment = $service->refundPayment(
            $paidPayment
        );

        $log = ActivityLog::query()
            ->where(
                'action',
                'payments.refunded'
            )
            ->where(
                'properties->payment->id',
                $refundedPayment->id
            )
            ->latest('id')
            ->first();

        $this->assertNotNull(
            $log
        );

        $this->assertSame(
            'payments.refunded',
            $log->action
        );

        $this->assertSame(
            'Payment refunded successfully.',
            $log->description
        );

        $this->assertSame(
            Payment::class,
            $log->properties['subject']['type']
        );

        $this->assertSame(
            $refundedPayment->id,
            $log->properties['subject']['id']
        );

        $this->assertSame(
            $data['order']->id,
            $log->properties['payment']['order_id']
        );

        $this->assertSame(
            PaymentStatus::REFUNDED->value,
            $log->properties['payment']['status']
        );

        $this->assertSame(
            'REFUND-AUDIT-001',
            $log->properties['original_transaction_id']
        );

        $this->assertNotEmpty(
            $log->properties['refund_transaction_id']
        );

        $this->assertNotEmpty(
            $log->properties['refunded_at']
        );

        $this->assertArrayNotHasKey(
            'gateway_response',
            $log->properties
        );
    }

    public function test_refunding_an_already_refunded_payment_is_idempotent(): void
    {
        $data = $this->preparePendingOrder();

        $service = $this->paymentService();

        $paymentResult = $service->createPayment(
            $data['order']
        );

        $paidPayment = $service->handleCallback([
            'reference' =>
                $paymentResult['payment']->gateway_reference,

            'gateway_status' =>
                'success',

            'transaction_id' =>
                'REFUND-IDEMPOTENT-001',

            'amount' =>
                220,

            'currency' =>
                'IRT',
        ]);

        $firstRefund = $service->refundPayment(
            $paidPayment
        );

        $secondRefund = $service->refundPayment(
            $firstRefund
        );

        $this->assertSame(
            PaymentStatus::REFUNDED,
            $firstRefund->status
        );

        $this->assertSame(
            PaymentStatus::REFUNDED,
            $secondRefund->status
        );

        $this->assertSame(
            1,
            ActivityLog::query()
                ->where(
                    'action',
                    'payments.refunded'
                )
                ->where(
                    'properties->payment->id',
                    $paidPayment->id
                )
                ->count()
        );
    }

    public function test_failed_refund_from_gateway_does_not_change_payment_state(): void
    {
        $data = $this->preparePendingOrder();

        $gateway = new class implements PaymentGatewayInterface {
            public function requestPayment(
                Payment $payment
            ): array {
                return [
                    'reference' =>
                        'REFUND-FAIL-REF-' . $payment->id,

                    'payment_url' =>
                        'http://fake-gateway.test/pay/' .
                        $payment->id,
                ];
            }

            public function extractReference(
                array $callbackData
            ): string {
                return (string) (
                    $callbackData['reference']
                    ?? ''
                );
            }

            public function verifyPayment(
                Payment $payment,
                array $callbackData
            ): array {
                return [
                    'success' =>
                        true,

                    'transaction_id' =>
                        'REFUND-FAIL-PAY-TXN',

                    'amount' =>
                        $payment->amount,

                    'currency' =>
                        $payment->currency,
                ];
            }

            public function refundPayment(
                Payment $payment
            ): array {
                return [
                    'success' =>
                        false,

                    'gateway_response' => [
                        'status' =>
                            'failed',

                        'message' =>
                            'Refund rejected by gateway.',
                    ],
                ];
            }
        };

        $service = new PaymentService(
            $gateway,
            app(ActivityLogService::class)
        );

        $paymentResult = $service->createPayment(
            $data['order']
        );

        $paidPayment = $service->handleCallback([
            'reference' =>
                $paymentResult['payment']->gateway_reference,

            'gateway_status' =>
                'success',

            'transaction_id' =>
                'REFUND-FAIL-PAY-TXN',

            'amount' =>
                220,

            'currency' =>
                'IRT',
        ]);

        $this->assertSame(
            PaymentStatus::PAID,
            $paidPayment->status
        );

        try {
            $service->refundPayment(
                $paidPayment
            );

            $this->fail(
                'Expected PaymentRefundException was not thrown.'
            );
        } catch (PaymentRefundException $exception) {
            $this->assertSame(
                'The payment gateway could not refund the payment.',
                $exception->getMessage()
            );
        }

        $freshPayment = $paidPayment->fresh();

        $this->assertSame(
            PaymentStatus::PAID,
            $freshPayment->status
        );

        $this->assertSame(
            'REFUND-FAIL-PAY-TXN',
            $freshPayment->transaction_id
        );

        $this->assertNotNull(
            $freshPayment->paid_at
        );

        $this->assertSame(
            OrderStatus::PAID,
            $data['order']->fresh()->status
        );

        $this->assertSame(
            8,
            $data['inventory']->fresh()->quantity
        );

        $this->assertSame(
            0,
            $data['inventory']->fresh()->reserved_quantity
        );

        $this->assertSame(
            0,
            ActivityLog::query()
                ->where(
                    'action',
                    'payments.refunded'
                )
                ->where(
                    'properties->payment->id',
                    $paidPayment->id
                )
                ->count()
        );
    }

    public function test_pending_payment_cannot_be_refunded(): void
    {
        $data = $this->preparePendingOrder();

        $service = $this->paymentService();

        $paymentResult = $service->createPayment(
            $data['order']
        );

        $this->expectException(
            ValidationException::class
        );

        $service->refundPayment(
            $paymentResult['payment']
        );

        $this->assertSame(
            PaymentStatus::PENDING,
            $paymentResult['payment']->fresh()->status
        );

        $this->assertDatabaseMissing(
            'activity_logs',
            [
                'action' =>
                    'payments.refunded',
            ]
        );
    }

    public function test_failed_payment_cannot_be_refunded(): void
    {
        $data = $this->preparePendingOrder();

        $service = $this->paymentService();

        $paymentResult = $service->createPayment(
            $data['order']
        );

        $failedPayment = $service->handleCallback([
            'reference' =>
                $paymentResult['payment']->gateway_reference,

            'gateway_status' =>
                'failed',
        ]);

        $this->assertSame(
            PaymentStatus::FAILED,
            $failedPayment->status
        );

        $this->expectException(
            ValidationException::class
        );

        $service->refundPayment(
            $failedPayment
        );
    }

    private function paymentService(): PaymentService
    {
        return new PaymentService(
            new FakePaymentGateway(),
            app(ActivityLogService::class)
        );
    }

    private function preparePendingOrder(): array
    {
        $user = User::factory()->create();

        $cart = Cart::create([
            'user_id' =>
                $user->id,

            'status' =>
                CartStatus::ACTIVE,
        ]);

        $product = Product::factory()->create([
            'status' =>
                ProductStatus::ACTIVE,

            'price' =>
                100,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' =>
                $product->id,

            'status' =>
                VariantStatus::ACTIVE,

            'price' =>
                100,
        ]);

        $inventory = Inventory::create([
            'product_variant_id' =>
                $variant->id,

            'quantity' =>
                10,

            'reserved_quantity' =>
                0,

            'low_stock_threshold' =>
                2,
        ]);

        CartItem::create([
            'cart_id' =>
                $cart->id,

            'product_variant_id' =>
                $variant->id,

            'quantity' =>
                2,

            'price_snapshot' =>
                100,
        ]);

        $shippingMethod = ShippingMethod::create([
            'name' =>
                'Test Express',

            'description' =>
                'Payment test shipping',

            'price' =>
                20,

            'estimated_days' =>
                2,

            'is_active' =>
                true,

            'sort_order' =>
                1,
        ]);

        $checkout = CheckoutSession::create([
            'user_id' =>
                $user->id,

            'cart_id' =>
                $cart->id,

            'status' =>
                CheckoutSessionStatus::ACTIVE,

            'shipping_address' => [
                'first_name' =>
                    'John',

                'last_name' =>
                    'Doe',

                'phone' =>
                    '+49123456789',

                'country' =>
                    'Germany',

                'state' =>
                    'Hesse',

                'city' =>
                    'Wiesbaden',

                'address_line_1' =>
                    'Main Street 10',

                'address_line_2' =>
                    null,

                'postal_code' =>
                    '65183',
            ],

            'shipping_method_id' =>
                $shippingMethod->id,
        ]);

        $checkoutService = app(
            CheckoutService::class
        );

        $order = $checkoutService->placeOrder(
            $user,
            $checkout
        );

        return [
            'user' =>
                $user,

            'cart' =>
                $cart,

            'product' =>
                $product,

            'variant' =>
                $variant,

            'inventory' =>
                $inventory,

            'checkout' =>
                $checkout,

            'order' =>
                $order,
        ];
    }
    public function test_failed_payment_can_be_retried_on_same_order(): void
    {
        $data = $this->preparePendingOrder();
        $service = $this->paymentService();
        $first = $service->createPayment($data['order'])['payment'];
        $service->handleCallback([
            'reference' => $first->gateway_reference,
            'gateway_status' => 'failed',
        ]);
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $data['order']->fresh()->status);
        $this->assertSame(0, $data['inventory']->fresh()->reserved_quantity);
        $second = $service->createPayment($data['order']->fresh())['payment'];
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, $data['inventory']->fresh()->reserved_quantity);
        $this->assertSame(2, Payment::query()->where('order_id', $data['order']->id)->count());
        $paid = $service->handleCallback([
            'reference' => $second->gateway_reference,
            'gateway_status' => 'success',
            'transaction_id' => 'RETRY-PAID-001',
            'amount' => 220,
            'currency' => 'IRT',
        ]);
        $this->assertSame(PaymentStatus::PAID, $paid->status);
        $this->assertSame(OrderStatus::PAID, $data['order']->fresh()->status);
        $this->assertSame(8, $data['inventory']->fresh()->quantity);
        $this->assertSame(0, $data['inventory']->fresh()->reserved_quantity);
    }

    public function test_retry_cannot_extend_order_deadline(): void
    {
        $data = $this->preparePendingOrder();
        $service = $this->paymentService();
        $first = $service->createPayment($data['order'])['payment'];
        $service->handleCallback([
            'reference' => $first->gateway_reference,
            'gateway_status' => 'failed',
        ]);
        $data['order']->forceFill([
            'created_at' => now()->subMinutes(31),
        ])->saveQuietly();
        $this->expectException(ValidationException::class);
        $service->createPayment($data['order']->fresh());
    }
    public function test_order_with_no_payment_expires_without_stock_leak(): void
    {
        $data = $this->preparePendingOrder();
        $data['order']->forceFill(['created_at' => now()->subMinutes(31)])->saveQuietly();
        $service = app(PaymentExpirationService::class);
        $this->assertSame(1, $service->expirePendingPayments(30));
        $this->assertSame(OrderStatus::CANCELLED, $data['order']->fresh()->status);
        $this->assertSame(0, $data['inventory']->fresh()->reserved_quantity);
        $this->assertSame(0, Payment::query()->where('order_id', $data['order']->id)->count());
        $this->assertSame(0, $service->expirePendingPayments(30));
    }

    public function test_order_with_only_failed_attempt_still_expires(): void
    {
        $data = $this->preparePendingOrder();
        $service = $this->paymentService();
        $payment = $service->createPayment($data['order'])['payment'];
        $service->handleCallback([
            'reference' => $payment->gateway_reference,
            'gateway_status' => 'failed',
        ]);
        $data['order']->forceFill(['created_at' => now()->subMinutes(31)])->saveQuietly();
        $this->assertSame(1, app(PaymentExpirationService::class)->expirePendingPayments(30));
        $this->assertSame(OrderStatus::CANCELLED, $data['order']->fresh()->status);
        $this->assertSame(0, $data['inventory']->fresh()->reserved_quantity);
    }

    public function test_late_verified_callback_records_reconciliation_without_selling_stock(): void
    {
        $data = $this->preparePendingOrder();
        $service = $this->paymentService();
        $payment = $service->createPayment($data['order'])['payment'];
        $data['order']->forceFill(['created_at' => now()->subMinutes(31)])->saveQuietly();
        app(PaymentExpirationService::class)->expirePayment($payment->id);
        try {
            $service->handleCallback([
                'reference' => $payment->gateway_reference,
                'gateway_status' => 'success',
                'transaction_id' => 'LATE-AUDIT-TXN',
                'amount' => 220,
                'currency' => 'IRT',
            ]);
            $this->fail('Late callback should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }
        $this->assertDatabaseHas('payment_reconciliations', [
            'payment_id' => $payment->id,
            'gateway_transaction_id' => 'LATE-AUDIT-TXN',
            'status' => 'requires_review',
        ]);
        $this->assertSame(OrderStatus::CANCELLED, $data['order']->fresh()->status);
        $this->assertSame(10, $data['inventory']->fresh()->quantity);
    }

}
