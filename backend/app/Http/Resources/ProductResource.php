<?php

namespace App\Http\Resources;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public bool $includeAdminInventory = false;

    /**
     * Create an admin resource with inventory details enabled.
     */
    public static function admin($resource): self
    {
        $product = new static($resource);
        $product->includeAdminInventory = true;

        return $product;
    }

    /**
     * Create an admin collection with inventory details enabled.
     */
    public static function adminCollection($resource)
    {
        $collection = static::collection($resource);

        $collection->collection->each(
            function (self $product): void {
                $product->includeAdminInventory = true;
            }
        );

        return $collection;
    }

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        /*
        |--------------------------------------------------------------------------
        | Variant Visibility
        |--------------------------------------------------------------------------
        |
        | Public API:
        | - active
        | - made_to_order
        |
        | Admin API:
        | - all variants
        |
        |--------------------------------------------------------------------------
        */

        $allVariants = $this->relationLoaded('variants')
            ? $this->variants
            : collect();
        $attributes = $this->resource->getAttributes();

        $visibleVariants = $this->includeAdminInventory
            ? $allVariants
            : $allVariants->filter(
                fn ($variant) =>
                    in_array(
                        $variant->status,
                        [
                            VariantStatus::ACTIVE,
                            VariantStatus::MADE_TO_ORDER,
                        ],
                        true
                    )
            );
        $hasAnyVariant = array_key_exists(
            'variants_count',
            $attributes
        )
            ? (int) $this->variants_count > 0
            : $allVariants->isNotEmpty();

        /*
        |--------------------------------------------------------------------------
        | Public Price Range
        |--------------------------------------------------------------------------
        */

        $pricedVariants = $visibleVariants->filter(
            fn ($variant) => in_array(
                $variant->status,
                [
                    VariantStatus::ACTIVE,
                    VariantStatus::MADE_TO_ORDER,
                ],
                true
            )
        );

        $effectiveVariantPrices = $pricedVariants->map(
            fn ($variant) => $variant->price !== null
                ? (float) $variant->price
                : (float) $this->price
        );

        $minPrice = (float) (
            $this->min_price
            ?? (
                $effectiveVariantPrices->isNotEmpty()
                    ? $effectiveVariantPrices->min()
                    : (float) $this->price
            )
        );

        $maxPrice = (float) (
            $this->max_price
            ?? (
                $effectiveVariantPrices->isNotEmpty()
                    ? $effectiveVariantPrices->max()
                    : (float) $this->price
            )
        );

        $hasPriceRange = $minPrice !== $maxPrice;

        $discountPercentage = $this->discount_percentage;
        $discountedVariantPrices = $visibleVariants
            ->filter(
                fn ($variant) =>
                    in_array(
                        $variant->status,
                        [
                            VariantStatus::ACTIVE,
                            VariantStatus::MADE_TO_ORDER,
                        ],
                        true
                    )
            )
            ->map(
                fn ($variant) => \App\Models\Product::calculateDiscountedPrice(
                    $variant->price !== null
                        ? (float) $variant->price
                        : (float) $this->price,
                    $discountPercentage
                )
            )
            ->filter(fn ($price) => $price !== null)
            ->values();
        $discountedProductPrice = ! $hasAnyVariant
            ? \App\Models\Product::calculateDiscountedPrice(
                (float) $this->price,
                $discountPercentage
            )
            : $discountedVariantPrices->min();

        /*
        |--------------------------------------------------------------------------
        | Inventory
        |--------------------------------------------------------------------------
        */

        $hasPreparedInventory =
            array_key_exists(
                'total_inventory',
                $attributes
            )
            && array_key_exists(
                'available_inventory',
                $attributes
            );

        if ($hasPreparedInventory) {
            $inventoryTotals = [
                'total' => (int) $this->total_inventory,
                'available' => (int) $this->available_inventory,
            ];
        } elseif ($allVariants->isNotEmpty()) {
            $inventoryTotals = $allVariants->reduce(
                function (
                    array $totals,
                    $variant
                ): array {

                    if (
                        $variant->status !==
                        VariantStatus::ACTIVE
                    ) {
                        return $totals;
                    }

                    if (
                        ! $variant->relationLoaded('inventory')
                        || ! $variant->inventory
                    ) {
                        return $totals;
                    }

                    $totals['total'] +=
                        (int) $variant->inventory->quantity;

                    $totals['available'] += max(
                        (int) $variant->inventory->quantity
                            - (int) $variant->inventory->reserved_quantity,
                        0
                    );

                    return $totals;
                },
                [
                    'total' => 0,
                    'available' => 0,
                ]
            );
        } else {
            $inventoryTotals = [
                'total' => 0,
                'available' => 0,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Purchase State
        |--------------------------------------------------------------------------
        */

        $hasAvailableInventory =
            $inventoryTotals['available'] > 0;

        $hasMadeToOrderVariant = $visibleVariants->contains(
            fn ($variant) =>
                $variant->status ===
                VariantStatus::MADE_TO_ORDER
        );

        $hasPurchasableVariant = $visibleVariants->contains(
            fn ($variant) =>
                method_exists(
                    $variant,
                    'isPurchasable'
                )
                    ? $variant->isPurchasable()
                    : in_array(
                        $variant->status,
                        [
                            VariantStatus::ACTIVE,
                            VariantStatus::MADE_TO_ORDER,
                        ],
                        true
                    )
        );

        $onlineCheckoutEnabled = (bool) config(
            'storefront.online_checkout_enabled',
            false
        );

        /*
        |--------------------------------------------------------------------------
        | Product Gallery
        |--------------------------------------------------------------------------
        */

        $galleryImages =
            $this->relationLoaded('galleryImages')
                ? $this->galleryImages
                : collect();

        $generalImages =
            $this->relationLoaded('galleryImages')
                ? $galleryImages->filter(
                    fn ($image) =>
                        $image->attribute_value_id === null
                )
                : (
                    $this->relationLoaded('images')
                        ? $this->images
                        : collect()
                );

        $visibleColorValueIds =
            $visibleVariants
                ->flatMap(
                    function ($variant) {
                        if (
                            ! $variant->relationLoaded(
                                'attributeValues'
                            )
                        ) {
                            return [];
                        }

                        return $variant
                            ->attributeValues
                            ->filter(
                                function ($attributeValue) {
                                    return $attributeValue
                                        ->relationLoaded(
                                            'attribute'
                                        )
                                        && $attributeValue
                                            ->attribute
                                        && $attributeValue
                                            ->attribute
                                            ->type === 'color';
                                }
                            )
                            ->pluck('id')
                            ->all();
                    }
                )
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->unique()
                ->values();

        $colorImages =
            $galleryImages
                ->filter(
                    fn ($image) =>
                        $image->attribute_value_id !== null
                );

        if (! $this->includeAdminInventory) {
            $colorImages =
                $colorImages->filter(
                    fn ($image) =>
                        $visibleColorValueIds->contains(
                            (int) $image->attribute_value_id
                        )
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return [
            'id' => $this->id,

            'category_id' => $this->category_id,

            /*
            |--------------------------------------------------------------------------
            | Localization
            |--------------------------------------------------------------------------
            */

            'locale' => app()->getLocale(),

            'direction' => $request->attributes->get(
                'direction',
                'ltr'
            ),

            /*
            |--------------------------------------------------------------------------
            | Basic Information
            |--------------------------------------------------------------------------
            */

            'name' => $this->localizedFieldForResponse('name'),

            'slug' => $this->slug,

            'short_description' =>
                $this->localizedFieldForResponse(
                    'short_description'
                ),

            'description' =>
                $this->localizedFieldForResponse(
                    'description'
                ),

            'brand' =>
                $this->brand,

            /*
            |--------------------------------------------------------------------------
            | SEO Metadata
            |--------------------------------------------------------------------------
            */
            'seo_title' =>
                $this->localizedFieldForResponse('seo_title'),

            'seo_description' =>
                $this->localizedFieldForResponse('seo_description'),

            'seo_image' =>
                $this->seo_image,

            /*
            |--------------------------------------------------------------------------
            | Pricing
            |--------------------------------------------------------------------------
            */

            'base_price' => $this->when(
                $this->includeAdminInventory,
                fn () => (float) $this->resource->getRawOriginal(
                    'price'
                )
            ),

            'price' =>
                $minPrice,

            'min_price' =>
                $minPrice,

            'max_price' =>
                $maxPrice,

            'has_price_range' =>
                $hasPriceRange,

            'discount_percentage' =>
                $this->includeAdminInventory
                    ? $discountPercentage
                    : ($discountedProductPrice !== null
                        ? (int) $discountPercentage
                        : null),

            'discounted_price' =>
                $discountedProductPrice !== null
                    ? (float) $discountedProductPrice
                    : null,

            'min_discounted_price' =>
                $discountedProductPrice !== null
                    ? (float) $discountedProductPrice
                    : null,

            'max_discounted_price' =>
                $discountedVariantPrices->isNotEmpty()
                    ? (float) $discountedVariantPrices->max()
                    : $discountedProductPrice,

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            'status' =>
                $this->status,

            'is_featured' =>
                (bool) $this->is_featured,

            /*
            |--------------------------------------------------------------------------
            | Purchase State
            |--------------------------------------------------------------------------
            */

            'has_made_to_order_variant' =>
                $hasMadeToOrderVariant,

            'has_available_inventory' =>
                $hasAvailableInventory,

            'can_purchase' =>
                $this->status === ProductStatus::ACTIVE
                && (
                    $hasPurchasableVariant
                    || (
                        ! $hasAnyVariant
                        && ! $onlineCheckoutEnabled
                    )
                ),

            /*
            |--------------------------------------------------------------------------
            | Category
            |--------------------------------------------------------------------------
            */

            'category' => $this->when(
                $this->relationLoaded('category')
                && $this->category,
                fn () => [
                    'id' =>
                        $this->category->id,

                    'name' =>
                        $this->category->name,

                    'slug' =>
                        $this->category->slug,
                ]
            ),

            /*
            |--------------------------------------------------------------------------
            | Collections
            |--------------------------------------------------------------------------
            */

            'collections' => $this->when(
                $this->relationLoaded('collections'),
                fn () => $this->collections
                    ->map(
                        fn ($collection) => [
                            'id' =>
                                $collection->id,

                            'name' =>
                                $collection->name,

                            'slug' =>
                                $collection->slug,
                        ]
                    )
                    ->values()
                    ->all()
            ),

            /*
            |--------------------------------------------------------------------------
            | Images
            |--------------------------------------------------------------------------
            */

            'images' => $this->when(
                $this->relationLoaded('images')
                || $this->relationLoaded('galleryImages'),
                fn () => $generalImages
                    ->sortBy('sort_order')
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
                                (bool) $image->is_primary,
                        ]
                    )
                    ->values()
                    ->all()
            ),

            'color_images' => $this->when(
                $this->relationLoaded('galleryImages'),
                fn () => $colorImages
                    ->sortBy('sort_order')
                    ->map(
                        fn ($image) => [
                            'id' =>
                                $image->id,

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
                        ]
                    )
                    ->values()
                    ->all()
            ),

            /*
            |--------------------------------------------------------------------------
            | Variants
            |--------------------------------------------------------------------------
            |
            | Public:
            |   active / made_to_order
            |
            | Admin:
            |   all variants
            |
            |--------------------------------------------------------------------------
            */

            'variants' => $this->when(
                $this->relationLoaded('variants'),
                fn () => $visibleVariants
                    ->map(
                        fn ($variant) =>
                            ProductVariantResource::forProduct(
                                $variant,
                                (float) $this->price,
                                $this->includeAdminInventory,
                                $discountPercentage
                            )
                    )
                    ->values()
                    ->all()
            ),

            /*
            |--------------------------------------------------------------------------
            | Inventory
            |--------------------------------------------------------------------------
            |
            | Numeric inventory is admin-only.
            |
            |--------------------------------------------------------------------------
            */

            'total_inventory' =>
                $this->includeAdminInventory
                    ? $inventoryTotals['total']
                    : null,

            'available_inventory' =>
                $this->includeAdminInventory
                    ? $inventoryTotals['available']
                    : null,

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

    /**
     * Public responses keep the localized string.
     * Admin responses expose the complete translation map
     * so the administration UI can edit every locale.
     */
    protected function localizedFieldForResponse(
        string $field
    ): mixed {
        if (! $this->includeAdminInventory) {
            return $this->{$field};
        }

        $raw = $this->resource->getRawOriginal(
            $field
        );

        if ($raw === null) {
            return null;
        }

        if (is_array($raw)) {
            return $raw;
        }

        if (
            ! is_string($raw)
            || trim($raw) === ''
        ) {
            return null;
        }

        $decoded = json_decode(
            $raw,
            true
        );

        if (is_array($decoded)) {
            return $decoded;
        }

        return $this->{$field};
    }}
