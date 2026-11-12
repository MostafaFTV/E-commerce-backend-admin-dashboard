<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ContactMessage;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'filter' => ['sometimes', 'in:all,unread,read'],
        ]);
        $user = $request->user();
        $permissions = $this->permissionsFor($user);
        $items = $this->notificationItemsFor($permissions);
        $readIds = $this->readNotificationIds(
            (int) $user->id,
            $items->pluck('id')
        );

        $items = $items
            ->map(function (array $item) use ($readIds) {
                $item['is_read'] = ($item['is_read'] ?? false)
                    || $readIds->has($item['id']);

                return $item;
            })
            ->sortByDesc('created_at')
            ->values();
        $unreadCount = $items
            ->filter(fn (array $item) => ! $item['is_read'])
            ->sum(fn (array $item) => $item['unread_count'] ?? 1);
        $filter = $validated['filter'] ?? 'all';
        $filteredItems = match ($filter) {
            'unread' => $items->where('is_read', false)->values(),
            'read' => $items->where('is_read', true)->values(),
            default => $items,
        };
        $pageSize = (int) ($validated['per_page'] ?? 20);
        $page = (int) ($validated['page'] ?? 1);
        $total = $filteredItems->count();

        return response()->json([
            'count' => $unreadCount,
            'items' => $filteredItems
                ->forPage($page, $pageSize)
                ->values(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $pageSize,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $pageSize)),
            ],
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $permissions = $this->permissionsFor($user);
        $notificationIds = $this->notificationItemsFor($permissions)->pluck('id');
        $readAt = now();

        DB::transaction(function () use ($notificationIds, $user, $readAt) {
            foreach ($notificationIds->chunk(500) as $chunk) {
                DB::table('admin_notification_reads')->insertOrIgnore(
                    $chunk->map(fn (string $notificationId) => [
                        'user_id' => $user->id,
                        'notification_id' => $notificationId,
                        'read_at' => $readAt,
                    ])->all()
                );
            }
        });

        return response()->json([
            'marked_count' => $notificationIds->count(),
        ]);
    }

    private function permissionsFor(User $user): Collection
    {
        $permissions = $user->roles()
            ->with('permissions:id,slug')
            ->get()
            ->flatMap(fn ($role) => $role->permissions->pluck('slug'))
            ->unique()
            ->flip();

        abort_unless(
            $permissions->has('contact_messages.view')
                || $permissions->has('orders.view')
                || $permissions->has('inventory.view'),
            403,
            'You do not have permission to view notifications.'
        );

        return $permissions;
    }

    private function notificationItemsFor(Collection $permissions): Collection
    {
        $items = collect();

        if ($permissions->has('contact_messages.view')) {
            $items = $items->concat(
                ContactMessage::query()
                    ->latest('id')
                    ->get(['id', 'full_name', 'subject', 'status', 'created_at'])
                    ->map(fn (ContactMessage $message) => [
                        'id' => "contact-{$message->id}",
                        'type' => 'contact_message',
                        'title' => 'پیام تماس جدید',
                        'description' => "{$message->full_name} · {$this->subjectLabel($message->subject)}",
                        'href' => "/contact-messages/{$message->id}",
                        'created_at' => $message->created_at?->toISOString(),
                        'is_read' => $message->status !== 'new',
                    ])
            );

            DB::table('support_ticket_messages as ticket_messages')
                ->join('support_tickets as tickets', 'tickets.id', '=', 'ticket_messages.support_ticket_id')
                ->join('users', 'users.id', '=', 'tickets.customer_id')
                ->where('ticket_messages.sender_role', 'customer')
                ->get([
                    'tickets.id as ticket_id',
                    'tickets.subject',
                    'tickets.status as ticket_status',
                    'tickets.admin_last_read_message_id',
                    'ticket_messages.id as message_id',
                    'ticket_messages.created_at',
                    'users.first_name',
                    'users.last_name',
                ])
                ->each(function ($message) use (&$items) {
                    $customerName = trim(
                        ($message->first_name ?? '') . ' ' .
                        ($message->last_name ?? '')
                    );
                    $items->push([
                        'id' => "support-ticket-{$message->ticket_id}-message-{$message->message_id}",
                        'type' => 'support_ticket',
                        'title' => 'پیام تیکت پشتیبانی',
                        'description' => "{$customerName} · {$this->subjectLabel($message->subject)}",
                        'href' => "/support-tickets/{$message->ticket_id}",
                        'created_at' => $message->created_at
                            ? \Illuminate\Support\Carbon::parse($message->created_at)->toISOString()
                            : null,
                        'is_read' => $message->ticket_status !== 'open'
                            || (
                                $message->admin_last_read_message_id !== null
                                && $message->message_id <= $message->admin_last_read_message_id
                            ),
                    ]);
                });
        }

        if ($permissions->has('orders.view')) {
            $recentOrderIds = ActivityLog::query()
                ->where('action', 'orders.created')
                ->latest('id')
                ->get(['properties'])
                ->map(fn (ActivityLog $log) => data_get($log->properties, 'order.id')
                    ?? data_get($log->properties, 'subject.id'))
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $reconciliationOrderIds = DB::table('payment_reconciliations')
                ->where('status', 'requires_review')
                ->select('order_id');

            $orders = Order::query()
                ->where(function ($query) use ($reconciliationOrderIds, $recentOrderIds) {
                    $query->where('status', 'pending_payment')
                        ->orWhereIn('id', $reconciliationOrderIds);

                    if ($recentOrderIds->isNotEmpty()) {
                        $query->orWhereIn('id', $recentOrderIds);
                    }
                })
                ->get(['id', 'order_number', 'created_at']);
            $orderIds = $orders->pluck('id');
            $latestPayments = Payment::query()
                ->whereIn('order_id', $orderIds)
                ->orderByDesc('id')
                ->get(['id', 'order_id', 'status', 'created_at', 'updated_at'])
                ->unique('order_id')
                ->keyBy('order_id');
            $reviewOrderIds = DB::table('payment_reconciliations')
                ->where('status', 'requires_review')
                ->whereIn('order_id', $orderIds)
                ->pluck('order_id')
                ->flip();

            $items = $items->concat(
                $orders->map(function (Order $order) use ($latestPayments, $reviewOrderIds) {
                    $latestPayment = $latestPayments->get($order->id);
                    $requiresReview = $reviewOrderIds->has($order->id);
                    $paymentFailed = $latestPayment?->status?->value === 'failed';

                    return [
                        'id' => $this->orderNotificationId($order, $latestPayment, $requiresReview),
                        'type' => $requiresReview
                            ? 'payment_reconciliation'
                            : ($paymentFailed ? 'payment_failed' : 'order'),
                        'title' => $requiresReview
                            ? 'نیاز به بررسی پرداخت'
                            : ($paymentFailed ? 'پرداخت ناموفق' : 'سفارش جدید'),
                        'description' => $order->order_number,
                        'href' => "/orders/{$order->id}",
                        'created_at' => $latestPayment?->created_at?->toISOString()
                            ?? $order->created_at?->toISOString(),
                    ];
                })
            );
        }

        if ($permissions->has('inventory.view')) {
            $activeVariant = fn ($query) => $query
                ->where('status', 'active')
                ->whereHas('product', fn ($productQuery) => $productQuery
                    ->where('status', 'active'));

            $restocked = $this->recentRestocks();
            $restockedInventoryIds = $restocked->pluck('inventory_id')->flip();

            $outOfStock = Inventory::query()
                ->whereHas('variant', $activeVariant)
                ->whereRaw('quantity <= reserved_quantity')
                ->whereNotIn('id', $restockedInventoryIds->keys())
                ->with('variant:id,sku')
                ->get();
            $lowStock = Inventory::query()
                ->whereHas('variant', $activeVariant)
                ->whereRaw('quantity > reserved_quantity')
                ->whereRaw('(quantity - reserved_quantity) <= low_stock_threshold')
                ->whereNotIn('id', $restockedInventoryIds->keys())
                ->with('variant:id,sku')
                ->get();

            $outOfStock
                ->merge($lowStock)
                ->each(function (Inventory $inventory) use ($outOfStock, &$items) {
                    $type = $outOfStock->contains('id', $inventory->id)
                        ? 'out_of_stock'
                        : 'low_stock';
                    $items->push($this->inventoryNotification($inventory, $type));
                });

            $items = $items->concat($this->restockNotifications($restocked));
        }

        return $items->values();
    }

    private function readNotificationIds(int $userId, Collection $notificationIds): Collection
    {
        return $notificationIds
            ->chunk(500)
            ->flatMap(fn (Collection $chunk) => DB::table('admin_notification_reads')
                ->where('user_id', $userId)
                ->whereIn('notification_id', $chunk)
                ->pluck('notification_id'))
            ->flip();
    }

    private function orderNotificationId(
        Order $order,
        ?Payment $payment,
        bool $requiresReview
    ): string {
        $paymentStatus = $payment?->status?->value ?? 'none';
        $eventType = $requiresReview
            ? 'payment_reconciliation'
            : ($paymentStatus === 'failed' ? 'payment_failed' : 'order');
        $paymentId = $payment?->id ?? 'none';
        $paymentUpdatedAt = $payment?->updated_at?->getTimestamp() ?? 0;

        return "order-{$order->id}-{$eventType}-payment-{$paymentId}-{$paymentStatus}-{$paymentUpdatedAt}";
    }

    private function recentRestocks(): Collection
    {
        $logs = ActivityLog::query()
            ->whereIn('action', [
                'inventory.stock_added',
                'inventory.stock_adjusted',
                'inventory.stock_returned',
                'inventory.stock_released',
                'inventory.stock_reserved',
                'inventory.stock_sold',
            ])
            ->latest('id')
            ->get(['id', 'properties', 'created_at']);

        $seen = [];

        return $logs->filter(function (ActivityLog $log) use (&$seen) {
            $inventoryId = data_get($log->properties, 'inventory.id');
            $before = data_get($log->properties, 'available_quantity_before');
            $after = data_get($log->properties, 'available_quantity_after');

            if (
                ! is_numeric($inventoryId) ||
                ! is_numeric($before) ||
                ! is_numeric($after) ||
                ((int) $before > 0) === ((int) $after > 0) ||
                isset($seen[$inventoryId])
            ) {
                return false;
            }

            $seen[$inventoryId] = true;
            if ((int) $after <= 0) {
                return false;
            }

            $log->setAttribute('inventory_id', (int) $inventoryId);

            return true;
        })->values();
    }

    private function restockNotifications(Collection $logs): Collection
    {
        $inventories = Inventory::query()
            ->with('variant:id,sku')
            ->whereIn('id', $logs->pluck('inventory_id'))
            ->get()
            ->keyBy('id');

        return $logs->map(function (ActivityLog $log) use ($inventories) {
            $inventory = $inventories->get($log->inventory_id);
            $sku = $inventory?->variant?->sku ?? "Inventory {$log->inventory_id}";

            return [
                'id' => "stock-restocked-{$log->id}",
                'type' => 'stock_replenished',
                'title' => 'موجودی پس از اتمام شارژ شد',
                'description' => "{$sku} · موجودی قابل فروش " . data_get($log->properties, 'available_quantity_after'),
                'href' => "/inventory/{$log->inventory_id}",
                'created_at' => $log->created_at?->toISOString(),
            ];
        });
    }

    private function inventoryNotification(Inventory $inventory, string $type): array
    {
        return [
            'id' => $this->inventoryNotificationId($inventory, $type),
            'type' => $type,
            'title' => $type === 'out_of_stock'
                ? 'موجودی کالا تمام شده است'
                : 'موجودی کالا کم است',
            'description' => ($inventory->variant?->sku ?? "Inventory {$inventory->id}")
                . ' · موجودی قابل فروش ' . $inventory->availableQuantity(),
            'href' => "/inventory/{$inventory->id}",
            'created_at' => $inventory->updated_at?->toISOString(),
        ];
    }

    private function inventoryNotificationId(Inventory $inventory, string $type): string
    {
        return "stock-{$type}-{$inventory->id}-"
            . ($inventory->updated_at?->getTimestamp() ?? 0);
    }

    private function subjectLabel(string $subject): string
    {
        return [
            'product' => 'محصول',
            'order' => 'سفارش',
            'delivery' => 'ارسال',
            'payment' => 'پرداخت',
            'returns' => 'مرجوعی',
            'press' => 'رسانه‌ها',
            'other' => 'سایر',
        ][$subject] ?? $subject;
    }
}
