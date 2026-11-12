<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->relationLoaded('items') ? $this->items : collect();

        return [
            'id' => $this->id,
            'items' => CartItemResource::collection($items),
            'item_count' => $items->sum('quantity'),
            'subtotal' => (float) $items->sum(fn ($item) => $item->quantity * (float) $item->price_snapshot),
        ];
    }
}
