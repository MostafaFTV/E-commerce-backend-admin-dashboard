<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Models\ActivityLog;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_stock_increases_quantity_and_creates_purchase_movement(): void
    {
        $inventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 2
        );

        $user = User::factory()->create();

        $service = app(InventoryService::class);

        $updated = $service->addStock(
            $inventory,
            5,
            $user,
            'Test stock purchase'
        );

        $this->assertSame(
            15,
            $updated->quantity
        );

        $this->assertSame(
            2,
            $updated->reserved_quantity
        );

        $this->assertSame(
            13,
            $updated->availableQuantity()
        );

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $inventory->id,
            'type' => InventoryMovementType::PURCHASE->value,
            'quantity_delta' => 5,
            'reserved_delta' => 0,
            'created_by' => $user->id,
            'note' => 'Test stock purchase',
        ]);
    }

    public function test_reserve_stock_increases_reserved_quantity_and_creates_reservation_movement(): void
    {
        $inventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 2
        );

        $user = User::factory()->create();

        $order = $this->createOrder(
            $user
        );

        $service = app(InventoryService::class);

        $updated = $service->reserveStock(
            $inventory,
            3,
            $order,
            $user
        );

        $this->assertSame(
            10,
            $updated->quantity
        );

        $this->assertSame(
            5,
            $updated->reserved_quantity
        );

        $this->assertSame(
            5,
            $updated->availableQuantity()
        );

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $inventory->id,
            'type' => InventoryMovementType::RESERVATION->value,
            'quantity_delta' => 0,
            'reserved_delta' => 3,
            'order_id' => $order->id,
            'created_by' => $user->id,
        ]);
    }

    public function test_reserve_stock_cannot_exceed_available_stock(): void
    {
        $inventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 7
        );

        $service = app(InventoryService::class);

        $this->expectException(
            RuntimeException::class
        );

        $service->reserveStock(
            $inventory,
            4
        );

        $this->assertSame(
            10,
            $inventory->fresh()->quantity
        );

        $this->assertSame(
            7,
            $inventory->fresh()->reserved_quantity
        );

        $this->assertDatabaseCount(
            'inventory_movements',
            0
        );
    }

    public function test_reserve_stock_rejects_order_item_from_different_variant(): void
    {
        $inventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 0
        );

        $otherInventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 0
        );

        $order = $this->createOrder();

        $orderItem = $this->createOrderItem(
            $order,
            $otherInventory
        );

        $service = app(InventoryService::class);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Order item does not belong to this inventory variant.'
        );

        $service->reserveStock(
            $inventory,
            2,
            $order,
            null,
            $orderItem
        );

        $this->assertSame(
            0,
            $inventory->fresh()->reserved_quantity
        );

        $this->assertDatabaseCount(
            'inventory_movements',
            0
        );
    }

    public function test_release_stock_decreases_reserved_quantity_and_creates_release_movement(): void
    {
        $inventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 5
        );

        $order = $this->createOrder();

        $service = app(InventoryService::class);

        $updated = $service->releaseStock(
            $inventory,
            2,
            $order
        );

        $this->assertSame(
            10,
            $updated->quantity
        );

        $this->assertSame(
            3,
            $updated->reserved_quantity
        );

        $this->assertSame(
            7,
            $updated->availableQuantity()
        );

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $inventory->id,
            'type' => InventoryMovementType::RELEASE->value,
            'quantity_delta' => 0,
            'reserved_delta' => -2,
            'order_id' => $order->id,
        ]);
    }

    public function test_release_stock_cannot_exceed_reserved_stock(): void
    {
        $inventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 2
        );

        $service = app(InventoryService::class);

        $this->expectException(
            RuntimeException::class
        );

        $service->releaseStock(
            $inventory,
            3
        );

        $this->assertSame(
            10,
            $inventory->fresh()->quantity
        );

        $this->assertSame(
            2,
            $inventory->fresh()->reserved_quantity
        );

        $this->assertDatabaseCount(
            'inventory_movements',
            0
        );
    }

    public function test_sell_stock_decreases_quantity_and_reserved_quantity_and_creates_sale_movement(): void
    {
        $inventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 4
        );

        $order = $this->createOrder();

        $orderItem = $this->createOrderItem(
            $order,
            $inventory
        );

        $service = app(InventoryService::class);

        $updated = $service->sellStock(
            $inventory,
            2,
            $order,
            $orderItem
        );

        $this->assertSame(
            8,
            $updated->quantity
        );

        $this->assertSame(
            2,
            $updated->reserved_quantity
        );

        $this->assertSame(
            6,
            $updated->availableQuantity()
        );

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $inventory->id,
            'type' => InventoryMovementType::SALE->value,
            'quantity_delta' => -2,
            'reserved_delta' => -2,
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
        ]);
    }

    public function test_sell_stock_cannot_exceed_reserved_stock(): void
    {
        $inventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 2
        );

        $service = app(InventoryService::class);

        $this->expectException(
            RuntimeException::class
        );

        $service->sellStock(
            $inventory,
            3
        );

        $this->assertSame(
            10,
            $inventory->fresh()->quantity
        );

        $this->assertSame(
            2,
            $inventory->fresh()->reserved_quantity
        );

        $this->assertDatabaseCount(
            'inventory_movements',
            0
        );
    }

    public function test_sell_stock_rejects_order_item_from_different_variant(): void
    {
        $inventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 4
        );

        $otherInventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 4
        );

        $order = $this->createOrder();

        $orderItem = $this->createOrderItem(
            $order,
            $otherInventory
        );

        $service = app(InventoryService::class);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Order item does not belong to this inventory variant.'
        );

        $service->sellStock(
            $inventory,
            2,
            $order,
            $orderItem
        );

        $freshInventory = $inventory->fresh();

        $this->assertSame(
            10,
            $freshInventory->quantity
        );

        $this->assertSame(
            4,
            $freshInventory->reserved_quantity
        );

        $this->assertDatabaseCount(
            'inventory_movements',
            0
        );
    }

    public function test_sell_stock_rejects_order_item_from_different_order(): void
    {
        $inventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 4
        );

        $orderA = $this->createOrder();

        $orderB = $this->createOrder();

        $orderItemFromOrderB = $this->createOrderItem(
            $orderB,
            $inventory
        );

        $service = app(InventoryService::class);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Order item does not belong to the given order.'
        );

        $service->sellStock(
            $inventory,
            2,
            $orderA,
            $orderItemFromOrderB
        );

        $freshInventory = $inventory->fresh();

        $this->assertSame(
            10,
            $freshInventory->quantity
        );

        $this->assertSame(
            4,
            $freshInventory->reserved_quantity
        );

        $this->assertDatabaseCount(
            'inventory_movements',
            0
        );
    }

    public function test_return_stock_increases_quantity_without_changing_reserved_quantity(): void
    {
        $inventory = $this->createInventory(
            quantity: 8,
            reservedQuantity: 2
        );

        $order = $this->createOrder();

        $orderItem = $this->createOrderItem(
            $order,
            $inventory
        );

        $service = app(InventoryService::class);

        $updated = $service->returnStock(
            $inventory,
            3,
            $order,
            $orderItem
        );

        $this->assertSame(
            11,
            $updated->quantity
        );

        $this->assertSame(
            2,
            $updated->reserved_quantity
        );

        $this->assertSame(
            9,
            $updated->availableQuantity()
        );

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $inventory->id,
            'type' => InventoryMovementType::RETURN->value,
            'quantity_delta' => 3,
            'reserved_delta' => 0,
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
        ]);
    }

    public function test_return_stock_can_record_actor_and_note(): void
    {
        $inventory = $this->createInventory(
            quantity: 8,
            reservedQuantity: 1
        );

        $order = $this->createOrder();

        $orderItem = $this->createOrderItem(
            $order,
            $inventory
        );

        $user = User::factory()->create();

        $service = app(InventoryService::class);

        $service->returnStock(
            $inventory,
            2,
            $order,
            $orderItem,
            $user,
            'Admin approved customer return.'
        );

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $inventory->id,
            'type' => InventoryMovementType::RETURN->value,
            'quantity_delta' => 2,
            'reserved_delta' => 0,
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'created_by' => $user->id,
            'note' => 'Admin approved customer return.',
        ]);
    }

    public function test_return_stock_creates_activity_log(): void
    {
        $inventory = $this->createInventory(
            quantity: 8,
            reservedQuantity: 2
        );

        $order = $this->createOrder();

        $orderItem = $this->createOrderItem(
            $order,
            $inventory
        );

        $user = User::factory()->create();

        $service = app(InventoryService::class);

        $service->returnStock(
            $inventory,
            3,
            $order,
            $orderItem,
            $user,
            'Return approved.'
        );

        $log = ActivityLog::query()
            ->where(
                'action',
                'inventory.stock_returned'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull(
            $log
        );

        $this->assertSame(
            $inventory->id,
            $log->properties['subject']['id']
        );

        $this->assertSame(
            8,
            $log->properties['quantity_before']
        );

        $this->assertSame(
            11,
            $log->properties['quantity_after']
        );

        $this->assertSame(
            2,
            $log->properties['reserved_quantity_before']
        );

        $this->assertSame(
            2,
            $log->properties['reserved_quantity_after']
        );

        $this->assertSame(
            3,
            $log->properties['movement']['quantity_delta']
        );

        $this->assertSame(
            InventoryMovementType::RETURN->value,
            $log->properties['movement']['type']
        );

        $this->assertSame(
            $order->id,
            $log->properties['order_id']
        );

        $this->assertSame(
            $orderItem->id,
            $log->properties['order_item_id']
        );

        $this->assertSame(
            $user->id,
            $log->user_id
        );

        $this->assertSame(
            'return',
            $log->properties['operation']
        );

        $this->assertSame(
            3,
            $log->properties['quantity_returned']
        );

        $this->assertSame(
            'Return approved.',
            $log->properties['note']
        );
    }

    public function test_return_stock_rejects_zero_and_negative_quantity(): void
    {
        $inventory = $this->createInventory();

        $order = $this->createOrder();

        $orderItem = $this->createOrderItem(
            $order,
            $inventory
        );

        $service = app(InventoryService::class);

        $this->expectException(
            RuntimeException::class
        );

        $service->returnStock(
            $inventory,
            0,
            $order,
            $orderItem
        );
    }

    public function test_return_stock_rejects_order_item_from_different_variant(): void
    {
        $inventory = $this->createInventory(
            quantity: 8,
            reservedQuantity: 0
        );

        $otherInventory = $this->createInventory(
            quantity: 8,
            reservedQuantity: 0
        );

        $order = $this->createOrder();

        $orderItem = $this->createOrderItem(
            $order,
            $otherInventory
        );

        $service = app(InventoryService::class);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Order item does not belong to this inventory variant.'
        );

        $service->returnStock(
            $inventory,
            3,
            $order,
            $orderItem
        );

        $freshInventory = $inventory->fresh();

        $this->assertSame(
            8,
            $freshInventory->quantity
        );

        $this->assertSame(
            0,
            $freshInventory->reserved_quantity
        );

        $this->assertDatabaseCount(
            'inventory_movements',
            0
        );
    }

    public function test_return_stock_rejects_order_item_from_different_order(): void
    {
        $inventory = $this->createInventory(
            quantity: 8,
            reservedQuantity: 0
        );

        $orderA = $this->createOrder();

        $orderB = $this->createOrder();

        $orderItemFromOrderB = $this->createOrderItem(
            $orderB,
            $inventory
        );

        $service = app(InventoryService::class);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Order item does not belong to the given order.'
        );

        $service->returnStock(
            $inventory,
            3,
            $orderA,
            $orderItemFromOrderB
        );

        $freshInventory = $inventory->fresh();

        $this->assertSame(
            8,
            $freshInventory->quantity
        );

        $this->assertSame(
            0,
            $freshInventory->reserved_quantity
        );

        $this->assertDatabaseCount(
            'inventory_movements',
            0
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Activity Logging
    |--------------------------------------------------------------------------
    */

    public function test_add_stock_creates_activity_log(): void
    {
        $inventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 2
        );

        $user = User::factory()->create();

        $service = app(InventoryService::class);

        $updated = $service->addStock(
            $inventory,
            5,
            $user,
            'Test stock purchase'
        );

        $log = ActivityLog::query()
            ->where(
                'action',
                'inventory.stock_added'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            Inventory::class,
            $log->properties['subject']['type']
        );

        $this->assertSame(
            $inventory->id,
            $log->properties['subject']['id']
        );

        $this->assertSame(
            $inventory->id,
            $log->properties['inventory']['id']
        );

        $this->assertSame(
            10,
            $log->properties['quantity_before']
        );

        $this->assertSame(
            15,
            $log->properties['quantity_after']
        );

        $this->assertSame(
            2,
            $log->properties['reserved_quantity_before']
        );

        $this->assertSame(
            2,
            $log->properties['reserved_quantity_after']
        );

        $this->assertSame(
            5,
            $log->properties['movement']['quantity_delta']
        );

        $this->assertSame(
            'purchase',
            $log->properties['operation']
        );

        $this->assertSame(
            'Test stock purchase',
            $log->properties['note']
        );
    }

    public function test_reserve_stock_creates_activity_log(): void
    {
        $inventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 2
        );

        $user = User::factory()->create();

        $order = $this->createOrder(
            $user
        );

        $service = app(InventoryService::class);

        $service->reserveStock(
            $inventory,
            3,
            $order,
            $user
        );

        $log = ActivityLog::query()
            ->where(
                'action',
                'inventory.stock_reserved'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $inventory->id,
            $log->properties['subject']['id']
        );

        $this->assertSame(
            10,
            $log->properties['quantity_before']
        );

        $this->assertSame(
            10,
            $log->properties['quantity_after']
        );

        $this->assertSame(
            2,
            $log->properties['reserved_quantity_before']
        );

        $this->assertSame(
            5,
            $log->properties['reserved_quantity_after']
        );

        $this->assertSame(
            3,
            $log->properties['movement']['reserved_delta']
        );

        $this->assertSame(
            $order->id,
            $log->properties['order_id']
        );

        $this->assertSame(
            $user->id,
            $log->user_id
        );
    }

    public function test_release_stock_creates_activity_log(): void
    {
        $inventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 5
        );

        $order = $this->createOrder();

        $service = app(InventoryService::class);

        $service->releaseStock(
            $inventory,
            2,
            $order
        );

        $log = ActivityLog::query()
            ->where(
                'action',
                'inventory.stock_released'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $inventory->id,
            $log->properties['subject']['id']
        );

        $this->assertSame(
            10,
            $log->properties['quantity_before']
        );

        $this->assertSame(
            10,
            $log->properties['quantity_after']
        );

        $this->assertSame(
            5,
            $log->properties['reserved_quantity_before']
        );

        $this->assertSame(
            3,
            $log->properties['reserved_quantity_after']
        );

        $this->assertSame(
            -2,
            $log->properties['movement']['reserved_delta']
        );

        $this->assertSame(
            $order->id,
            $log->properties['order_id']
        );
    }

    public function test_sell_stock_creates_activity_log(): void
    {
        $inventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 4
        );

        $order = $this->createOrder();

        $orderItem = $this->createOrderItem(
            $order,
            $inventory
        );

        $service = app(InventoryService::class);

        $service->sellStock(
            $inventory,
            2,
            $order,
            $orderItem
        );

        $log = ActivityLog::query()
            ->where(
                'action',
                'inventory.stock_sold'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $inventory->id,
            $log->properties['subject']['id']
        );

        $this->assertSame(
            10,
            $log->properties['quantity_before']
        );

        $this->assertSame(
            8,
            $log->properties['quantity_after']
        );

        $this->assertSame(
            4,
            $log->properties['reserved_quantity_before']
        );

        $this->assertSame(
            2,
            $log->properties['reserved_quantity_after']
        );

        $this->assertSame(
            -2,
            $log->properties['movement']['quantity_delta']
        );

        $this->assertSame(
            -2,
            $log->properties['movement']['reserved_delta']
        );

        $this->assertSame(
            $order->id,
            $log->properties['order_id']
        );

        $this->assertSame(
            $orderItem->id,
            $log->properties['order_item_id']
        );
    }

    public function test_add_stock_rejects_zero_and_negative_quantity(): void
    {
        $inventory = $this->createInventory();

        $service = app(InventoryService::class);

        $this->expectException(
            RuntimeException::class
        );

        $service->addStock(
            $inventory,
            0
        );
    }

    public function test_reserve_stock_rejects_zero_and_negative_quantity(): void
    {
        $inventory = $this->createInventory();

        $service = app(InventoryService::class);

        $this->expectException(
            RuntimeException::class
        );

        $service->reserveStock(
            $inventory,
            -1
        );
    }

    public function test_release_stock_rejects_zero_and_negative_quantity(): void
    {
        $inventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 2
        );

        $service = app(InventoryService::class);

        $this->expectException(
            RuntimeException::class
        );

        $service->releaseStock(
            $inventory,
            0
        );
    }

    public function test_sell_stock_rejects_zero_and_negative_quantity(): void
    {
        $inventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 2
        );

        $service = app(InventoryService::class);

        $this->expectException(
            RuntimeException::class
        );

        $service->sellStock(
            $inventory,
            -2
        );
    }

    private function createInventory(
        int $quantity = 10,
        int $reservedQuantity = 0
    ): Inventory {
        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
        ]);

        return Inventory::create([
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'reserved_quantity' => $reservedQuantity,
            'low_stock_threshold' => 2,
        ]);
    }

    private function createOrder(
        ?User $user = null
    ): Order {
        $user ??= User::factory()->create();

        return Order::create([
            'user_id' => $user->id,
            'order_number' => 'INV-TEST-' . strtoupper(
                substr(
                    str_replace(
                        '-',
                        '',
                        \Illuminate\Support\Str::uuid()->toString()
                    ),
                    0,
                    12
                )
            ),
            'status' => OrderStatus::PENDING_PAYMENT,
            'subtotal' => 100,
            'discount' => 0,
            'shipping_cost' => 20,
            'tax' => 0,
            'total' => 120,
            'currency' => 'IRT',
            'shipping_method_id' => null,
            'shipping_address' => [
                'first_name' => 'Test',
                'last_name' => 'Customer',
                'phone' => '+49123456789',
                'country' => 'Germany',
                'state' => 'Hesse',
                'city' => 'Wiesbaden',
                'postal_code' => '65183',
                'address_line_1' => 'Main Street 10',
                'address_line_2' => null,
            ],
            'billing_address' => null,
        ]);
    }

    private function createOrderItem(
        Order $order,
        Inventory $inventory
    ): OrderItem {
        $variant = $inventory->variant;
        $product = $variant->product;

        return $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'sku' => $variant->sku,
            'quantity' => 2,
            'unit_price' => 50,
            'total' => 100,
        ]);
    }
}