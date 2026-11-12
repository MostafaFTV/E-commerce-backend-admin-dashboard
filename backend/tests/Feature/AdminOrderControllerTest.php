<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_orders(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/orders');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $order->id
            )
            ->assertJsonPath(
                'data.0.order_number',
                $order->order_number
            );
    }

    public function test_admin_can_filter_orders_by_status(): void
    {
        $admin = $this->createAdmin();

        $paidOrder = $this->createOrder([
            'status' => OrderStatus::PAID,
        ]);

        $processingOrder = $this->createOrder([
            'status' => OrderStatus::PROCESSING,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/orders?status=' .
                OrderStatus::PROCESSING->value
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $processingOrder->id
            )
            ->assertJsonMissing([
                'id' => $paidOrder->id,
            ]);
    }

    public function test_admin_can_search_orders_by_order_number(): void
    {
        $admin = $this->createAdmin();

        $matching = $this->createOrder([
            'order_number' => 'TKB-SEARCH-001',
        ]);

        $nonMatching = $this->createOrder([
            'order_number' => 'TKB-OTHER-001',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/orders?order_number=SEARCH'
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $matching->id
            )
            ->assertJsonMissing([
                'id' => $nonMatching->id,
            ]);
    }

    public function test_admin_can_filter_orders_by_user_id(): void
    {
        $admin = $this->createAdmin();

        $customer = User::factory()->create([
            'email' =>
                'filter-customer-'
                . uniqid()
                . '@tkb.test',

            'status' =>
                UserStatus::ACTIVE,
        ]);

        $matching = $this->createOrder([
            'user_id' => $customer->id,
        ]);

        $other = $this->createOrder();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/orders?user_id='
                . $customer->id
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $matching->id
            )
            ->assertJsonMissing([
                'id' => $other->id,
            ]);
    }

    public function test_admin_can_view_order_details(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/admin/orders/{$order->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $order->id
            )
            ->assertJsonPath(
                'data.order_number',
                $order->order_number
            )
            ->assertJsonPath(
                'data.status',
                OrderStatus::PAID->value
            );
    }

    public function test_admin_order_resource_exposes_loaded_payments_without_gateway_response(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder();

        $payment = Payment::create([
            'order_id' => $order->id,
            'transaction_id' => 'TXN-ADMIN-001',
            'gateway_reference' => 'REF-ADMIN-001',
            'amount' => 120,
            'currency' => 'IRT',
            'method' => 'fake',
            'gateway_response' => [
                'secret' => 'internal-data',
                'raw_code' => 200,
            ],
            'status' => PaymentStatus::PAID,
            'paid_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/admin/orders/{$order->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.payments.0.id',
                $payment->id
            )
            ->assertJsonPath(
                'data.payments.0.order_id',
                $order->id
            )
            ->assertJsonPath(
                'data.payments.0.status',
                PaymentStatus::PAID->value
            )
            ->assertJsonPath(
                'data.payments.0.amount',
                120
            )
            ->assertJsonPath(
                'data.payments.0.currency',
                'IRT'
            )
            ->assertJsonPath(
                'data.payments.0.method',
                'fake'
            )
            ->assertJsonPath(
                'data.payments.0.gateway_reference',
                'REF-ADMIN-001'
            )
            ->assertJsonPath(
                'data.payments.0.transaction_id',
                'TXN-ADMIN-001'
            )
            ->assertJsonMissingPath(
                'data.payments.0.gateway_response'
            );
    }

    public function test_admin_order_resource_exposes_loaded_status_history(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder();

        $firstHistory = $order->statusHistory()->create([
            'from_status' => OrderStatus::PAID,
            'to_status' => OrderStatus::PROCESSING,
            'changed_by' => $admin->id,
            'note' => 'Order is being prepared.',
            'created_at' => now()->subMinutes(2),
        ]);

        $secondHistory = $order->statusHistory()->create([
            'from_status' => OrderStatus::PROCESSING,
            'to_status' => OrderStatus::SHIPPED,
            'changed_by' => $admin->id,
            'note' => 'Order has been shipped.',
            'created_at' => now()->subMinute(),
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/admin/orders/{$order->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status_history.0.id',
                $firstHistory->id
            )
            ->assertJsonPath(
                'data.status_history.0.from_status',
                OrderStatus::PAID->value
            )
            ->assertJsonPath(
                'data.status_history.0.to_status',
                OrderStatus::PROCESSING->value
            )
            ->assertJsonPath(
                'data.status_history.0.changed_by.id',
                $admin->id
            )
            ->assertJsonPath(
                'data.status_history.0.note',
                'Order is being prepared.'
            )
            ->assertJsonPath(
                'data.status_history.1.id',
                $secondHistory->id
            )
            ->assertJsonPath(
                'data.status_history.1.from_status',
                OrderStatus::PROCESSING->value
            )
            ->assertJsonPath(
                'data.status_history.1.to_status',
                OrderStatus::SHIPPED->value
            )
            ->assertJsonPath(
                'data.status_history.1.changed_by.id',
                $admin->id
            )
            ->assertJsonPath(
                'data.status_history.1.note',
                'Order has been shipped.'
            );
    }

    public function test_admin_can_change_paid_order_to_processing(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/status",
                [
                    'status' =>
                        OrderStatus::PROCESSING->value,

                    'note' =>
                        'Order is being prepared.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                OrderStatus::PROCESSING->value
            );

        $this->assertSame(
            OrderStatus::PROCESSING,
            $order->fresh()->status
        );

        $this->assertDatabaseHas(
            'order_status_history',
            [
                'order_id' =>
                    $order->id,

                'from_status' =>
                    OrderStatus::PAID->value,

                'to_status' =>
                    OrderStatus::PROCESSING->value,

                'changed_by' =>
                    $admin->id,

                'note' =>
                    'Order is being prepared.',
            ]
        );
    }

    public function test_admin_status_change_creates_activity_log(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder();

        $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/status",
                [
                    'status' =>
                        OrderStatus::PROCESSING->value,

                    'note' =>
                        'Start preparation.',
                ]
            )
            ->assertOk();

        $log = ActivityLog::query()
            ->where(
                'action',
                'orders.status_changed'
            )
            ->where(
                'properties->order->id',
                $order->id
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $admin->id,
            $log->user_id
        );

        $this->assertSame(
            OrderStatus::PAID->value,
            $log->properties['from_status']
        );

        $this->assertSame(
            OrderStatus::PROCESSING->value,
            $log->properties['to_status']
        );

        $this->assertSame(
            'Start preparation.',
            $log->properties['note']
        );
    }

    public function test_admin_can_cancel_paid_order(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder([
            'status' => OrderStatus::PAID,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/cancel",
                [
                    'note' => 'Cancelled by admin.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                OrderStatus::CANCELLED->value
            );

        $this->assertSame(
            OrderStatus::CANCELLED,
            $order->fresh()->status
        );

        $this->assertDatabaseHas(
            'order_status_history',
            [
                'order_id' =>
                    $order->id,

                'from_status' =>
                    OrderStatus::PAID->value,

                'to_status' =>
                    OrderStatus::CANCELLED->value,

                'changed_by' =>
                    $admin->id,

                'note' =>
                    'Cancelled by admin.',
            ]
        );
    }

    public function test_admin_can_return_delivered_order(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder([
            'status' => OrderStatus::DELIVERED,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/return",
                [
                    'note' => 'Return approved by admin.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                OrderStatus::RETURNED->value
            );

        $this->assertSame(
            OrderStatus::RETURNED,
            $order->fresh()->status
        );

        $this->assertDatabaseHas(
            'order_status_history',
            [
                'order_id' =>
                    $order->id,

                'from_status' =>
                    OrderStatus::DELIVERED->value,

                'to_status' =>
                    OrderStatus::RETURNED->value,

                'changed_by' =>
                    $admin->id,

                'note' =>
                    'Return approved by admin.',
            ]
        );
    }

    public function test_admin_cancel_creates_activity_log(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder([
            'status' => OrderStatus::PAID,
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/cancel",
                [
                    'note' =>
                        'Admin cancellation.',
                ]
            )
            ->assertOk();

        $log = ActivityLog::query()
            ->where(
                'action',
                'orders.status_changed'
            )
            ->where(
                'properties->order->id',
                $order->id
            )
            ->where(
                'properties->to_status',
                OrderStatus::CANCELLED->value
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $admin->id,
            $log->user_id
        );

        $this->assertSame(
            OrderStatus::PAID->value,
            $log->properties['from_status']
        );

        $this->assertSame(
            OrderStatus::CANCELLED->value,
            $log->properties['to_status']
        );

        $this->assertSame(
            'Admin cancellation.',
            $log->properties['note']
        );
    }

    public function test_admin_return_creates_activity_log(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder([
            'status' =>
                OrderStatus::DELIVERED,
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/return",
                [
                    'note' =>
                        'Admin approved return.',
                ]
            )
            ->assertOk();

        $log = ActivityLog::query()
            ->where(
                'action',
                'orders.status_changed'
            )
            ->where(
                'properties->order->id',
                $order->id
            )
            ->where(
                'properties->to_status',
                OrderStatus::RETURNED->value
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $admin->id,
            $log->user_id
        );

        $this->assertSame(
            OrderStatus::DELIVERED->value,
            $log->properties['from_status']
        );

        $this->assertSame(
            OrderStatus::RETURNED->value,
            $log->properties['to_status']
        );

        $this->assertSame(
            'Admin approved return.',
            $log->properties['note']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Exception Mapping
    |--------------------------------------------------------------------------
    */

    public function test_invalid_order_status_transition_returns_422_json(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder([
            'status' =>
                OrderStatus::PAID,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/status",
                [
                    'status' =>
                        OrderStatus::DELIVERED->value,
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' =>
                    'Invalid order status transition.',
            ])
            ->assertJsonPath(
                'errors.status.0',
                'Invalid order status transition: paid → delivered'
            );

        $this->assertSame(
            OrderStatus::PAID,
            $order->fresh()->status
        );

        $this->assertDatabaseMissing(
            'order_status_history',
            [
                'order_id' =>
                    $order->id,

                'to_status' =>
                    OrderStatus::DELIVERED->value,
            ]
        );
    }

    public function test_admin_cancel_with_insufficient_reserved_inventory_returns_409_json(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder([
            'status' =>
                OrderStatus::PENDING_PAYMENT,
        ]);

        $inventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 1
        );

        $orderItem = $this->createOrderItem(
            $order,
            $inventory,
            quantity: 2
        );

        InventoryMovement::create([
            'inventory_id' =>
                $inventory->id,

            'type' =>
                InventoryMovementType::RESERVATION->value,

            'quantity_delta' =>
                0,

            'reserved_delta' =>
                1,

            'order_id' =>
                $order->id,

            'order_item_id' =>
                $orderItem->id,

            'created_by' =>
                $admin->id,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/cancel",
                [
                    'note' =>
                        'Cancel with inconsistent inventory.',
                ]
            );

        $response
            ->assertStatus(409)
            ->assertJson([
                'message' =>
                    'Inventory conflict.',
            ])
            ->assertJsonPath(
                'errors.inventory.0',
                'Reserved inventory is insufficient to cancel this order.'
            );

        $this->assertSame(
            OrderStatus::PENDING_PAYMENT,
            $order->fresh()->status
        );

        $this->assertSame(
            10,
            $inventory->fresh()->quantity
        );

        $this->assertSame(
            1,
            $inventory->fresh()->reserved_quantity
        );

        $this->assertDatabaseMissing(
            'inventory_movements',
            [
                'inventory_id' =>
                    $inventory->id,

                'type' =>
                    InventoryMovementType::RELEASE->value,
            ]
        );

        $this->assertDatabaseMissing(
            'order_status_history',
            [
                'order_id' =>
                    $order->id,

                'to_status' =>
                    OrderStatus::CANCELLED->value,
            ]
        );
    }

    public function test_admin_cancel_refund_failure_returns_502_json_and_rolls_back_transaction(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder([
            'status' =>
                OrderStatus::PAID,
        ]);

        $inventory = $this->createInventory(
            quantity: 8,
            reservedQuantity: 0
        );

        $orderItem = $this->createOrderItem(
            $order,
            $inventory,
            quantity: 2
        );

        $payment = Payment::create([
            'order_id' =>
                $order->id,

            'transaction_id' =>
                'REFUND-FAIL-TXN-001',

            'gateway_reference' =>
                'REFUND-FAIL-REF-001',

            'amount' =>
                120,

            'currency' =>
                'IRT',

            'method' =>
                'fake',

            'gateway_response' => [
                'status' =>
                    'success',
            ],

            'status' =>
                PaymentStatus::PAID,

            'paid_at' =>
                now(),
        ]);

        InventoryMovement::create([
            'inventory_id' =>
                $inventory->id,

            'type' =>
                InventoryMovementType::SALE->value,

            'quantity_delta' =>
                -2,

            'reserved_delta' =>
                -2,

            'order_id' =>
                $order->id,

            'order_item_id' =>
                $orderItem->id,

            'created_by' =>
                null,
        ]);

        $failingGateway = new class implements PaymentGatewayInterface
        {
            public function requestPayment(
                Payment $payment
            ): array {
                return [
                    'reference' =>
                        'UNUSED',

                    'payment_url' =>
                        'http://unused.test',
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
                    'success' =>
                        false,
                ];
            }

            public function refundPayment(
                Payment $payment
            ): array {
                return [
                    'success' =>
                        false,

                    'refund_transaction_id' =>
                        null,

                    'gateway_response' => [
                        'status' =>
                            'refund_failed',
                    ],
                ];
            }
        };

        app()->instance(
            PaymentGatewayInterface::class,
            $failingGateway
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/cancel",
                [
                    'note' =>
                        'Refund gateway failure.',
                ]
            );

        $response
            ->assertStatus(502)
            ->assertJson([
                'message' =>
                    'Payment refund failed.',
            ])
            ->assertJsonPath(
                'errors.payment.0',
                'The payment gateway could not refund the payment.'
            );

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
                    InventoryMovementType::SALE->value
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
                    InventoryMovementType::RETURN->value
                )
                ->count()
        );

        $this->assertSame(
            0,
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

        $this->assertSame(
            0,
            ActivityLog::query()
                ->where(
                    'action',
                    'payments.refunded'
                )
                ->where(
                    'properties->payment->id',
                    $payment->id
                )
                ->count()
        );

        $this->assertSame(
            0,
            ActivityLog::query()
                ->where(
                    'action',
                    'inventory.stock_returned'
                )
                ->where(
                    'properties->order_id',
                    $order->id
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
                    $order->id
                )
                ->where(
                    'properties->to_status',
                    OrderStatus::CANCELLED->value
                )
                ->count()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Inventory Integration
    |--------------------------------------------------------------------------
    */

    public function test_admin_cancel_pending_payment_releases_reserved_inventory(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder([
            'status' => OrderStatus::PENDING_PAYMENT,
        ]);

        $inventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 2
        );

        $orderItem = $this->createOrderItem(
            $order,
            $inventory,
            quantity: 2
        );

        InventoryMovement::create([
            'inventory_id' =>
                $inventory->id,

            'type' =>
                InventoryMovementType::RESERVATION->value,

            'quantity_delta' =>
                0,

            'reserved_delta' =>
                2,

            'order_id' =>
                $order->id,

            'order_item_id' =>
                $orderItem->id,

            'created_by' =>
                $admin->id,
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/cancel",
                [
                    'note' =>
                        'Cancel unpaid order.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                OrderStatus::CANCELLED->value
            );

        $updatedInventory = $inventory->fresh();

        $this->assertSame(
            10,
            $updatedInventory->quantity
        );

        $this->assertSame(
            0,
            $updatedInventory->reserved_quantity
        );

        $this->assertSame(
            10,
            $updatedInventory->availableQuantity()
        );

        $this->assertDatabaseHas(
            'inventory_movements',
            [
                'inventory_id' =>
                    $inventory->id,

                'type' =>
                    InventoryMovementType::RELEASE->value,

                'quantity_delta' =>
                    0,

                'reserved_delta' =>
                    -2,

                'order_id' =>
                    $order->id,

                'created_by' =>
                    $admin->id,
            ]
        );

        $this->assertDatabaseHas(
            'activity_logs',
            [
                'action' =>
                    'inventory.stock_released',
            ]
        );
    }

    public function test_admin_cancel_paid_order_returns_sold_inventory(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder([
            'status' =>
                OrderStatus::PAID,
        ]);

        $inventory = $this->createInventory(
            quantity: 8,
            reservedQuantity: 0
        );

        $orderItem = $this->createOrderItem(
            $order,
            $inventory,
            quantity: 2
        );

        InventoryMovement::create([
            'inventory_id' =>
                $inventory->id,

            'type' =>
                InventoryMovementType::SALE->value,

            'quantity_delta' =>
                -2,

            'reserved_delta' =>
                -2,

            'order_id' =>
                $order->id,

            'order_item_id' =>
                $orderItem->id,

            'created_by' =>
                null,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/cancel",
                [
                    'note' =>
                        'Paid order cancelled by admin.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                OrderStatus::CANCELLED->value
            );

        $updatedInventory = $inventory->fresh();

        $this->assertSame(
            10,
            $updatedInventory->quantity
        );

        $this->assertSame(
            0,
            $updatedInventory->reserved_quantity
        );

        $this->assertSame(
            10,
            $updatedInventory->availableQuantity()
        );

        $this->assertDatabaseHas(
            'inventory_movements',
            [
                'inventory_id' =>
                    $inventory->id,

                'type' =>
                    InventoryMovementType::RETURN->value,

                'quantity_delta' =>
                    2,

                'reserved_delta' =>
                    0,

                'order_id' =>
                    $order->id,

                'order_item_id' =>
                    $orderItem->id,

                'created_by' =>
                    $admin->id,

                'note' =>
                    'Paid order cancelled by admin.',
            ]
        );

        $this->assertDatabaseHas(
            'activity_logs',
            [
                'action' =>
                    'inventory.stock_returned',
            ]
        );
    }

    public function test_admin_return_delivered_order_returns_sold_inventory(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder([
            'status' =>
                OrderStatus::DELIVERED,
        ]);

        $inventory = $this->createInventory(
            quantity: 8,
            reservedQuantity: 0
        );

        $orderItem = $this->createOrderItem(
            $order,
            $inventory,
            quantity: 2
        );

        InventoryMovement::create([
            'inventory_id' =>
                $inventory->id,

            'type' =>
                InventoryMovementType::SALE->value,

            'quantity_delta' =>
                -2,

            'reserved_delta' =>
                -2,

            'order_id' =>
                $order->id,

            'order_item_id' =>
                $orderItem->id,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/return",
                [
                    'note' =>
                        'Product returned by customer.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                OrderStatus::RETURNED->value
            );

        $updatedInventory = $inventory->fresh();

        $this->assertSame(
            10,
            $updatedInventory->quantity
        );

        $this->assertSame(
            0,
            $updatedInventory->reserved_quantity
        );

        $this->assertSame(
            10,
            $updatedInventory->availableQuantity()
        );

        $this->assertDatabaseHas(
            'inventory_movements',
            [
                'inventory_id' =>
                    $inventory->id,

                'type' =>
                    InventoryMovementType::RETURN->value,

                'quantity_delta' =>
                    2,

                'reserved_delta' =>
                    0,

                'order_id' =>
                    $order->id,

                'order_item_id' =>
                    $orderItem->id,

                'created_by' =>
                    $admin->id,

                'note' =>
                    'Product returned by customer.',
            ]
        );

        $this->assertDatabaseHas(
            'activity_logs',
            [
                'action' =>
                    'inventory.stock_returned',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Refund Integration
    |--------------------------------------------------------------------------
    */

    public function test_admin_cancel_paid_order_refunds_payment_and_restores_inventory(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder([
            'status' =>
                OrderStatus::PAID,
        ]);

        $inventory = $this->createInventory(
            quantity: 8,
            reservedQuantity: 0
        );

        $orderItem = $this->createOrderItem(
            $order,
            $inventory,
            quantity: 2
        );

        $payment = Payment::create([
            'order_id' =>
                $order->id,

            'transaction_id' =>
                'PAID-TXN-CANCEL-001',

            'gateway_reference' =>
                'FAKE-PAID-CANCEL-001',

            'amount' =>
                120,

            'currency' =>
                'IRT',

            'method' =>
                'fake',

            'gateway_response' => [
                'status' =>
                    'success',
            ],

            'status' =>
                PaymentStatus::PAID,

            'paid_at' =>
                now(),
        ]);

        InventoryMovement::create([
            'inventory_id' =>
                $inventory->id,

            'type' =>
                InventoryMovementType::SALE->value,

            'quantity_delta' =>
                -2,

            'reserved_delta' =>
                -2,

            'order_id' =>
                $order->id,

            'order_item_id' =>
                $orderItem->id,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/cancel",
                [
                    'note' =>
                        'Paid order cancelled and refunded.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                OrderStatus::CANCELLED->value
            );

        $this->assertSame(
            OrderStatus::CANCELLED,
            $order->fresh()->status
        );

        $this->assertSame(
            PaymentStatus::REFUNDED,
            $payment->fresh()->status
        );

        $this->assertNotEmpty(
            data_get(
                $payment->fresh()->gateway_response,
                'refund.transaction_id'
            )
        );

        $updatedInventory = $inventory->fresh();

        $this->assertSame(
            10,
            $updatedInventory->quantity
        );

        $this->assertSame(
            0,
            $updatedInventory->reserved_quantity
        );

        $this->assertSame(
            10,
            $updatedInventory->availableQuantity()
        );

        $this->assertDatabaseHas(
            'inventory_movements',
            [
                'inventory_id' =>
                    $inventory->id,

                'type' =>
                    InventoryMovementType::RETURN->value,

                'quantity_delta' =>
                    2,

                'reserved_delta' =>
                    0,

                'order_id' =>
                    $order->id,

                'order_item_id' =>
                    $orderItem->id,

                'created_by' =>
                    $admin->id,
            ]
        );

        $this->assertDatabaseHas(
            'activity_logs',
            [
                'action' =>
                    'payments.refunded',
            ]
        );

        $this->assertDatabaseHas(
            'activity_logs',
            [
                'action' =>
                    'inventory.stock_returned',
            ]
        );

        $this->assertDatabaseHas(
            'activity_logs',
            [
                'action' =>
                    'orders.status_changed',
            ]
        );
    }

    public function test_admin_return_delivered_order_refunds_payment_and_restores_inventory(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder([
            'status' =>
                OrderStatus::DELIVERED,
        ]);

        $inventory = $this->createInventory(
            quantity: 8,
            reservedQuantity: 0
        );

        $orderItem = $this->createOrderItem(
            $order,
            $inventory,
            quantity: 2
        );

        $payment = Payment::create([
            'order_id' =>
                $order->id,

            'transaction_id' =>
                'PAID-TXN-RETURN-001',

            'gateway_reference' =>
                'FAKE-PAID-RETURN-001',

            'amount' =>
                120,

            'currency' =>
                'IRT',

            'method' =>
                'fake',

            'gateway_response' => [
                'status' =>
                    'success',
            ],

            'status' =>
                PaymentStatus::PAID,

            'paid_at' =>
                now(),
        ]);

        InventoryMovement::create([
            'inventory_id' =>
                $inventory->id,

            'type' =>
                InventoryMovementType::SALE->value,

            'quantity_delta' =>
                -2,

            'reserved_delta' =>
                -2,

            'order_id' =>
                $order->id,

            'order_item_id' =>
                $orderItem->id,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/return",
                [
                    'note' =>
                        'Delivered order returned and refunded.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                OrderStatus::RETURNED->value
            );

        $this->assertSame(
            OrderStatus::RETURNED,
            $order->fresh()->status
        );

        $this->assertSame(
            PaymentStatus::REFUNDED,
            $payment->fresh()->status
        );

        $this->assertNotEmpty(
            data_get(
                $payment->fresh()->gateway_response,
                'refund.transaction_id'
            )
        );

        $updatedInventory = $inventory->fresh();

        $this->assertSame(
            10,
            $updatedInventory->quantity
        );

        $this->assertSame(
            0,
            $updatedInventory->reserved_quantity
        );

        $this->assertSame(
            10,
            $updatedInventory->availableQuantity()
        );

        $this->assertDatabaseHas(
            'inventory_movements',
            [
                'inventory_id' =>
                    $inventory->id,

                'type' =>
                    InventoryMovementType::RETURN->value,

                'quantity_delta' =>
                    2,

                'reserved_delta' =>
                    0,

                'order_id' =>
                    $order->id,

                'order_item_id' =>
                    $orderItem->id,

                'created_by' =>
                    $admin->id,
            ]
        );

        $this->assertDatabaseHas(
            'activity_logs',
            [
                'action' =>
                    'payments.refunded',
            ]
        );

        $this->assertDatabaseHas(
            'activity_logs',
            [
                'action' =>
                    'inventory.stock_returned',
            ]
        );

        $this->assertDatabaseHas(
            'activity_logs',
            [
                'action' =>
                    'orders.status_changed',
            ]
        );
    }

    public function test_failed_refund_rolls_back_order_payment_inventory_and_activity_logs(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder([
            'status' =>
                OrderStatus::PAID,
        ]);

        $inventory = $this->createInventory(
            quantity: 8,
            reservedQuantity: 0
        );

        $orderItem = $this->createOrderItem(
            $order,
            $inventory,
            quantity: 2
        );

        $payment = Payment::create([
            'order_id' =>
                $order->id,

            'transaction_id' =>
                'PAID-TXN-FAIL-001',

            'gateway_reference' =>
                'FAKE-PAID-FAIL-001',

            'amount' =>
                120,

            'currency' =>
                'IRT',

            'method' =>
                'fake',

            'gateway_response' => [
                'status' =>
                    'success',
            ],

            'status' =>
                PaymentStatus::PAID,

            'paid_at' =>
                now(),
        ]);

        InventoryMovement::create([
            'inventory_id' =>
                $inventory->id,

            'type' =>
                InventoryMovementType::SALE->value,

            'quantity_delta' =>
                -2,

            'reserved_delta' =>
                -2,

            'order_id' =>
                $order->id,

            'order_item_id' =>
                $orderItem->id,
        ]);

        $gateway = new class implements PaymentGatewayInterface
        {
            public function requestPayment(
                Payment $payment
            ): array {
                return [
                    'reference' =>
                        'FAIL-REF',

                    'payment_url' =>
                        'http://fake-gateway.test/pay',
                ];
            }

            public function extractReference(
                array $callbackData
            ): string {
                return trim(
                    (string) (
                        $callbackData['reference']
                        ?? ''
                    )
                );
            }

            public function verifyPayment(
                Payment $payment,
                array $callbackData
            ): array {
                return [
                    'success' =>
                        false,

                    'transaction_id' =>
                        null,

                    'amount' =>
                        null,

                    'currency' =>
                        null,

                    'gateway_response' => [
                        'status' =>
                            'failed',
                    ],
                ];
            }

            public function refundPayment(
                Payment $payment
            ): array {
                return [
                    'success' =>
                        false,

                    'refund_transaction_id' =>
                        null,

                    'gateway_response' => [
                        'status' =>
                            'refund_failed',
                    ],
                ];
            }
        };

        app()->instance(
            PaymentGatewayInterface::class,
            $gateway
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/cancel",
                [
                    'note' =>
                        'Refund gateway failure.',
                ]
            );

        $response->assertStatus(502);

        $this->assertSame(
            OrderStatus::PAID,
            $order->fresh()->status
        );

        $this->assertSame(
            PaymentStatus::PAID,
            $payment->fresh()->status
        );

        $updatedInventory = $inventory->fresh();

        $this->assertSame(
            8,
            $updatedInventory->quantity
        );

        $this->assertSame(
            0,
            $updatedInventory->reserved_quantity
        );

        $this->assertSame(
            0,
            InventoryMovement::query()
                ->where(
                    'inventory_id',
                    $inventory->id
                )
                ->where(
                    'order_id',
                    $order->id
                )
                ->where(
                    'type',
                    InventoryMovementType::RETURN->value
                )
                ->count()
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
                    $payment->id
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
                    $order->id
                )
                ->where(
                    'properties->to_status',
                    OrderStatus::CANCELLED->value
                )
                ->count()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Terminal State Protection
    |--------------------------------------------------------------------------
    */

    public function test_cancelled_order_cannot_be_cancelled_again(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder([
            'status' =>
                OrderStatus::CANCELLED,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/cancel",
                [
                    'note' =>
                        'Second cancellation attempt.',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.status.0',
                'Invalid order status transition: cancelled → cancelled'
            );

        $this->assertSame(
            OrderStatus::CANCELLED,
            $order->fresh()->status
        );

        $this->assertDatabaseCount(
            'order_status_history',
            0
        );

        $this->assertDatabaseCount(
            'activity_logs',
            0
        );
    }

    public function test_returned_order_cannot_be_returned_again(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder([
            'status' =>
                OrderStatus::RETURNED,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/return",
                [
                    'note' =>
                        'Second return attempt.',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.status.0',
                'Invalid order status transition: returned → returned'
            );

        $this->assertSame(
            OrderStatus::RETURNED,
            $order->fresh()->status
        );

        $this->assertDatabaseCount(
            'order_status_history',
            0
        );

        $this->assertDatabaseCount(
            'activity_logs',
            0
        );
    }

    public function test_paid_order_cannot_be_cancelled_twice_and_refunded_or_inventory_returned_twice(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder([
            'status' =>
                OrderStatus::PAID,
        ]);

        $inventory = $this->createInventory(
            quantity: 8,
            reservedQuantity: 0
        );

        $orderItem = $this->createOrderItem(
            $order,
            $inventory,
            quantity: 2
        );

        $payment = Payment::create([
            'order_id' =>
                $order->id,

            'transaction_id' =>
                'DOUBLE-CANCEL-TXN-001',

            'gateway_reference' =>
                'DOUBLE-CANCEL-REF-001',

            'amount' =>
                120,

            'currency' =>
                'IRT',

            'method' =>
                'fake',

            'gateway_response' => [
                'status' =>
                    'success',
            ],

            'status' =>
                PaymentStatus::PAID,

            'paid_at' =>
                now(),
        ]);

        InventoryMovement::create([
            'inventory_id' =>
                $inventory->id,

            'type' =>
                InventoryMovementType::SALE->value,

            'quantity_delta' =>
                -2,

            'reserved_delta' =>
                -2,

            'order_id' =>
                $order->id,

            'order_item_id' =>
                $orderItem->id,
        ]);

        $firstResponse = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/cancel",
                [
                    'note' =>
                        'First cancellation.',
                ]
            );

        $firstResponse
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                OrderStatus::CANCELLED->value
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
                    InventoryMovementType::RETURN->value
                )
                ->count()
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
                    $payment->id
                )
                ->count()
        );

        $this->assertSame(
            1,
            ActivityLog::query()
                ->where(
                    'action',
                    'inventory.stock_returned'
                )
                ->where(
                    'properties->order_id',
                    $order->id
                )
                ->count()
        );

        $historyCount = OrderStatusHistory::query()
            ->where(
                'order_id',
                $order->id
            )
            ->count();

        $activityLogCount = ActivityLog::query()
            ->where(
                'properties->order->id',
                $order->id
            )
            ->count();

        $returnMovementCount = InventoryMovement::query()
            ->where(
                'order_id',
                $order->id
            )
            ->where(
                'type',
                InventoryMovementType::RETURN->value
            )
            ->count();

        $paymentStatus = $payment->fresh()->status;
        $inventoryQuantity = $inventory->fresh()->quantity;
        $inventoryReserved = $inventory->fresh()->reserved_quantity;

        $secondResponse = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/cancel",
                [
                    'note' =>
                        'Second cancellation.',
                ]
            );

        $secondResponse
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.status.0',
                'Invalid order status transition: cancelled → cancelled'
            );

        $this->assertSame(
            OrderStatus::CANCELLED,
            $order->fresh()->status
        );

        $this->assertSame(
            $paymentStatus,
            $payment->fresh()->status
        );

        $this->assertSame(
            $inventoryQuantity,
            $inventory->fresh()->quantity
        );

        $this->assertSame(
            $inventoryReserved,
            $inventory->fresh()->reserved_quantity
        );

        $this->assertSame(
            $historyCount,
            OrderStatusHistory::query()
                ->where(
                    'order_id',
                    $order->id
                )
                ->count()
        );

        $this->assertSame(
            $activityLogCount,
            ActivityLog::query()
                ->where(
                    'properties->order->id',
                    $order->id
                )
                ->count()
        );

        $this->assertSame(
            $returnMovementCount,
            InventoryMovement::query()
                ->where(
                    'order_id',
                    $order->id
                )
                ->where(
                    'type',
                    InventoryMovementType::RETURN->value
                )
                ->count()
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
                    $payment->id
                )
                ->count()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | History
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_view_order_history(): void
    {
        $admin = $this->createAdmin();

        $order = $this->createOrder();

        $order->status = OrderStatus::PROCESSING;
        $order->save();

        $order->statusHistory()->create([
            'from_status' =>
                OrderStatus::PAID,

            'to_status' =>
                OrderStatus::PROCESSING,

            'changed_by' =>
                $admin->id,

            'note' =>
                'Order is being prepared.',

            'created_at' =>
                now(),
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/admin/orders/{$order->id}/history"
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.from_status',
                OrderStatus::PAID->value
            )
            ->assertJsonPath(
                'data.0.to_status',
                OrderStatus::PROCESSING->value
            )
            ->assertJsonPath(
                'data.0.changed_by.id',
                $admin->id
            )
            ->assertJsonPath(
                'data.0.note',
                'Order is being prepared.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Access Control
    |--------------------------------------------------------------------------
    */

    public function test_customer_cannot_access_admin_orders(): void
    {
        $customer = $this->createCustomer();

        $this->createOrder();

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->getJson(
                '/api/admin/orders'
            );

        $response->assertForbidden();
    }

    public function test_guest_cannot_access_admin_orders(): void
    {
        $response = $this->getJson(
            '/api/admin/orders'
        );

        $response->assertUnauthorized();
    }

    public function test_admin_without_view_permission_cannot_view_orders(): void
    {
        $admin = $this->createAdmin(
            permissions: [
                'orders.manage',
                'orders.update_status',
                'orders.cancel',
                'orders.return',
            ]
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/orders'
            );

        $response->assertForbidden();
    }

    public function test_admin_without_update_status_permission_cannot_change_status(): void
    {
        $admin = $this->createAdmin(
            permissions: [
                'orders.view',
                'orders.manage',
                'orders.cancel',
                'orders.return',
            ]
        );

        $order = $this->createOrder();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/status",
                [
                    'status' =>
                        OrderStatus::PROCESSING->value,
                ]
            );

        $response->assertForbidden();

        $this->assertSame(
            OrderStatus::PAID,
            $order->fresh()->status
        );
    }

    public function test_admin_without_cancel_permission_cannot_cancel_order(): void
    {
        $admin = $this->createAdmin(
            permissions: [
                'orders.view',
                'orders.manage',
                'orders.update_status',
                'orders.return',
            ]
        );

        $order = $this->createOrder([
            'status' =>
                OrderStatus::PAID,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/cancel",
                [
                    'note' =>
                        'Unauthorized cancellation.',
                ]
            );

        $response->assertForbidden();

        $this->assertSame(
            OrderStatus::PAID,
            $order->fresh()->status
        );
    }

    public function test_admin_without_return_permission_cannot_return_order(): void
    {
        $admin = $this->createAdmin(
            permissions: [
                'orders.view',
                'orders.manage',
                'orders.update_status',
                'orders.cancel',
            ]
        );

        $order = $this->createOrder([
            'status' =>
                OrderStatus::DELIVERED,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/orders/{$order->id}/return",
                [
                    'note' =>
                        'Unauthorized return.',
                ]
            );

        $response->assertForbidden();

        $this->assertSame(
            OrderStatus::DELIVERED,
            $order->fresh()->status
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createAdmin(
        array $permissions = [
            'orders.view',
            'orders.manage',
            'orders.update_status',
            'orders.cancel',
            'orders.return',
        ]
    ): User {
        $admin = User::factory()->create([
            'email' =>
                'admin-'
                . uniqid()
                . '@tkb.test',

            'status' =>
                UserStatus::ACTIVE,
        ]);

        $adminRole = Role::firstOrCreate(
            [
                'slug' =>
                    'admin',
            ],
            [
                'name' =>
                    'Admin',

                'description' =>
                    'Test admin role',
            ]
        );

        $permissionIds = collect(
            $permissions
        )->map(
            fn (string $slug) =>
                Permission::firstOrCreate(
                    [
                        'slug' =>
                            $slug,
                    ],
                    [
                        'name' =>
                            $slug,
                    ]
                )->id
        )->all();

        $adminRole->permissions()->sync(
            $permissionIds
        );

        $admin->roles()->sync([
            $adminRole->id,
        ]);

        return $admin;
    }

    private function createCustomer(): User
    {
        $customer = User::factory()->create([
            'email' =>
                'customer-'
                . uniqid()
                . '@tkb.test',

            'status' =>
                UserStatus::ACTIVE,
        ]);

        $customerRole = Role::firstOrCreate(
            [
                'slug' =>
                    'customer',
            ],
            [
                'name' =>
                    'Customer',

                'description' =>
                    'Test customer role',
            ]
        );

        $customer->roles()->sync([
            $customerRole->id,
        ]);

        return $customer;
    }

    private function createOrder(
        array $overrides = []
    ): Order {
        $customer = User::factory()->create([
            'email' =>
                'order-customer-'
                . uniqid()
                . '@tkb.test',

            'status' =>
                UserStatus::ACTIVE,
        ]);

        return Order::create(
            array_merge(
                [
                    'user_id' =>
                        $customer->id,

                    'order_number' =>
                        'TKB-TEST-'
                        . strtoupper(
                            uniqid()
                        ),

                    'status' =>
                        OrderStatus::PAID,

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
                ],
                $overrides
            )
        );
    }

    private function createInventory(
        int $quantity = 10,
        int $reservedQuantity = 0
    ): Inventory {
        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' =>
                $product->id,
        ]);

        return Inventory::create([
            'product_variant_id' =>
                $variant->id,

            'quantity' =>
                $quantity,

            'reserved_quantity' =>
                $reservedQuantity,

            'low_stock_threshold' =>
                2,
        ]);
    }

    private function createOrderItem(
        Order $order,
        Inventory $inventory,
        int $quantity = 2
    ): OrderItem {
        $variant = $inventory->variant;
        $product = $variant->product;

        return $order->items()->create([
            'product_id' =>
                $product->id,

            'product_variant_id' =>
                $variant->id,

            'product_name' =>
                $product->name,

            'sku' =>
                $variant->sku,

            'quantity' =>
                $quantity,

            'unit_price' =>
                50,

            'total' =>
                50 * $quantity,
        ]);
    }
}