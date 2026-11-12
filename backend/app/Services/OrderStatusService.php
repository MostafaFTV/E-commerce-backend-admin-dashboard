<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InventoryConflictException;
use App\Exceptions\OrderActionForbiddenException;
use App\Exceptions\OrderStatusTransitionException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderStatusService
{
    public function __construct(
        protected ActivityLogService $activityLogService,
        protected ?InventoryService $inventoryService = null,
        protected ?PaymentService $paymentService = null
    ) {}

    /**
     * Change order status with validation,
     * authorization, inventory handling,
     * payment handling and history tracking.
     */
    public function changeStatus(
        Order $order,
        OrderStatus $newStatus,
        ?User $changedBy = null,
        ?string $note = null
    ): Order {
        $currentStatus = $order->status;

        $this->validateTransition(
            $currentStatus,
            $newStatus,
            $changedBy
        );

        $this->authorize(
            $order,
            $newStatus,
            $changedBy
        );

        return DB::transaction(function () use (
            $order,
            $currentStatus,
            $newStatus,
            $changedBy,
            $note
        ) {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Re-check after locking: an asynchronous callback may already
            // have changed the order since the supplied model was loaded.
            $this->validateTransition($order->status, $newStatus, $changedBy);
            $this->authorize($order, $newStatus, $changedBy);
            $currentStatus = $order->status;

            /*
            |--------------------------------------------------------------------------
            | Inventory
            |--------------------------------------------------------------------------
            */

            /*
             * Detect wallet conflicts before any inventory change
             * or irreversible payment gateway refund.
             */
            if (in_array($newStatus, [
                OrderStatus::CANCELLED,
                OrderStatus::RETURNED,
            ], true)) {
                app(WalletReversalService::class)
                    ->assertCanReverseEarned($order);
            }

            $this->handleInventoryForTransition(
                $order,
                $currentStatus,
                $newStatus,
                $changedBy,
                $note
            );

            /*
            |--------------------------------------------------------------------------
            | Payment
            |--------------------------------------------------------------------------
            |
            | A paid order that is cancelled or returned must refund
            | its successful payment before the new terminal status
            | is persisted.
            |
            */

            $this->handlePaymentForTransition(
                $order,
                $currentStatus,
                $newStatus
            );

            /*
            |--------------------------------------------------------------------------
            | Order status
            |--------------------------------------------------------------------------
            */

            $order->update([
                'status' => $newStatus,
            ]);

            /*
             * Keep wallet effects in the same database transaction
             * as the order status transition.
             */
            if (in_array($newStatus, [
                OrderStatus::CANCELLED,
                OrderStatus::RETURNED,
            ], true)) {
                $reversalService = app(
                    WalletReversalService::class
                );

                $reversalService->reverseEarned($order);

                if ($newStatus === OrderStatus::CANCELLED) {
                    app(WalletSpendingService::class)
                        ->release($order);
                }

                $reversalService->restoreSpent($order);
            }

            /*
             * Start cashback availability and expiry only
             * when the order reaches Delivered.
             */
            if (
                $newStatus === OrderStatus::DELIVERED
                && $order->cashback_snapshot_completed_at !== null
            ) {
                app(WalletService::class)
                    ->activateDelivered($order);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $currentStatus,
                'to_status' => $newStatus,
                'changed_by' => $changedBy?->id,
                'note' => $note,
            ]);

            $this->activityLogService->log(
                action: 'orders.status_changed',
                description: 'Order status changed.',
                properties: [
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                    ],
                    'from_status' => $currentStatus->value,
                    'to_status' => $newStatus->value,
                    'changed_by' => $changedBy?->id,
                    'note' => $note,
                ],
                subject: $order,
                actor: $changedBy
            );

            return $order->refresh();
        });
    }

    /**
     * Check whether a normal workflow transition is possible.
     */
    public function canChangeStatus(
        OrderStatus $from,
        OrderStatus $to
    ): bool {
        return $from->canTransitionTo($to);
    }

    /**
     * Validate state machine.
     *
     * PAID -> CANCELLED is an administrative action.
     */
    protected function validateTransition(
        OrderStatus $from,
        OrderStatus $to,
        ?User $user = null
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Administrative cancellation
        |--------------------------------------------------------------------------
        |
        | PAID -> CANCELLED is intentionally outside the normal
        | customer-facing state machine.
        |
        | Authorized staff can proceed directly.
        | Unauthorized users are allowed through this validation
        | so authorize() can return the correct 403 response.
        |
        */

        if (
            $from === OrderStatus::PAID
            && $to === OrderStatus::CANCELLED
            && $this->isAuthorizedStaffUser($user)
        ) {
            return;
        }

        if ($this->canChangeStatus($from, $to)) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Keep the administrative PAID -> CANCELLED path reachable
        |--------------------------------------------------------------------------
        */

        if (
            $from === OrderStatus::PAID
            && $to === OrderStatus::CANCELLED
        ) {
            return;
        }

        throw new OrderStatusTransitionException(
            "Invalid order status transition: {$from->value} → {$to->value}"
        );
    }

    /**
     * Handle payment-related effects of an order status transition.
     */
    protected function handlePaymentForTransition(
        Order $order,
        OrderStatus $from,
        OrderStatus $to
    ): void {
        if (
            $to !== OrderStatus::CANCELLED
            && $to !== OrderStatus::RETURNED
        ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Pending payment cancellation
        |--------------------------------------------------------------------------
        |
        | There is no successful payment to refund.
        |
        */

        if ($from === OrderStatus::PENDING_PAYMENT) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Paid / fulfilled order cancellation or return
        |--------------------------------------------------------------------------
        */

        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->where('status', PaymentStatus::PAID)
            ->latest('id')
            ->lockForUpdate()
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Manual / legacy paid orders
        |--------------------------------------------------------------------------
        |
        | Some historical/manual orders may have PAID status without
        | a Payment record. Do not crash the order operation in that case.
        | When a paid Payment exists, it MUST be refunded.
        |
        */

        if (! $payment) {
            return;
        }

        $this->paymentService()->refundPayment(
            $payment
        );
    }

    /**
     * Apply inventory changes required by the status transition.
     */
    protected function handleInventoryForTransition(
        Order $order,
        OrderStatus $from,
        OrderStatus $to,
        ?User $user,
        ?string $note
    ): void {
        if (
            $to === OrderStatus::CANCELLED
            && $from === OrderStatus::PENDING_PAYMENT
        ) {
            // Orders created by Checkout have an inventory movement ledger.
            // Legacy/manual orders may only have a reserved_quantity value.
            if (\App\Models\InventoryMovement::query()
                ->where('order_id', $order->id)->exists()) {
                // A manual cancellation cannot silently release only part
                // of the inventory reserved for an order. A zero net
                // reservation is valid after a failed payment attempt.
                $order->load('items.variant');
                $requiredByVariant = [];

                foreach ($order->items as $item) {
                    if (! $item->variant?->requiresInventory()) {
                        continue;
                    }

                    $variantId = (int) $item->product_variant_id;
                    $requiredByVariant[$variantId] =
                        ($requiredByVariant[$variantId] ?? 0)
                        + (int) $item->quantity;
                }

                foreach ($requiredByVariant as $variantId => $required) {
                    $inventory = \App\Models\Inventory::query()
                        ->where('product_variant_id', $variantId)
                        ->lockForUpdate()
                        ->first();

                    if (! $inventory) {
                        throw new InventoryConflictException(
                            'Reserved inventory is insufficient to cancel this order.'
                        );
                    }

                    $net = (int) \App\Models\InventoryMovement::query()
                        ->where('order_id', $order->id)
                        ->where('inventory_id', $inventory->id)
                        ->sum('reserved_delta');

                    if ($net < 0 || ($net !== 0 && $net !== $required)
                        || $inventory->reserved_quantity < $net) {
                        throw new InventoryConflictException(
                            'Reserved inventory is insufficient to cancel this order.'
                        );
                    }
                }

                app(OrderPaymentReservationService::class)
                    ->releaseStock($order, $user);
            } else {
                $this->releaseReservedInventory($order, $user);
            }

            return;
        }

        if (
            $to === OrderStatus::CANCELLED
            && in_array(
                $from,
                [
                    OrderStatus::PAID,
                    OrderStatus::PROCESSING,
                ],
                true
            )
        ) {
            $this->returnSoldInventory(
                $order,
                $user,
                $note
            );

            return;
        }

        if (
            $to === OrderStatus::RETURNED
            && in_array(
                $from,
                [
                    OrderStatus::SHIPPED,
                    OrderStatus::DELIVERED,
                ],
                true
            )
        ) {
            $this->returnSoldInventory(
                $order,
                $user,
                $note
            );
        }
    }

    /**
     * Release inventory reserved for a pending-payment order.
     */
    protected function releaseReservedInventory(
        Order $order,
        ?User $user
    ): void {
        $order->loadMissing([
            'items.variant.inventory',
        ]);

        $inventoryService = $this->inventoryService();

        foreach ($order->items as $orderItem) {
            $inventory = $this->inventoryForItem(
                $orderItem
            );

            if (! $inventory) {
                continue;
            }

            if (
                $inventory->reserved_quantity
                < $orderItem->quantity
            ) {
                throw new InventoryConflictException(
                    'Reserved inventory is insufficient to cancel this order.'
                );
            }

            $inventoryService->releaseStock(
                $inventory,
                $orderItem->quantity,
                $order,
                $user
            );
        }
    }

    /**
     * Restore physically sold inventory.
     */
    protected function returnSoldInventory(
        Order $order,
        ?User $user,
        ?string $note
    ): void {
        $order->loadMissing([
            'items.variant.inventory',
        ]);

        $inventoryService = $this->inventoryService();

        foreach ($order->items as $orderItem) {
            $inventory = $this->inventoryForItem(
                $orderItem
            );

            if (! $inventory) {
                if (
                    $orderItem->variant?->requiresInventory() === false
                ) {
                    continue;
                }

                throw new RuntimeException(
                    'The order inventory could not be found.'
                );
            }

            $soldQuantity = $this->soldQuantityForOrderItem(
                $orderItem
            );

            if ($soldQuantity <= 0) {
                continue;
            }

            if ($soldQuantity > $orderItem->quantity) {
                throw new InventoryConflictException(
                    'Sold inventory quantity exceeds order item quantity.'
                );
            }

            $inventoryService->returnStock(
                $inventory,
                $soldQuantity,
                $order,
                $orderItem,
                $user,
                $note
            );
        }
    }

    /**
     * Find physical inventory for an order item.
     */
    protected function inventoryForItem(
        OrderItem $orderItem
    ) {
        return $orderItem->variant?->inventory;
    }

    /**
     * Calculate how much of the order item was physically sold.
     */
    protected function soldQuantityForOrderItem(
        OrderItem $orderItem
    ): int {
        return max(
            0,
            (int) $orderItem->inventoryMovements()
                ->where(
                    'type',
                    'sale'
                )
                ->sum(
                    DB::raw('ABS(quantity_delta)')
                )
        );
    }

    protected function inventoryService(): InventoryService
    {
        return $this->inventoryService
            ??= app(InventoryService::class);
    }

    protected function paymentService(): PaymentService
    {
        return $this->paymentService
            ??= app(PaymentService::class);
    }

    /**
     * Authorization for administrative cancellation/return.
     *
     * A null user is allowed for trusted internal system operations,
     * such as automatic payment expiration.
     */
    protected function authorize(
        Order $order,
        OrderStatus $newStatus,
        ?User $user
    ): void {
        if (
            ! in_array(
                $newStatus,
                [
                    OrderStatus::CANCELLED,
                    OrderStatus::RETURNED,
                ],
                true
            )
        ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Internal system action
        |--------------------------------------------------------------------------
        |
        | Null actor is reserved for trusted internal services.
        |
        */

        if (! $user) {
            return;
        }

        if ($user->isCustomer()) {
            throw new OrderActionForbiddenException(
                'Customers cannot cancel or return orders.'
            );
        }

        if (! $this->isAuthorizedStaffUser($user)) {
            throw new OrderActionForbiddenException(
                'User does not have permission for this action.'
            );
        }
    }

    protected function isAuthorizedStaffUser(
        ?User $user
    ): bool {
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole([
            'admin',
            'super_admin',
            'support',
        ]);
    }
}
