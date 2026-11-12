<?php

namespace App\Http\Resources;

use App\Enums\VariantStatus;
use App\Models\AttributeValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public bool $includeAdminInventory = false;

    public ?float $productPrice = null;

    public mixed $productDiscountPercentage = null;


    /**
     * Create a product variant resource.
     */
    public static function forProduct(
        $resource,
        float $productPrice,
        bool $includeAdminInventory = false,
        mixed $productDiscountPercentage = null
    ): self {
        $variant = new static($resource);

        $variant->productPrice =
            $productPrice;

        $variant->includeAdminInventory =
            $includeAdminInventory;

        $variant->productDiscountPercentage =
            $productDiscountPercentage;

        return $variant;
    }


    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $inventory = $this->relationLoaded('inventory')
            ? $this->inventory
            : null;

        $isMadeToOrder =
            $this->status === VariantStatus::MADE_TO_ORDER;

        $isPurchasable =
            $this->status === VariantStatus::ACTIVE
            || $this->status === VariantStatus::MADE_TO_ORDER;
        $effectivePrice = $this->price !== null
            ? (float) $this->price
            : (float) ($this->productPrice ?? $this->product?->price ?? 0);
        $discountedPrice = $isPurchasable
            ? \App\Models\Product::calculateDiscountedPrice(
                $effectivePrice,
                $this->productDiscountPercentage
            )
            : null;

        /*
        |--------------------------------------------------------------------------
        | Customer Inventory Contract
        |--------------------------------------------------------------------------
        |
        | ACTIVE:
        |   inventory.available = true / false
        |
        | MADE_TO_ORDER:
        |   inventory.available = true
        |   even when no inventory record exists
        |
        | SOLD / INACTIVE:
        |   These variants should normally not be exposed publicly.
        |
        |--------------------------------------------------------------------------
        */

        $publicInventory = null;

        if (! $this->includeAdminInventory) {
            if ($isMadeToOrder) {
                $publicInventory = [
                    'available' => true,
                ];
            } elseif ($inventory) {
                $publicInventory = [
                    'available' =>
                        $inventory->availableQuantity() > 0,
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Admin Inventory Contract
        |--------------------------------------------------------------------------
        */

        $adminInventory = null;

        if (
            $this->includeAdminInventory
            && $inventory
        ) {
            $adminInventory = [
                'id' =>
                    $inventory->id,

                'quantity' =>
                    $inventory->quantity,

                'reserved_quantity' =>
                    $inventory->reserved_quantity,

                'low_stock_threshold' =>
                    $inventory->low_stock_threshold,

                'available_quantity' =>
                    $inventory->availableQuantity(),

                'available' =>
                    $inventory->availableQuantity() > 0,
            ];
        }

        return [
            'id' => $this->id,

            'product_id' =>
                $this->product_id,

            'sku' =>
                $this->sku,

            /*
            |--------------------------------------------------------------------------
            | Pricing
            |--------------------------------------------------------------------------
            */

            'price' =>
                $this->price,

            'effective_price' =>
                $effectivePrice,

            'discounted_price' =>
                $discountedPrice,

            'discount_percentage' =>
                $discountedPrice !== null
                    ? (int) $this->productDiscountPercentage
                    : null,

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            'status' =>
                $this->status,

            'is_purchasable' =>
                $isPurchasable,

            'sort_order' =>
                $this->sort_order,

            /*
            |--------------------------------------------------------------------------
            | Attribute Values
            |--------------------------------------------------------------------------
            */

            'attribute_values' => $this->when(
                $this->relationLoaded('attributeValues'),
                function () {
                    return $this->attributeValues
                        ->map(
                            function (
                                AttributeValue $attributeValue
                            ) {
                                return [
                                    'id' =>
                                        $attributeValue->id,

                                    'attribute_id' =>
                                        $attributeValue->attribute_id,

                                    'value' =>
                                        $attributeValue->value,

                                    'attribute' =>
                                        $attributeValue->relationLoaded(
                                            'attribute'
                                        ) && $attributeValue->attribute
                                            ? [
                                                'id' =>
                                                    $attributeValue
                                                        ->attribute
                                                        ->id,

                                                'name' =>
                                                    $attributeValue
                                                        ->attribute
                                                        ->name,

                                                'slug' =>
                                                    $attributeValue
                                                        ->attribute
                                                        ->slug,

                                                'type' =>
                                                    $attributeValue
                                                        ->attribute
                                                        ->type,
                                            ]
                                            : null,
                                ];
                            }
                        )
                        ->values()
                        ->all();
                }
            ),

            /*
            |--------------------------------------------------------------------------
            | Images
            |--------------------------------------------------------------------------
            */

            'images' => $this->when(
                $this->relationLoaded('images'),
                function () {
                    return $this->images
                        ->sortBy('sort_order')
                        ->map(
                            function ($image) {
                                return [
                                    'id' =>
                                        $image->id,

                                    'product_id' =>
                                        $image->product_id,

                                    'product_variant_id' =>
                                        $image->product_variant_id,

                                    'attribute_value_id' =>
                                        $image->attribute_value_id,

                                    'image_path' =>
                                        $image->image_path,

                                    'alt_text' =>
                                        $image->alt_text,

                                    'sort_order' =>
                                        $image->sort_order,

                                    'is_primary' =>
                                        (bool) $image->is_primary,
                                ];
                            }
                        )
                        ->values()
                        ->all();
                }
            ),

            /*
            |--------------------------------------------------------------------------
            | Inventory
            |--------------------------------------------------------------------------
            */

            'inventory' => $this->includeAdminInventory
                ? $adminInventory
                : $publicInventory,

            /*
            |--------------------------------------------------------------------------
            | Timestamps
            |--------------------------------------------------------------------------
            */

            'created_at' =>
                $this->created_at,

            'updated_at' =>
                $this->updated_at,
        ];
    }
}
