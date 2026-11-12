<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\VariantStatus;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderPaymentReservationService
{
    /** Assumes caller holds an order row lock and an outer DB transaction. */
    public function ensureReserved(Order $order): void
    {
        if ($order->status !== OrderStatus::PENDING_PAYMENT) {
            throw ValidationException::withMessages(['order' => 'Order is not payable.']);
        }

        if ($order->created_at->addMinutes(30)->lte(now())) {
            throw ValidationException::withMessages(['order' => 'Order payment window has expired.']);
        }

        $order->load('items.variant');
        $required = [];
        foreach ($order->items as $item) {
            $variant = $item->variant;
            if (!$variant) {
                throw ValidationException::withMessages(['inventory' => 'Order variant is unavailable.']);
            }
            // Only ACTIVE variants use physical stock. Made-to-order does not.
            if ($variant->status !== VariantStatus::ACTIVE) {
                if ($variant->status !== VariantStatus::MADE_TO_ORDER) {
                    throw ValidationException::withMessages(['inventory' => 'Order variant is unavailable.']);
                }
                continue;
            }
            $inventory = Inventory::query()
                ->where('product_variant_id', $variant->id)
                ->lockForUpdate()->first();
            if (!$inventory) {
                throw ValidationException::withMessages(['inventory' => 'Product inventory is unavailable.']);
            }
            $key = $inventory->id;
            if (!isset($required[$key])) {
                $required[$key] = ['inventory' => $inventory, 'quantity' => 0, 'items' => []];
            }
            $required[$key]['quantity'] += $item->quantity;
            $required[$key]['items'][] = $item;
        }

        foreach ($required as $inventoryId => $group) {
            $netReserved = (int) InventoryMovement::query()
                ->where('order_id', $order->id)
                ->where('inventory_id', $inventoryId)
                ->sum('reserved_delta');
            if ($netReserved < 0 || $netReserved > $group['quantity']) {
                throw ValidationException::withMessages(['inventory' => 'Inconsistent order stock reservations.']);
            }
            $missing = $group['quantity'] - $netReserved;
            if ($missing === 0) { continue; }
            if ($netReserved !== 0) {
                throw ValidationException::withMessages(['inventory' => 'Partial order reservation requires review.']);
            }
            // Reserve per order item to preserve existing inventory movement audit.
            foreach ($group['items'] as $item) {
                app(InventoryService::class)->reserveStock(
                    $group['inventory'], $item->quantity, $order, $order->user, $item
                );
            }
        }

        $wanted = (string) ($order->wallet_credit_used ?? '0.00');
        if ((float)$wanted > 0) {
            app(WalletSpendingService::class)->reserve($order, $wanted);
            $reserved = WalletAllocation::query()->where('order_id', $order->id)
                ->where('status', 'reserved')->sum('amount');
            if (number_format((float)$reserved, 2, '.', '') !== $wanted) {
                throw ValidationException::withMessages(['wallet' => 'Wallet reservation mismatch.']);
            }
        }
    }

    /** Release only the net stock belonging to this order. */
    public function releaseStock(Order $order, ?User $user = null): void
    {
        $order->load('items.variant');
        $groups = [];
        foreach ($order->items as $item) {
            if (!$item->variant || $item->variant->status !== VariantStatus::ACTIVE) {
                continue;
            }
            $inventory = Inventory::query()
                ->where('product_variant_id', $item->product_variant_id)
                ->lockForUpdate()->first();
            if (!$inventory) {
                throw ValidationException::withMessages(['inventory' => 'Order inventory is unavailable.']);
            }
            $groups[$inventory->id] = $inventory;
        }
        foreach ($groups as $id => $inventory) {
            $net = (int) InventoryMovement::query()->where('order_id', $order->id)
                ->where('inventory_id', $id)->sum('reserved_delta');
            if ($net < 0) {
                throw ValidationException::withMessages(['inventory' => 'Invalid reservation ledger.']);
            }
            if ($net > 0) {
                app(InventoryService::class)->releaseStock($inventory, $net, $order, $user);
            }
        }
    }
}
