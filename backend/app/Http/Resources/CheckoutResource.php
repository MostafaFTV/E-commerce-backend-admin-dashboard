<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckoutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'status' => $this->status->value,

            'shipping_address' => $this->shipping_address,

            'billing_address' => $this->billing_address,

            'shipping_method_id' => $this->shipping_method_id,

            'expires_at' => $this->expires_at?->toISOString(),

            'cart' => [
                'id' => $this->cart->id,

                'items' => CartItemResource::collection(
                    $this->cart->items
                ),

                'subtotal' => $this->cart->calculateSubtotal(),
            ],
        ];
    }
}