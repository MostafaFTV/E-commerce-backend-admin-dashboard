<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingMethodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'name' => $this->name,

            'description' => $this->description,

            'price' => (float) $this->price,

            'estimated_days' => $this->estimated_days,

            'is_active' => (bool) $this->is_active,

            'sort_order' => $this->sort_order,

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}
