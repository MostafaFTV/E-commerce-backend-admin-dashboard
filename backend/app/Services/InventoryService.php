<?php

namespace App\Services;

use App\Exceptions\InventoryConflictException;
use App\Models\ActivityLog;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryService
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {}

    public function addStock(
        Inventory $inventory,
        int $quantity,
        ?User $user = null,
        ?string $note = null
    ): Inventory {
        if ($quantity <= 0) {
            throw new RuntimeException(
                'Quantity must be positive.'
            );
        }

        return DB::transaction(function () use (
            $inventory,
            $quantity,
            $user,
            $note
        ) {
            $inventory = Inventory::query()
                ->whereKey($inventory->id)
                ->lockForUpdate()
                ->firstOrFail();

            $quantityBefore = $inventory->quantity;
            $reservedBefore = $inventory->reserved_quantity;

            $inventory->increment(
                'quantity',
                $quantity
            );

            $movement = $this->createMovement(
                $inventory,
                'purchase',
                $quantity,
                0,
                null,
                null,
                $user,
                $note
            );

            $inventory = $inventory->refresh();

            $this->logInventoryOperation(
                action: 'inventory.stock_added',
                description: 'Inventory stock added.',
                inventory: $inventory,
                movement: $movement,
                quantityBefore: $quantityBefore,
                reservedBefore: $reservedBefore,
                user: $user,
                extra: [
                    'operation' => 'purchase',
                    'quantity_added' => $quantity,
                    'note' => $note,
                ]
            );

            return $inventory;
        });
    }

    public function reserveStock(
        Inventory $inventory,
        int $quantity,
        ?Order $order = null,
        ?User $user = null,
        ?OrderItem $orderItem = null
    ): Inventory {
        if ($quantity <= 0) {
            throw new RuntimeException(
                'Quantity must be positive.'
            );
        }

        $this->validateMovementContext(
            $inventory,
            $order,
            $orderItem
        );

        return DB::transaction(function () use (
            $inventory,
            $quantity,
            $order,
            $user,
            $orderItem
        ) {
            $inventory = Inventory::query()
                ->whereKey($inventory->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateMovementContext(
                $inventory,
                $order,
                $orderItem
            );

            if (! $inventory->hasStock($quantity)) {
                throw new InventoryConflictException(
                    'Not enough available stock.'
                );
            }

            $quantityBefore = $inventory->quantity;
            $reservedBefore = $inventory->reserved_quantity;

            $inventory->increment(
                'reserved_quantity',
                $quantity
            );

            $movement = $this->createMovement(
                $inventory,
                'reservation',
                0,
                $quantity,
                $order,
                $orderItem,
                $user
            );

            $inventory = $inventory->refresh();

            $this->logInventoryOperation(
                action: 'inventory.stock_reserved',
                description: 'Inventory stock reserved.',
                inventory: $inventory,
                movement: $movement,
                quantityBefore: $quantityBefore,
                reservedBefore: $reservedBefore,
                user: $user,
                extra: [
                    'operation' => 'reservation',
                    'quantity_reserved' => $quantity,
                    'order_id' => $order?->id,
                    'order_number' => $order?->order_number,
                    'order_item_id' => $orderItem?->id,
                ]
            );

            return $inventory;
        });
    }

    public function releaseStock(
        Inventory $inventory,
        int $quantity,
        ?Order $order = null,
        ?User $user = null
    ): Inventory {
        if ($quantity <= 0) {
            throw new RuntimeException(
                'Quantity must be positive.'
            );
        }

        $this->validateMovementContext(
            $inventory,
            $order,
            null
        );

        return DB::transaction(function () use (
            $inventory,
            $quantity,
            $order,
            $user
        ) {
            $inventory = Inventory::query()
                ->whereKey($inventory->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateMovementContext(
                $inventory,
                $order,
                null
            );

            if ($inventory->reserved_quantity < $quantity) {
                throw new InventoryConflictException(
                    'Reserved stock is insufficient.'
                );
            }

            $quantityBefore = $inventory->quantity;
            $reservedBefore = $inventory->reserved_quantity;

            $inventory->decrement(
                'reserved_quantity',
                $quantity
            );

            $movement = $this->createMovement(
                $inventory,
                'release',
                0,
                -$quantity,
                $order,
                null,
                $user
            );

            $inventory = $inventory->refresh();

            $this->logInventoryOperation(
                action: 'inventory.stock_released',
                description: 'Reserved inventory stock released.',
                inventory: $inventory,
                movement: $movement,
                quantityBefore: $quantityBefore,
                reservedBefore: $reservedBefore,
                user: $user,
                extra: [
                    'operation' => 'release',
                    'quantity_released' => $quantity,
                    'order_id' => $order?->id,
                    'order_number' => $order?->order_number,
                ]
            );

            return $inventory;
        });
    }

    public function sellStock(
        Inventory $inventory,
        int $quantity,
        ?Order $order = null,
        ?OrderItem $orderItem = null
    ): Inventory {
        if ($quantity <= 0) {
            throw new RuntimeException(
                'Quantity must be positive.'
            );
        }

        $this->validateMovementContext(
            $inventory,
            $order,
            $orderItem
        );

        return DB::transaction(function () use (
            $inventory,
            $quantity,
            $order,
            $orderItem
        ) {
            $inventory = Inventory::query()
                ->whereKey($inventory->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateMovementContext(
                $inventory,
                $order,
                $orderItem
            );

            if ($inventory->reserved_quantity < $quantity) {
                throw new InventoryConflictException(
                    'Reserved stock is insufficient.'
                );
            }

            $quantityBefore = $inventory->quantity;
            $reservedBefore = $inventory->reserved_quantity;

            $inventory->decrement(
                'quantity',
                $quantity
            );

            $inventory->decrement(
                'reserved_quantity',
                $quantity
            );

            $movement = $this->createMovement(
                $inventory,
                'sale',
                -$quantity,
                -$quantity,
                $order,
                $orderItem
            );

            $inventory = $inventory->refresh();

            $this->logInventoryOperation(
                action: 'inventory.stock_sold',
                description: 'Inventory stock sold.',
                inventory: $inventory,
                movement: $movement,
                quantityBefore: $quantityBefore,
                reservedBefore: $reservedBefore,
                user: null,
                extra: [
                    'operation' => 'sale',
                    'quantity_sold' => $quantity,
                    'order_id' => $order?->id,
                    'order_number' => $order?->order_number,
                    'order_item_id' => $orderItem?->id,
                ]
            );

            return $inventory;
        });
    }

    /**
     * Restore physically sold stock after an approved cancellation/return.
     *
     * This increases physical quantity only.
     * reserved_quantity is not increased.
     */
    public function returnStock(
        Inventory $inventory,
        int $quantity,
        ?Order $order = null,
        ?OrderItem $orderItem = null,
        ?User $user = null,
        ?string $note = null
    ): Inventory {
        if ($quantity <= 0) {
            throw new RuntimeException(
                'Quantity must be positive.'
            );
        }

        $this->validateMovementContext(
            $inventory,
            $order,
            $orderItem
        );

        return DB::transaction(function () use (
            $inventory,
            $quantity,
            $order,
            $orderItem,
            $user,
            $note
        ) {
            $inventory = Inventory::query()
                ->whereKey($inventory->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateMovementContext(
                $inventory,
                $order,
                $orderItem
            );

            $quantityBefore = $inventory->quantity;
            $reservedBefore = $inventory->reserved_quantity;

            $inventory->increment(
                'quantity',
                $quantity
            );

            $movement = $this->createMovement(
                $inventory,
                'return',
                $quantity,
                0,
                $order,
                $orderItem,
                $user,
                $note
            );

            $inventory = $inventory->refresh();

            $this->logInventoryOperation(
                action: 'inventory.stock_returned',
                description: 'Sold inventory stock returned.',
                inventory: $inventory,
                movement: $movement,
                quantityBefore: $quantityBefore,
                reservedBefore: $reservedBefore,
                user: $user,
                extra: [
                    'operation' => 'return',
                    'quantity_returned' => $quantity,
                    'order_id' => $order?->id,
                    'order_number' => $order?->order_number,
                    'order_item_id' => $orderItem?->id,
                    'note' => $note,
                ]
            );

            return $inventory;
        });
    }

    /**
     * Manually correct physical stock.
     *
     * Reserved stock is system-controlled and is never
     * changed by a manual adjustment.
     */
    public function adjustStock(
        Inventory $inventory,
        int $quantityDelta,
        ?User $user = null,
        ?string $note = null
    ): Inventory {
        if ($quantityDelta === 0) {
            throw new RuntimeException(
                'Quantity adjustment may not be zero.'
            );
        }

        return DB::transaction(function () use (
            $inventory,
            $quantityDelta,
            $user,
            $note
        ) {
            $inventory = Inventory::query()
                ->whereKey($inventory->id)
                ->lockForUpdate()
                ->firstOrFail();

            $quantityBefore = $inventory->quantity;
            $reservedBefore = $inventory->reserved_quantity;

            $newQuantity =
                $quantityBefore + $quantityDelta;

            if ($newQuantity < 0) {
                throw new InventoryConflictException(
                    'Physical stock cannot be negative.'
                );
            }

            if (
                $newQuantity <
                $inventory->reserved_quantity
            ) {
                throw new InventoryConflictException(
                    'Physical stock cannot be lower than reserved stock.'
                );
            }

            $inventory->update([
                'quantity' => $newQuantity,
            ]);

            $movement = $this->createMovement(
                inventory: $inventory,
                type: 'adjustment',
                quantityDelta: $quantityDelta,
                reservedDelta: 0,
                user: $user,
                note: $note
            );

            $inventory = $inventory->refresh();

            $this->logInventoryOperation(
                action: 'inventory.stock_adjusted',
                description: 'Inventory stock manually adjusted.',
                inventory: $inventory,
                movement: $movement,
                quantityBefore: $quantityBefore,
                reservedBefore: $reservedBefore,
                user: $user,
                extra: [
                    'operation' => 'adjustment',
                    'quantity_delta' => $quantityDelta,
                    'note' => $note,
                ]
            );

            return $inventory;
        });
    }

    /**
     * Update the low-stock warning threshold.
     *
     * This is configuration, not an inventory movement.
     */
    public function updateLowStockThreshold(
        Inventory $inventory,
        int $threshold,
        ?User $user = null
    ): Inventory {
        if ($threshold < 0) {
            throw new RuntimeException(
                'Low-stock threshold cannot be negative.'
            );
        }

        return DB::transaction(function () use (
            $inventory,
            $threshold,
            $user
        ) {
            $inventory = Inventory::query()
                ->whereKey($inventory->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before =
                $inventory->low_stock_threshold;

            $inventory->update([
                'low_stock_threshold' => $threshold,
            ]);

            $inventory = $inventory->refresh();

            $this->activityLogService->log(
                action:
                    'inventory.low_stock_threshold_updated',
                description:
                    'Inventory low-stock threshold updated.',
                properties: [
                    'inventory_id' =>
                        $inventory->id,

                    'product_variant_id' =>
                        $inventory->product_variant_id,

                    'low_stock_threshold_before' =>
                        $before,

                    'low_stock_threshold_after' =>
                        $inventory->low_stock_threshold,
                ],
                subject: $inventory,
                actor: $user
            );

            return $inventory;
        });
    }

    /**
     * Validate that order and order item references belong together
     * and that the order item belongs to the same product variant
     * as the inventory record being changed.
     */
    protected function validateMovementContext(
        Inventory $inventory,
        ?Order $order = null,
        ?OrderItem $orderItem = null
    ): void {
        if (
            $orderItem !== null &&
            $orderItem->product_variant_id !== $inventory->product_variant_id
        ) {
            throw new RuntimeException(
                'Order item does not belong to this inventory variant.'
            );
        }

        if (
            $orderItem !== null &&
            $order !== null &&
            $orderItem->order_id !== $order->id
        ) {
            throw new RuntimeException(
                'Order item does not belong to the given order.'
            );
        }
    }

    protected function createMovement(
        Inventory $inventory,
        string $type,
        int $quantityDelta,
        int $reservedDelta,
        ?Order $order = null,
        ?OrderItem $orderItem = null,
        ?User $user = null,
        ?string $note = null
    ): InventoryMovement {
        return InventoryMovement::create([
            'inventory_id' => $inventory->id,
            'type' => $type,
            'quantity_delta' => $quantityDelta,
            'reserved_delta' => $reservedDelta,
            'order_id' => $order?->id,
            'order_item_id' => $orderItem?->id,
            'created_by' => $user?->id,
            'note' => $note,
        ]);
    }

    protected function logInventoryOperation(
        string $action,
        string $description,
        Inventory $inventory,
        InventoryMovement $movement,
        int $quantityBefore,
        int $reservedBefore,
        ?User $user,
        array $extra = []
    ): ActivityLog {
        $properties = array_merge(
            [
                'inventory' => [
                    'id' => $inventory->id,
                    'product_variant_id' => $inventory->product_variant_id,
                ],

                'movement' => [
                    'id' => $movement->id,
                    'type' => $movement->type,
                    'quantity_delta' => $movement->quantity_delta,
                    'reserved_delta' => $movement->reserved_delta,
                    'order_id' => $movement->order_id,
                    'order_item_id' => $movement->order_item_id,
                ],

                'quantity_before' => $quantityBefore,
                'quantity_after' => $inventory->quantity,

                'reserved_quantity_before' => $reservedBefore,
                'reserved_quantity_after' => $inventory->reserved_quantity,

                'available_quantity_before' =>
                    $quantityBefore - $reservedBefore,

                'available_quantity_after' =>
                    $inventory->availableQuantity(),
            ],
            $extra
        );

        return $this->activityLogService->log(
            action: $action,
            description: $description,
            properties: $properties,
            subject: $inventory,
            actor: $user
        );
    }
}