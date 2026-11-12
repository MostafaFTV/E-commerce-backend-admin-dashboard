<?php

namespace App\Http\Resources;

use App\Enums\ProductStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->getAttributes();

        $hasAvailableInventory = array_key_exists(
            'has_available_inventory',
            $attributes
        )
            ? (bool) $this->has_available_inventory
            : false;

        $hasMadeToOrderVariant = array_key_exists(
            'has_made_to_order_variant',
            $attributes
        )
            ? (bool) $this->has_made_to_order_variant
            : false;

        $hasPurchasableVariant = array_key_exists(
            'has_purchasable_variant',
            $attributes
        )
            ? (bool) $this->has_purchasable_variant
            : false;

        $hasAnyVariant = array_key_exists(
            'has_any_variant',
            $attributes
        )
            ? (bool) $this->has_any_variant
            : false;

        $onlineCheckoutEnabled = (bool) config(
            'storefront.online_checkout_enabled',
            false
        );

        /*
         * Product status is cast to ProductStatus enum
         * on the Product model, so compare against the enum itself.
         *
         * Products without variants remain purchasable in the current
         * contact-checkout mode. Products that do have variants keep
         * using the existing purchasable-variant rules.
         */
        $canPurchase =
            $this->status === ProductStatus::ACTIVE
            && (
                $hasPurchasableVariant
                || (
                    ! $hasAnyVariant
                    && ! $onlineCheckoutEnabled
                )
            );

        $minPrice = (float) (
            $this->min_price
            ?? $this->price
            ?? 0
        );

        $maxPrice = (float) (
            $this->max_price
            ?? $this->price
            ?? 0
        );
        $candidateDiscountedPrice = $this->min_discounted_price !== null
            ? (float) $this->min_discounted_price
            : null;
        $discountPercentage = (int) ($this->discount_percentage ?? 0);
        $discountedPrice = $candidateDiscountedPrice !== null
            && $discountPercentage >= 1
            && $discountPercentage <= 100
            && $candidateDiscountedPrice < $minPrice
                ? $candidateDiscountedPrice
                : null;

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,

            'locale' => app()->getLocale(),

            'direction' => $request->attributes->get(
                'direction',
                'ltr'
            ),

            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'brand' => $this->brand,

            /*
             * -------------------------------------------------------
             * Pricing
             * -------------------------------------------------------
             */

            'price' => $minPrice,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'discount_percentage' => $discountedPrice !== null
                ? (int) $this->discount_percentage
                : null,
            'discounted_price' => $discountedPrice,
            'min_discounted_price' => $discountedPrice,
            'max_discounted_price' => $discountedPrice !== null
                && $this->max_discounted_price !== null
                ? (float) $this->max_discounted_price
                : null,

            'has_price_range' =>
                $minPrice !== $maxPrice,

            /*
             * -------------------------------------------------------
             * Product State
             * -------------------------------------------------------
             */

            'status' => $this->status,

            'is_featured' => (bool) $this->is_featured,

            'has_made_to_order_variant' =>
                $hasMadeToOrderVariant,

            'has_purchasable_variant' =>
                $hasPurchasableVariant,

            'has_available_inventory' =>
                $hasAvailableInventory,

            'can_purchase' =>
                $canPurchase,

            /*
             * -------------------------------------------------------
             * Category
             * -------------------------------------------------------
             */

            'category' => $this->when(
                $this->relationLoaded('category')
                && $this->category,
                fn () => [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ]
            ),

            /*
             * -------------------------------------------------------
             * Collections
             * -------------------------------------------------------
             */

            'collections' => $this->when(
                $this->relationLoaded('collections'),
                fn () => $this->collections
                    ->map(
                        fn ($collection) => [
                            'id' => $collection->id,
                            'name' => $collection->name,
                            'slug' => $collection->slug,
                        ]
                    )
                    ->values()
                    ->all()
            ),

            /*
             * -------------------------------------------------------
             * Product Images
             * -------------------------------------------------------
             */

            'images' => $this->when(
                $this->relationLoaded('galleryImages'),
                function () {
                    $galleryImages =
                        $this->galleryImages
                            ->sortBy(
                                fn ($image) =>
                                    sprintf(
                                        '%010d-%010d',
                                        (int) $image->sort_order,
                                        (int) $image->id
                                    )
                            )
                            ->values();

                    /*
                     * Preserve the existing behavior whenever
                     * normal product-level images exist.
                     */
                    $generalImages =
                        $galleryImages
                            ->filter(
                                fn ($image) =>
                                    $image->attribute_value_id
                                        === null
                            )
                            ->values();

                    $cardImages =
                        $generalImages;

                    /*
                     * Products that only have Color galleries:
                     * use the first available Color gallery for
                     * the product card.
                     *
                     * Two images are enough for:
                     * - primary card image
                     * - hover card image
                     */
                    if ($cardImages->isEmpty()) {
                        $fallbackColorImage =
                            $galleryImages->first(
                                fn ($image) =>
                                    $image->attribute_value_id
                                        !== null
                            );

                        if ($fallbackColorImage) {
                            $fallbackColorId =
                                (int) $fallbackColorImage
                                    ->attribute_value_id;

                            $cardImages =
                                $galleryImages
                                    ->filter(
                                        fn ($image) =>
                                            (int) $image
                                                ->attribute_value_id
                                            === $fallbackColorId
                                    )
                                    ->take(2)
                                    ->values();
                        }
                    }

                    return $cardImages
                        ->map(
                            fn ($image) => [
                                'id' =>
                                    $image->id,

                                'image_path' =>
                                    $this->resolveMediaUrl(
                                        $image->image_path
                                    ),

                                'alt_text' =>
                                    $image->alt_text,

                                'sort_order' =>
                                    $image->sort_order,

                                'is_primary' =>
                                    (bool) $image->is_primary,
                            ]
                        )
                        ->values()
                        ->all();
                }
            ),
        ];
    }

    protected function resolveMediaUrl(
        ?string $value
    ): ?string {
        if (
            $value === null
            || trim($value) === ''
        ) {
            return null;
        }

        $value = trim($value);

        if (
            str_starts_with(
                $value,
                'http://'
            )
            || str_starts_with(
                $value,
                'https://'
            )
        ) {
            return $value;
        }

        if (
            str_starts_with(
                $value,
                '/storage/'
            )
        ) {
            return url($value);
        }

        return $value;
    }
}
