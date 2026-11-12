<?php

namespace App\Models;

use App\Casts\LocalizedText;
use App\Enums\ProductStatus;
use App\Support\ProductSearchIndexer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'seo_title',
        'seo_description',
        'seo_image',
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'brand',
        'price',
        'discount_percentage',
        'status',
        'is_featured',
    ];

    protected static function booted(): void
    {
        static::saving(
            function (Product $product): void {
                $index =
                    ProductSearchIndexer::build(
                        $product
                    );

                $product->setAttribute(
                    'search_text',
                    $index['search_text']
                );

                $product->setAttribute(
                    'search_compact',
                    $index['search_compact']
                );
            }
        );

        static::updated(function (Product $product): void {
            $knowledge = ShoppingAssistantProductKnowledge::query()
                ->where('product_id', $product->getKey())
                ->where('review_status', 'approved');

            if ($product->wasChanged([
                'name',
                'slug',
                'brand',
                'category_id',
                'status',
                'short_description',
                'description',
                'deleted_at',
            ])) {
                $knowledge->update([
                    'review_status' => 'stale',
                    'verified_at' => null,
                    'updated_at' => now(),
                ]);

                return;
            }

            $knowledge->update([
                'product_updated_at' => $product->updated_at,
            ]);
        });
    }

    protected function casts(): array
    {
        return [
            'seo_title' => LocalizedText::class,
            'seo_description' => LocalizedText::class,
            'name' => LocalizedText::class,
            'short_description' => LocalizedText::class,
            'description' => LocalizedText::class,
            'price' => 'decimal:2',
            'discount_percentage' => 'integer',
            'status' => ProductStatus::class,
            'is_featured' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
            ->orderBy('sort_order');
    }

    /**
     * General product images.
     *
     * These are the images used for the default product gallery
     * and product-card ordering.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->whereNull('product_variant_id')
            ->whereNull('attribute_value_id')
            ->orderBy('sort_order');
    }

    /**
     * Product-level gallery images.
     *
     * Includes:
     * - general product images
     * - color-specific product images
     *
     * Variant-specific images are excluded.
     */
    public function galleryImages(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->whereNull('product_variant_id')
            ->orderBy('sort_order');
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(
            Collection::class,
            'collection_product'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function hasVariants(): bool
    {
        return $this->variants()->exists();
    }

    /**
     * Get the effective listing price for the product.
     *
     * Rules:
     * - Product without variants => products.price
     * - Product with active variants => minimum effective variant price
     *
     * A variant uses its own price when defined,
     * otherwise it falls back to products.price.
     */
    public function effectivePrice(): float
    {
        $variantPrices = $this->variants()
            ->where('product_variants.status', 'active')
            ->pluck('price');

        if ($variantPrices->isEmpty()) {
            return (float) $this->price;
        }

        $minimumPrice = $variantPrices
            ->map(function ($price) {
                return $price !== null
                    ? (float) $price
                    : (float) $this->price;
            })
            ->min();

        return (float) $minimumPrice;
    }

    /**
     * Determine whether the product should display
     * a "From" price on the listing.
     */
    public function hasPriceRange(): bool
    {
        $activeVariants = $this->variants()
            ->where('product_variants.status', 'active')
            ->get();

        if ($activeVariants->isEmpty()) {
            return false;
        }

        $prices = $activeVariants
            ->map(function (ProductVariant $variant) {
                return $variant->effectivePrice();
            });

        return $prices->min() !== $prices->max();
    }

    /**
     * Get the highest effective price among active variants.
     */
    public function maxEffectivePrice(): float
    {
        $variantPrices = $this->variants()
            ->where('product_variants.status', 'active')
            ->get()
            ->map(function (ProductVariant $variant) {
                return $variant->effectivePrice();
            });

        if ($variantPrices->isEmpty()) {
            return (float) $this->price;
        }

        return (float) $variantPrices->max();
    }

    /**
     * Get total physical inventory across active variants.
     */
    public function totalInventory(): int
    {
        return (int) $this->variants()
            ->where('product_variants.status', 'active')
            ->join(
                'inventories',
                'product_variants.id',
                '=',
                'inventories.product_variant_id'
            )
            ->sum('inventories.quantity');
    }

    /**
     * Get total available inventory across active variants.
     *
     * Available inventory = quantity - reserved_quantity.
     */
    public function availableInventory(): int
    {
        return (int) $this->variants()
            ->where('product_variants.status', 'active')
            ->join(
                'inventories',
                'product_variants.id',
                '=',
                'inventories.product_variant_id'
            )
            ->selectRaw(
                'COALESCE(
                    SUM(
                        GREATEST(
                            inventories.quantity - inventories.reserved_quantity,
                            0
                        )
                    ),
                    0
                ) as total'
            )
            ->value('total');
    }

    /**
     * Check whether any active variant is available for sale.
     */
    public function hasAvailableInventory(): bool
    {
        return $this->availableInventory() > 0;
    }

    public static function calculateDiscountedPrice(
        ?float $price,
        mixed $percentage
    ): ?float {
        $percentage = filter_var(
            $percentage,
            FILTER_VALIDATE_INT
        );

        if (
            $price === null
            || $price <= 0
            || $percentage === false
            || $percentage < 1
            || $percentage > 100
        ) {
            return null;
        }

        return round(
            $price * (100 - $percentage) / 100,
            2
        );
    }
}