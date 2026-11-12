<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'quantity',
        'reserved_quantity',
        'low_stock_threshold',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Inventory belongs to a product variant.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
    }

    /**
     * Inventory movement history.
     */
    public function movements(): HasMany
    {
        return $this->hasMany(
            InventoryMovement::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Business Logic
    |--------------------------------------------------------------------------
    */

    /**
     * Available stock for selling.
     *
     * quantity - reserved_quantity
     */
    public function availableQuantity(): int
    {
        return max(
            0,
            $this->quantity - $this->reserved_quantity
        );
    }

    /**
     * Check if enough stock is available.
     */
    public function hasStock(int $amount = 1): bool
    {
        return $this->availableQuantity() >= $amount;
    }

    /**
     * Check low stock.
     */
    public function isLowStock(): bool
    {
        return $this->availableQuantity() <= $this->low_stock_threshold;
    }
}