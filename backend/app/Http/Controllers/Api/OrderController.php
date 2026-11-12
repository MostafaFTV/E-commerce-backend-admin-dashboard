<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderStatusHistoryResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    /**
     * Get the authenticated user's orders.
     */
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $validated = $request->validate([
            'group' => [
                'sometimes',
                'string',
                'in:current,delivered,returned,cancelled',
            ],
        ]);

        $group = $validated['group'] ?? null;

        $statuses = [
            'current' => [
                'pending_payment',
                'paid',
                'processing',
                'shipped',
            ],
            'delivered' => ['delivered'],
            'returned' => ['returned'],
            'cancelled' => ['cancelled'],
        ];

        $baseQuery = Order::query()
            ->where('user_id', $request->user()->id);

        $counts = [];

        foreach ($statuses as $key => $values) {
            $counts[$key] = (clone $baseQuery)
                ->whereIn('status', $values)
                ->count();
        }

        $ordersQuery = (clone $baseQuery)
            ->with([
                'shippingMethod',
                'items',
            ]);

        if ($group !== null) {
            $ordersQuery->whereIn(
                'status',
                $statuses[$group]
            );
        }

        $orders = $ordersQuery
            ->latest('id')
            ->paginate(20);

        return OrderResource::collection($orders)
            ->additional([
                'meta' => [
                    'group_counts' => $counts,
                ],
            ]);
    }

    /**
     * Get one order belonging to the authenticated user.
     */
    public function show(
        Request $request,
        int $order
    ): OrderResource {
        $order = Order::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->whereKey($order)
            ->with([
                'shippingMethod',
                'items',
            ])
            ->firstOrFail();

        return new OrderResource(
            $order
        );
    }

    /**
     * Get status history for one customer order.
     *
     * History is returned from oldest to newest
     * so the frontend can render it as a timeline.
     */
    public function history(
        Request $request,
        int $order
    ): AnonymousResourceCollection {
        $order = Order::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->whereKey($order)
            ->firstOrFail();

        $history = $order
            ->statusHistory()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return OrderStatusHistoryResource::collection(
            $history
        );
    }
}
