<?php

namespace App\Models;

use App\Enums\VariantStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'status' => VariantStatus::class,
            'sort_order' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'variant_attribute_values'
        )->withTimestamps();
    }

    public function images(): HasMany
    {
        return $this->hasMany(
            ProductImage::class,
            'product_variant_id'
        )->orderBy('sort_order');
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(
            Inventory::class,
            'product_variant_id'
        );
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    */

    public function effectivePrice(): float
    {
        return $this->price !== null
            ? (float) $this->price
            : (float) $this->product->price;
    }

    /*
    |--------------------------------------------------------------------------
    | Inventory Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Get physical inventory quantity.
     */
    public function inventoryQuantity(): int
    {
        return $this->inventory?->quantity ?? 0;
    }

    /**
     * Get available inventory quantity.
     */
    public function availableInventory(): int
    {
        if (! $this->inventory) {
            return 0;
        }

        return $this->inventory->availableQuantity();
    }

    /**
     * Check whether the variant is a made-to-order variant.
     */
    public function isMadeToOrder(): bool
    {
        return $this->status === VariantStatus::MADE_TO_ORDER;
    }

    /**
     * Check whether the variant can be purchased.
     */
    public function isPurchasable(): bool
    {
        return $this->status?->isPurchasable() === true;
    }

    /**
     * Check whether this variant requires physical inventory.
     */
    public function requiresInventory(): bool
    {
        return $this->status?->requiresInventory() === true;
    }

    /**
     * Check whether this variant is physically in stock.
     */
    public function isInStock(): bool
    {
        return $this->status === VariantStatus::ACTIVE
            && $this->availableInventory() > 0;
    }
}
