<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderStatusHistoryResource;
use App\Models\Order;
use App\Services\OrderStatusService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(
        protected OrderStatusService $orderStatusService
    ) {}

    /**
     * List all orders for the admin panel.
     */
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $query = Order::query()
            ->with([
                'user',
                'shippingMethod',
                'items',
                'payments',
                'statusHistory',
            ])
            ->latest('id');

        /*
        |--------------------------------------------------------------------------
        | Filters
        |--------------------------------------------------------------------------
        */

        if ($request->filled('status')) {
            $status = $request
                ->string('status')
                ->toString();

            if (OrderStatus::tryFrom($status)) {
                $query->where(
                    'status',
                    $status
                );
            }
        }

        if ($request->filled('order_number')) {
            $orderNumber = $request
                ->string('order_number')
                ->toString();

            $query->where(
                'order_number',
                'like',
                "%{$orderNumber}%"
            );
        }

        if ($request->filled('user_id')) {
            $query->where(
                'user_id',
                (int) $request->input('user_id')
            );
        }

        return OrderResource::collection(
            $query->paginate(
                (int) $request->input(
                    'per_page',
                    20
                )
            )
        );
    }

    /**
     * Show one order in the admin panel.
     */
    public function show(
        Order $order
    ): OrderResource {
        $order->load([
            'user',
            'shippingMethod',
            'items',
            'payments',
            'statusHistory.changedBy',
        ]);

        return new OrderResource(
            $order
        );
    }

    /**
     * Change order status.
     */
    public function updateStatus(
        Request $request,
        Order $order
    ): OrderResource {
        $validated = $request->validate([
            'status' => [
                'required',
                Rule::enum(OrderStatus::class),
            ],

            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $newStatus = OrderStatus::from(
            $validated['status']
        );

        $changedOrder =
            $this->orderStatusService->changeStatus(
                $order,
                $newStatus,
                $request->user(),
                $validated['note'] ?? null
            );

        $changedOrder->load([
            'user',
            'shippingMethod',
            'items',
            'payments',
            'statusHistory.changedBy',
        ]);

        return new OrderResource(
            $changedOrder
        );
    }

    /**
     * Cancel an order.
     */
    public function cancel(
        Request $request,
        Order $order
    ): OrderResource {
        $validated = $request->validate([
            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $changedOrder =
            $this->orderStatusService->changeStatus(
                $order,
                OrderStatus::CANCELLED,
                $request->user(),
                $validated['note'] ?? null
            );

        $changedOrder->load([
            'user',
            'shippingMethod',
            'items',
            'payments',
            'statusHistory.changedBy',
        ]);

        return new OrderResource(
            $changedOrder
        );
    }

    /**
     * Return an order.
     */
    public function returnOrder(
        Request $request,
        Order $order
    ): OrderResource {
        $validated = $request->validate([
            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $changedOrder =
            $this->orderStatusService->changeStatus(
                $order,
                OrderStatus::RETURNED,
                $request->user(),
                $validated['note'] ?? null
            );

        $changedOrder->load([
            'user',
            'shippingMethod',
            'items',
            'payments',
            'statusHistory.changedBy',
        ]);

        return new OrderResource(
            $changedOrder
        );
    }

    /**
     * Get order status history.
     */
    public function history(
        Order $order
    ): AnonymousResourceCollection {
        $history = $order
            ->statusHistory()
            ->with('changedBy')
            ->latest('created_at')
            ->get();

        return OrderStatusHistoryResource::collection(
            $history
        );
    }
}
