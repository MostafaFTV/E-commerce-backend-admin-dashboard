<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InventoryConflictException;
use App\Exceptions\OrderActionForbiddenException;
use App\Exceptions\OrderStatusTransitionException;
use App\Exceptions\PaymentRefundException;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\InventoryService;
use App\Services\OrderStatusService;
use App\Services\PaymentService;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Normal Status Transitions
    |--------------------------------------------------------------------------
    */

    public function test_paid_order_can_be_moved_to_processing(): void
    {
        $order = $this->createOrder(
            OrderStatus::PAID
        );

        $admin = $this->createAdminUser();

        $service = $this->orderStatusService();

        $updatedOrder = $service->changeStatus(
            $order,
            OrderStatus::PROCESSING,
            $admin
        );

        $this->assertSame(
            OrderStatus::PROCESSING,
            $updatedOrder->status
        );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::PROCESSING->value,
        ]);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'from_status' => OrderStatus::PAID->value,
            'to_status' => OrderStatus::PROCESSING->value,
            'changed_by' => $admin->id,
        ]);
    }

    public function test_processing_order_can_be_moved_to_shipped(): void
    {
        $order = $this->createOrder(
            OrderStatus::PROCESSING
        );

        $admin = $this->createAdminUser();

        $service = $this->orderStatusService();

        $updatedOrder = $service->changeStatus(
            $order,
            OrderStatus::SHIPPED,
            $admin
        );

        $this->assertSame(
            OrderStatus::SHIPPED,
            $updatedOrder->status
        );

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'from_status' => OrderStatus::PROCESSING->value,
            'to_status' => OrderStatus::SHIPPED->value,
        ]);
    }

    public function test_shipped_order_can_be_moved_to_delivered(): void
    {
        $order = $this->createOrder(
            OrderStatus::SHIPPED
        );

        $admin = $this->createAdminUser();

        $service = $this->orderStatusService();

        $updatedOrder = $service->changeStatus(
            $order,
            OrderStatus::DELIVERED,
            $admin
        );

        $this->assertSame(
            OrderStatus::DELIVERED,
            $updatedOrder->status
        );

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'from_status' => OrderStatus::SHIPPED->value,
            'to_status' => OrderStatus::DELIVERED->value,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Invalid Transitions
    |--------------------------------------------------------------------------
    */

    public function test_paid_order_cannot_be_moved_directly_to_shipped(): void
    {
        $order = $this->createOrder(
            OrderStatus::PAID
        );

        $admin = $this->createAdminUser();

        $service = $this->orderStatusService();

        $this->expectException(
            OrderStatusTransitionException::class
        );

        $service->changeStatus(
            $order,
            OrderStatus::SHIPPED,
            $admin
        );
    }

    public function test_delivered_order_cannot_be_moved_to_processing(): void
    {
        $order = $this->createOrder(
            OrderStatus::DELIVERED
        );

        $admin = $this->createAdminUser();

        $service = $this->orderStatusService();

        $this->expectException(
            OrderStatusTransitionException::class
        );

        $service->changeStatus(
            $order,
            OrderStatus::PROCESSING,
            $admin
        );
    }

    public function test_cancelled_order_cannot_be_moved_to_paid(): void
    {
        $order = $this->createOrder(
            OrderStatus::CANCELLED
        );

        $admin = $this->createAdminUser();

        $service = $this->orderStatusService();

        $this->expectException(
            OrderStatusTransitionException::class
        );

        $service->changeStatus(
            $order,
            OrderStatus::PAID,
            $admin
        );
    }

    public function test_returned_order_cannot_be_moved_to_paid(): void
    {
        $order = $this->createOrder(
            OrderStatus::RETURNED
        );

        $admin = $this->createAdminUser();

        $service = $this->orderStatusService();

        $this->expectException(
            OrderStatusTransitionException::class
        );

        $service->changeStatus(
            $order,
            OrderStatus::PAID,
            $admin
        );
    }

    /*
    |--------------------------------------------------------------------------
    | canChangeStatus
    |--------------------------------------------------------------------------
    */

    public function test_can_change_status_returns_true_for_valid_transition(): void
    {
        $service = $this->orderStatusService();

        $this->assertTrue(
            $service->canChangeStatus(
                OrderStatus::PAID,
                OrderStatus::PROCESSING
            )
        );

        $this->assertTrue(
            $service->canChangeStatus(
                OrderStatus::PROCESSING,
                OrderStatus::SHIPPED
            )
        );

        $this->assertTrue(
            $service->canChangeStatus(
                OrderStatus::SHIPPED,
                OrderStatus::DELIVERED
            )
        );
    }

    public function test_can_change_status_returns_false_for_invalid_transition(): void
    {
        $service = $this->orderStatusService();

        $this->assertFalse(
            $service->canChangeStatus(
                OrderStatus::PAID,
                OrderStatus::SHIPPED
            )
        );

        $this->assertFalse(
            $service->canChangeStatus(
                OrderStatus::DELIVERED,
                OrderStatus::PROCESSING
            )
        );

        $this->assertFalse(
            $service->canChangeStatus(
                OrderStatus::CANCELLED,
                OrderStatus::PAID
            )
        );

        $this->assertFalse(
            $service->canChangeStatus(
                OrderStatus::RETURNED,
                OrderStatus::PAID
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Pending Payment Cancellation
    |--------------------------------------------------------------------------
    */

    public function test_pending_payment_order_can_be_cancelled_and_reserved_stock_is_released(): void
    {
        $order = $this->createOrder(
            OrderStatus::PENDING_PAYMENT
        );

        [$item, $inventory] = $this->createOrderItemWithInventory(
            $order,
            quantity: 2,
            inventoryQuantity: 10,
            reservedQuantity: 2
        );

        $admin = $this->createAdminUser();

        $service = $this->orderStatusService();

        $updatedOrder = $service->changeStatus(
            $order,
            OrderStatus::CANCELLED,
            $admin
        );

        $this->assertSame(
            OrderStatus::CANCELLED,
            $updatedOrder->status
        );

        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $inventory->id,
            'type' => InventoryMovementType::RELEASE->value,
            'quantity_delta' => 0,
            'reserved_delta' => -2,
            'order_id' => $order->id,
        ]);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'from_status' => OrderStatus::PENDING_PAYMENT->value,
            'to_status' => OrderStatus::CANCELLED->value,
            'changed_by' => $admin->id,
        ]);

        $this->assertNotNull($item->fresh());
    }

    public function test_pending_payment_cancellation_fails_when_reserved_inventory_is_insufficient(): void
    {
        $order = $this->createOrder(
            OrderStatus::PENDING_PAYMENT
        );

        [, $inventory] = $this->createOrderItemWithInventory(
            $order,
            quantity: 2,
            inventoryQuantity: 10,
            reservedQuantity: 1
        );

        $admin = $this->createAdminUser();

        $service = $this->orderStatusService();

        $this->expectException(
            InventoryConflictException::class
        );

        $service->changeStatus(
            $order,
            OrderStatus::CANCELLED,
            $admin
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Sold Inventory Cancellation
    |--------------------------------------------------------------------------
    */

    public function test_paid_order_can_be_cancelled_and_sold_stock_is_returned(): void
    {
        $order = $this->createOrder(
            OrderStatus::PAID
        );

        [$item, $inventory] = $this->createOrderItemWithInventory(
            $order,
            quantity: 2,
            inventoryQuantity: 8,
            reservedQuantity: 0
        );

        $this->createSaleMovement(
            $inventory,
            $order,
            $item,
            2
        );

        $admin = $this->createAdminUser();

        $service = $this->orderStatusService();

        $updatedOrder = $service->changeStatus(
            $order,
            OrderStatus::CANCELLED,
            $admin
        );

        $this->assertSame(
            OrderStatus::CANCELLED,
            $updatedOrder->status
        );

        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $inventory->id,
            'type' => InventoryMovementType::RETURN->value,
            'quantity_delta' => 2,
            'reserved_delta' => 0,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
        ]);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'from_status' => OrderStatus::PAID->value,
            'to_status' => OrderStatus::CANCELLED->value,
            'changed_by' => $admin->id,
        ]);
    }

    public function test_processing_order_can_be_cancelled_and_sold_stock_is_returned(): void
    {
        $order = $this->createOrder(
            OrderStatus::PROCESSING
        );

        [$item, $inventory] = $this->createOrderItemWithInventory(
            $order,
            quantity: 1,
            inventoryQuantity: 4,
            reservedQuantity: 0
        );

        $this->createSaleMovement(
            $inventory,
            $order,
            $item,
            1
        );

        $admin = $this->createAdminUser();

        $service = $this->orderStatusService();

        $updatedOrder = $service->changeStatus(
            $order,
            OrderStatus::CANCELLED,
            $admin
        );

        $this->assertSame(
            OrderStatus::CANCELLED,
            $updatedOrder->status
        );

        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'quantity' => 5,
            'reserved_quantity' => 0,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Return Inventory
    |--------------------------------------------------------------------------
    */

    public function test_shipped_order_can_be_returned_and_sold_stock_is_restored(): void
    {
        $order = $this->createOrder(
            OrderStatus::SHIPPED
        );

        [$item, $inventory] = $this->createOrderItemWithInventory(
            $order,
            quantity: 3,
            inventoryQuantity: 7,
            reservedQuantity: 0
        );

        $this->createSaleMovement(
            $inventory,
            $order,
            $item,
            3
        );

        $admin = $this->createAdminUser();

        $service = $this->orderStatusService();

        $updatedOrder = $service->changeStatus(
            $order,
            OrderStatus::RETURNED,
            $admin,
            'Customer return approved.'
        );

        $this->assertSame(
            OrderStatus::RETURNED,
            $updatedOrder->status
        );

        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
        ]);
    }

    public function test_delivered_order_can_be_returned_and_sold_stock_is_restored(): void
    {
        $order = $this->createOrder(
            OrderStatus::DELIVERED
        );

        [$item, $inventory] = $this->createOrderItemWithInventory(
            $order,
            quantity: 2,
            inventoryQuantity: 6,
            reservedQuantity: 0
        );

        $this->createSaleMovement(
            $inventory,
            $order,
            $item,
            2
        );

        $admin = $this->createAdminUser();

        $service = $this->orderStatusService();

        $updatedOrder = $service->changeStatus(
            $order,
            OrderStatus::RETURNED,
            $admin
        );

        $this->assertSame(
            OrderStatus::RETURNED,
            $updatedOrder->status
        );

        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'quantity' => 8,
            'reserved_quantity' => 0,
        ]);
    }

    public function test_return_fails_when_sold_quantity_exceeds_order_item_quantity(): void
    {
        $order = $this->createOrder(
            OrderStatus::PAID
        );

        [$item, $inventory] = $this->createOrderItemWithInventory(
            $order,
            quantity: 2,
            inventoryQuantity: 8,
            reservedQuantity: 0
        );

        $this->createSaleMovement(
            $inventory,
            $order,
            $item,
            3
        );

        $admin = $this->createAdminUser();

        $service = $this->orderStatusService();

        $this->expectException(
            InventoryConflictException::class
        );

        $service->changeStatus(
            $order,
            OrderStatus::CANCELLED,
            $admin
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate Cancel / Return
    |--------------------------------------------------------------------------
    */

    public function test_cancelled_order_cannot_be_cancelled_again_and_side_effects_are_not_repeated(): void
    {
        $order = $this->createOrder(
            OrderStatus::PAID
        );

        [$item, $inventory] = $this->createOrderItemWithInventory(
            $order,
            quantity: 2,
            inventoryQuantity: 8,
            reservedQuantity: 0
        );

        $this->createSaleMovement(
            $inventory,
            $order,
            $item,
            2
        );

        $payment = $this->createPaidPayment(
            $order,
            'PAID-TXN-DUPLICATE-CANCEL',
            120
        );

        $admin = $this->createAdminUser();

        $service = $this->orderStatusService();

        /*
        |--------------------------------------------------------------------------
        | First cancellation
        |--------------------------------------------------------------------------
        */

        $service->changeStatus(
            $order,
            OrderStatus::CANCELLED,
            $admin
        );

        $this->assertSame(
            OrderStatus::CANCELLED,
            $order->fresh()->status
        );

        $this->assertSame(
            PaymentStatus::REFUNDED,
            $payment->fresh()->status
        );

        $this->assertSame(
            10,
            $inventory->fresh()->quantity
        );

        $this->assertSame(
            1,
            InventoryMovement::query()
                ->where(
                    'order_id',
                    $order->id
                )
                ->where(
                    'type',
                    InventoryMovementType::RETURN
                )
                ->count()
        );

        $this->assertSame(
            1,
            OrderStatusHistory::query()
                ->where(
                    'order_id',
                    $order->id
                )
                ->where(
                    'to_status',
                    OrderStatus::CANCELLED->value
                )
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Second cancellation must fail before side effects
        |--------------------------------------------------------------------------
        */

        try {
            $service->changeStatus(
                $order->fresh(),
                OrderStatus::CANCELLED,
                $admin
            );

            $this->fail(
                'A cancelled order should not be cancellable again.'
            );
        } catch (OrderStatusTransitionException $exception) {
            $this->assertStringContainsString(
                'Invalid order status transition',
                $exception->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Verify side effects were not repeated
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            OrderStatus::CANCELLED,
            $order->fresh()->status
        );

        $this->assertSame(
            PaymentStatus::REFUNDED,
            $payment->fresh()->status
        );

        $this->assertSame(
            10,
            $inventory->fresh()->quantity
        );

        $this->assertSame(
            1,
            InventoryMovement::query()
                ->where(
                    'order_id',
                    $order->id
                )
                ->where(
                    'type',
                    InventoryMovementType::RETURN
                )
                ->count()
        );

        $this->assertSame(
            1,
            OrderStatusHistory::query()
                ->where(
                    'order_id',
                    $order->id
                )
                ->where(
                    'to_status',
                    OrderStatus::CANCELLED->value
                )
                ->count()
        );
    }

    public function test_returned_order_cannot_be_returned_again_and_side_effects_are_not_repeated(): void
    {
        $order = $this->createOrder(
            OrderStatus::SHIPPED
        );

        [$item, $inventory] = $this->createOrderItemWithInventory(
            $order,
            quantity: 2,
            inventoryQuantity: 8,
            reservedQuantity: 0
        );

        $this->createSaleMovement(
            $inventory,
            $order,
            $item,
            2
        );

        $payment = $this->createPaidPayment(
            $order,
            'PAID-TXN-DUPLICATE-RETURN',
            120
        );

        $admin = $this->createAdminUser();

        $service = $this->orderStatusService();

        /*
        |--------------------------------------------------------------------------
        | First return
        |--------------------------------------------------------------------------
        */

        $service->changeStatus(
            $order,
            OrderStatus::RETURNED,
            $admin,
            'Return approved.'
        );

        $this->assertSame(
            OrderStatus::RETURNED,
            $order->fresh()->status
        );

        $this->assertSame(
            PaymentStatus::REFUNDED,
            $payment->fresh()->status
        );

        $this->assertSame(
            10,
            $inventory->fresh()->quantity
        );

        $this->assertSame(
            1,
            InventoryMovement::query()
                ->where(
                    'order_id',
                    $order->id
                )
                ->where(
                    'type',
                    InventoryMovementType::RETURN
                )
                ->count()
        );

        $this->assertSame(
            1,
            OrderStatusHistory::query()
                ->where(
                    'order_id',
                    $order->id
                )
                ->where(
                    'to_status',
                    OrderStatus::RETURNED->value
                )
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Second return must fail before side effects
        |--------------------------------------------------------------------------
        */

        try {
            $service->changeStatus(
                $order->fresh(),
                OrderStatus::RETURNED,
                $admin
            );

            $this->fail(
                'A returned order should not be returnable again.'
            );
        } catch (OrderStatusTransitionException $exception) {
            $this->assertStringContainsString(
                'Invalid order status transition',
                $exception->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Verify side effects were not repeated
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            OrderStatus::RETURNED,
            $order->fresh()->status
        );

        $this->assertSame(
            PaymentStatus::REFUNDED,
            $payment->fresh()->status
        );

        $this->assertSame(
            10,
            $inventory->fresh()->quantity
        );

        $this->assertSame(
            1,
            InventoryMovement::query()
                ->where(
                    'order_id',
                    $order->id
                )
                ->where(
                    'type',
                    InventoryMovementType::RETURN
                )
                ->count()
        );

        $this->assertSame(
            1,
            OrderStatusHistory::query()
                ->where(
                    'order_id',
                    $order->id
                )
                ->where(
                    'to_status',
                    OrderStatus::RETURNED->value
                )
                ->count()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_customer_cannot_cancel_pending_payment_order(): void
    {
        $order = $this->createOrder(
            OrderStatus::PENDING_PAYMENT
        );

        $customer = $this->createCustomer();

        $service = $this->orderStatusService();

        $this->expectException(
            OrderActionForbiddenException::class
        );

        $service->changeStatus(
            $order,
            OrderStatus::CANCELLED,
            $customer
        );
    }

    public function test_customer_cannot_cancel_paid_order(): void
    {
        $order = $this->createOrder(
            OrderStatus::PAID
        );

        $customer = $this->createCustomer();

        $service = $this->orderStatusService();

        try {
            $service->changeStatus(
                $order,
                OrderStatus::CANCELLED,
                $customer
            );

            $this->fail(
                'Customer should not be allowed to cancel a paid order.'
            );
        } catch (OrderActionForbiddenException $exception) {
            $this->assertSame(
                'Customers cannot cancel or return orders.',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            OrderStatus::PAID,
            $order->fresh()->status
        );

        $this->assertDatabaseMissing(
            'order_status_history',
            [
                'order_id' => $order->id,
                'to_status' => OrderStatus::CANCELLED->value,
            ]
        );
    }

    public function test_customer_cannot_return_shipped_order(): void
    {
        $order = $this->createOrder(
            OrderStatus::SHIPPED
        );

        $customer = $this->createCustomer();

        $service = $this->orderStatusService();

        try {
            $service->changeStatus(
                $order,
                OrderStatus::RETURNED,
                $customer
            );

            $this->fail(
                'Customer should not be allowed to return a shipped order.'
            );
        } catch (OrderActionForbiddenException $exception) {
            $this->assertSame(
                'Customers cannot cancel or return orders.',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            OrderStatus::SHIPPED,
            $order->fresh()->status
        );

        $this->assertDatabaseMissing(
            'order_status_history',
            [
                'order_id' => $order->id,
                'to_status' => OrderStatus::RETURNED->value,
            ]
        );
    }

    public function test_user_without_staff_role_cannot_cancel_pending_payment_order(): void
    {
        $order = $this->createOrder(
            OrderStatus::PENDING_PAYMENT
        );

        $user = User::factory()->create([
            'email' =>
                'regular-user-' . uniqid() . '@tkb.test',
        ]);

        $service = $this->orderStatusService();

        $this->expectException(
            OrderActionForbiddenException::class
        );

        $service->changeStatus(
            $order,
            OrderStatus::CANCELLED,
            $user
        );
    }

    public function test_user_without_staff_role_cannot_return_shipped_order(): void
    {
        $order = $this->createOrder(
            OrderStatus::SHIPPED
        );

        $user = User::factory()->create([
            'email' =>
                'regular-user-return-' .
                uniqid() .
                '@tkb.test',
        ]);

        $service = $this->orderStatusService();

        try {
            $service->changeStatus(
                $order,
                OrderStatus::RETURNED,
                $user
            );

            $this->fail(
                'User without a staff role should not be allowed to return a shipped order.'
            );
        } catch (OrderActionForbiddenException $exception) {
            $this->assertSame(
                'User does not have permission for this action.',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            OrderStatus::SHIPPED,
            $order->fresh()->status
        );

        $this->assertDatabaseMissing(
            'order_status_history',
            [
                'order_id' => $order->id,
                'to_status' => OrderStatus::RETURNED->value,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Refund Integration
    |--------------------------------------------------------------------------
    */

    public function test_paid_order_cancellation_refunds_successful_payment(): void
    {
        $order = $this->createOrder(
            OrderStatus::PAID
        );

        [$item, $inventory] = $this->createOrderItemWithInventory(
            $order,
            quantity: 2,
            inventoryQuantity: 8,
            reservedQuantity: 0
        );

        $this->createSaleMovement(
            $inventory,
            $order,
            $item,
            2
        );

        $payment = $this->createPaidPayment(
            $order,
            'PAID-TXN-001',
            120
        );

        $admin = $this->createAdminUser();

        $service = $this->orderStatusService();

        $updatedOrder = $service->changeStatus(
            $order,
            OrderStatus::CANCELLED,
            $admin,
            'Cancellation approved.'
        );

        $this->assertSame(
            OrderStatus::CANCELLED,
            $updatedOrder->status
        );

        $this->assertSame(
            PaymentStatus::REFUNDED,
            $payment->fresh()->status
        );

        $this->assertSame(
            10,
            $inventory->fresh()->quantity
        );
    }

    public function test_shipped_order_return_refunds_successful_payment(): void
    {
        $order = $this->createOrder(
            OrderStatus::SHIPPED
        );

        [$item, $inventory] = $this->createOrderItemWithInventory(
            $order,
            quantity: 2,
            inventoryQuantity: 8,
            reservedQuantity: 0
        );

        $this->createSaleMovement(
            $inventory,
            $order,
            $item,
            2
        );

        $payment = $this->createPaidPayment(
            $order,
            'PAID-TXN-002',
            120
        );

        $admin = $this->createAdminUser();

        $service = $this->orderStatusService();

        $updatedOrder = $service->changeStatus(
            $order,
            OrderStatus::RETURNED,
            $admin,
            'Return approved.'
        );

        $this->assertSame(
            OrderStatus::RETURNED,
            $updatedOrder->status
        );

        $this->assertSame(
            PaymentStatus::REFUNDED,
            $payment->fresh()->status
        );

        $this->assertSame(
            10,
            $inventory->fresh()->quantity
        );
    }

    public function test_payment_refund_failure_rolls_back_order_inventory_and_payment(): void
    {
        $order = $this->createOrder(
            OrderStatus::PAID
        );

        [$item, $inventory] = $this->createOrderItemWithInventory(
            $order,
            quantity: 2,
            inventoryQuantity: 8,
            reservedQuantity: 0
        );

        $this->createSaleMovement(
            $inventory,
            $order,
            $item,
            2
        );

        $payment = $this->createPaidPayment(
            $order,
            'PAID-TXN-ROLLBACK',
            120
        );

        $admin = $this->createAdminUser();

        $failingGateway = new class implements PaymentGatewayInterface {
            public function requestPayment(
                Payment $payment
            ): array {
                return [
                    'reference' => 'unused',
                    'payment_url' => 'http://unused.test',
                ];
            }

            public function extractReference(
                array $callbackData
            ): string {
                return '';
            }

            public function verifyPayment(
                Payment $payment,
                array $callbackData
            ): array {
                return [
                    'success' => false,
                ];
            }

            public function refundPayment(
                Payment $payment
            ): array {
                return [
                    'success' => false,
                ];
            }
        };

        $paymentService = new PaymentService(
            $failingGateway,
            app(ActivityLogService::class)
        );

        $service = new OrderStatusService(
            app(ActivityLogService::class),
            app(InventoryService::class),
            $paymentService
        );

        $this->expectException(
            PaymentRefundException::class
        );

        try {
            $service->changeStatus(
                $order,
                OrderStatus::CANCELLED,
                $admin
            );
        } finally {
            $this->assertSame(
                OrderStatus::PAID,
                $order->fresh()->status
            );

            $this->assertSame(
                PaymentStatus::PAID,
                $payment->fresh()->status
            );

            $this->assertSame(
                8,
                $inventory->fresh()->quantity
            );

            $this->assertSame(
                0,
                $inventory->fresh()->reserved_quantity
            );

            $this->assertSame(
                1,
                InventoryMovement::query()
                    ->where(
                        'order_id',
                        $order->id
                    )
                    ->where(
                        'type',
                        InventoryMovementType::SALE
                    )
                    ->count()
            );

            $this->assertSame(
                0,
                InventoryMovement::query()
                    ->where(
                        'order_id',
                        $order->id
                    )
                    ->where(
                        'type',
                        InventoryMovementType::RETURN
                    )
                    ->count()
            );

            $this->assertDatabaseMissing(
                'order_status_history',
                [
                    'order_id' => $order->id,
                    'to_status' =>
                        OrderStatus::CANCELLED->value,
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function orderStatusService(): OrderStatusService
    {
        return new OrderStatusService(
            app(ActivityLogService::class),
            app(InventoryService::class),
            new PaymentService(
                new FakePaymentGateway(),
                app(ActivityLogService::class)
            )
        );
    }

    private function createOrder(
        OrderStatus $status
    ): Order {
        $customer = $this->createCustomer();

        return Order::create([
            'user_id' =>
                $customer->id,

            'order_number' =>
                'TKB-TEST-' . strtoupper(uniqid()),

            'status' =>
                $status,

            'subtotal' =>
                100,

            'discount' =>
                0,

            'shipping_cost' =>
                20,

            'tax' =>
                0,

            'total' =>
                120,

            'currency' =>
                'IRT',

            'shipping_method_id' =>
                null,

            'shipping_address' => [
                'first_name' =>
                    'Test',

                'last_name' =>
                    'Customer',

                'phone' =>
                    '+49123456789',

                'country' =>
                    'Germany',

                'state' =>
                    'Hesse',

                'city' =>
                    'Wiesbaden',

                'postal_code' =>
                    '65183',

                'address_line_1' =>
                    'Main Street 10',

                'address_line_2' =>
                    null,
            ],

            'billing_address' =>
                null,
        ]);
    }

    private function createOrderItemWithInventory(
        Order $order,
        int $quantity,
        int $inventoryQuantity,
        int $reservedQuantity
    ): array {
        $variant = ProductVariant::factory()
            ->active()
            ->create();

        $inventory = Inventory::create([
            'product_variant_id' =>
                $variant->id,

            'quantity' =>
                $inventoryQuantity,

            'reserved_quantity' =>
                $reservedQuantity,

            'low_stock_threshold' =>
                2,
        ]);

        $item = OrderItem::create([
            'order_id' =>
                $order->id,

            'product_id' =>
                $variant->product_id,

            'product_variant_id' =>
                $variant->id,

            'product_name' =>
                $variant->product->name,

            'sku' =>
                $variant->sku,

            'quantity' =>
                $quantity,

            'unit_price' =>
                100,

            'total' =>
                $quantity * 100,
        ]);

        return [
            $item,
            $inventory,
        ];
    }

    private function createSaleMovement(
        Inventory $inventory,
        Order $order,
        OrderItem $item,
        int $quantity
    ): InventoryMovement {
        return InventoryMovement::create([
            'inventory_id' =>
                $inventory->id,

            'type' =>
                InventoryMovementType::SALE,

            'quantity_delta' =>
                -$quantity,

            'reserved_delta' =>
                -$quantity,

            'order_id' =>
                $order->id,

            'order_item_id' =>
                $item->id,

            'created_by' =>
                null,

            'note' =>
                null,
        ]);
    }

    private function createPaidPayment(
        Order $order,
        string $transactionId,
        float $amount
    ): Payment {
        return Payment::create([
            'order_id' =>
                $order->id,

            'transaction_id' =>
                $transactionId,

            'gateway_reference' =>
                'FAKE-REF-' . uniqid(),

            'amount' =>
                $amount,

            'currency' =>
                'IRT',

            'method' =>
                'gateway',

            'gateway_response' =>
                null,

            'status' =>
                PaymentStatus::PAID,

            'paid_at' =>
                now(),
        ]);
    }

    private function createAdminUser(): User
    {
        return $this->createStaff(
            'admin'
        );
    }

    private function createCustomer(): User
    {
        $this->ensureSystemRoles();

        $user = User::factory()->create([
            'email' =>
                'customer-' .
                uniqid() .
                '@tkb.test',
        ]);

        $role = Role::where(
            'slug',
            'customer'
        )->firstOrFail();

        $user->roles()->sync([
            $role->id,
        ]);

        return $user;
    }

    private function createStaff(
        string $roleSlug
    ): User {
        $this->ensureSystemRoles();

        $user = User::factory()->create([
            'email' =>
                $roleSlug .
                '-' .
                uniqid() .
                '@tkb.test',
        ]);

        $role = Role::where(
            'slug',
            $roleSlug
        )->firstOrFail();

        $user->roles()->sync([
            $role->id,
        ]);

        return $user;
    }

    private function ensureSystemRoles(): void
    {
        $roles = [
            'super_admin' =>
                'Super Admin',

            'admin' =>
                'Admin',

            'customer' =>
                'Customer',

            'accountant' =>
                'Accountant',

            'support' =>
                'Support',
        ];

        foreach ($roles as $slug => $name) {
            Role::firstOrCreate(
                [
                    'slug' =>
                        $slug,
                ],
                [
                    'name' =>
                        $name,

                    'description' =>
                        "Test {$name} role",
                ]
            );
        }
    }
}
