<?php

namespace App\Http\Resources;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variant = $this->variant;
        $product = $variant?->product
            ?? $this->product;
        $inventory = $variant?->inventory;

        $available = false;

        $onlineCheckoutEnabled = (bool) config(
            'storefront.online_checkout_enabled',
            false
        );

        if (
            $product?->status === ProductStatus::ACTIVE
            && $variant !== null
        ) {
            /*
             * MADE_TO_ORDER variants do not require inventory.
             */
            if ($variant->status === VariantStatus::MADE_TO_ORDER) {
                $available = true;
            }

            /*
             * ACTIVE variants:
             *
             * Contact checkout mode:
             * - selectable
             * - addable to cart
             * - inventory does not block cart availability
             *
             * Online checkout mode:
             * - inventory record is required
             * - requested quantity must be available
             */
            if ($variant->status === VariantStatus::ACTIVE) {
                if (! $onlineCheckoutEnabled) {
                    $available = true;
                } else {
                    $availableQuantity =
                        $inventory?->availableQuantity()
                        ?? 0;

                    $available =
                        $inventory !== null
                        && $availableQuantity >= $this->quantity;
                }
            }
        }


        /*
         * Products without variants are available only in the current
         * contact-checkout mode. No inventory quantity is exposed.
         */
        if (
            $product?->status === ProductStatus::ACTIVE
            && $variant === null
            && ! $onlineCheckoutEnabled
        ) {
            $available = true;
        }

        return [
            'id' => $this->id,

            'product_variant' => $variant ? [
                'id' => $variant->id,
                'product_id' => $variant->product_id,
                'sku' => $variant->sku,
                'price' => $variant->price,

                'effective_price' =>
                    $variant->price !== null
                        ? (float) $variant->price
                        : (float) ($product?->price ?? 0),

                'status' => $variant->status,

                'sort_order' =>
                    $variant->sort_order,

                'attribute_values' =>
                    $variant->relationLoaded(
                        'attributeValues'
                    )
                        ? $variant->attributeValues
                            ->map(
                                fn ($value) => [
                                    'id' =>
                                        $value->id,

                                    'attribute_id' =>
                                        $value->attribute_id,

                                    'value' =>
                                        $value->value,

                                    'attribute' =>
                                        $value->relationLoaded(
                                            'attribute'
                                        )
                                        && $value->attribute
                                            ? [
                                                'id' =>
                                                    $value
                                                        ->attribute
                                                        ->id,

                                                'name' =>
                                                    $value
                                                        ->attribute
                                                        ->name,

                                                'slug' =>
                                                    $value
                                                        ->attribute
                                                        ->slug,

                                                'type' =>
                                                    $value
                                                        ->attribute
                                                        ->type,
                                            ]
                                            : null,
                                ]
                            )
                            ->values()
                            ->all()
                        : [],

                'images' =>
                    $variant->relationLoaded(
                        'images'
                    )
                        ? $variant->images
                            ->map(
                                fn ($image) => [
                                    'id' =>
                                        $image->id,

                                    'image_path' =>
                                        $image->image_path,

                                    'alt_text' =>
                                        $image->alt_text,

                                    'sort_order' =>
                                        $image->sort_order,

                                    'is_primary' =>
                                        $image->is_primary,
                                ]
                            )
                            ->values()
                            ->all()
                        : [],
            ] : null,

            'product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'brand' => $product->brand,
            ] : null,

            'quantity' =>
                $this->quantity,

            'price_snapshot' =>
                (float) $this->price_snapshot,

            'line_total' =>
                $this->subtotal(),

            'availability' => [
                'available' =>
                    $available,
            ],
        ];
    }
}
