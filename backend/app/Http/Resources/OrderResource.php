<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'order_number' => $this->order_number,

            'status' => $this->status?->value,

            'subtotal' => (float) $this->subtotal,

            'discount' => (float) $this->discount,

            'shipping_cost' => (float) $this->shipping_cost,

            'tax' => (float) $this->tax,

            'total' => (float) $this->total,

            'currency' => $this->currency,

            'shipping_method' => $this->when(
                $this->relationLoaded('shippingMethod')
                && $this->shippingMethod,
                fn () => [
                    'id' => $this->shippingMethod->id,
                    'name' => $this->shippingMethod->name,
                    'price' => (float) $this->shippingMethod->price,
                    'estimated_days' => $this->shippingMethod->estimated_days,
                ]
            ),

            'shipping_address' => $this->shipping_address,

            'billing_address' => $this->billing_address,

            'items' => $this->when(
                $this->relationLoaded('items'),
                fn () => OrderItemResource::collection(
                    $this->items
                )
            ),

            'payments' => $this->when(
                $this->relationLoaded('payments'),
                fn () => PaymentResource::collection(
                    $this->payments
                )
            ),

            'status_history' => $this->when(
                $this->relationLoaded('statusHistory'),
                fn () => OrderStatusHistoryResource::collection(
                    $this->statusHistory
                )
            ),

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}
