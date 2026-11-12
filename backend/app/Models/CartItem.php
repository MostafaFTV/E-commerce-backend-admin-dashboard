<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'price_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price_snapshot' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CartItem $item): void {
            if ($item->cart_id === null) {
                throw ValidationException::withMessages([
                    'cart_id' => 'A cart item must belong to a cart.',
                ]);
            }

            if (
                $item->product_id === null
                && $item->product_variant_id !== null
            ) {
                $variant = ProductVariant::query()
                    ->select([
                        'id',
                        'product_id',
                    ])
                    ->find(
                        $item->product_variant_id
                    );

                if (! $variant) {
                    throw ValidationException::withMessages([
                        'product_variant_id' =>
                            'A cart item must reference a valid product variant.',
                    ]);
                }

                $item->product_id = $variant->product_id;
            }

            if ($item->product_id === null) {
                throw ValidationException::withMessages([
                    'product_id' =>
                        'A cart item must reference a product.',
                ]);
            }

            if ($item->product_variant_id !== null) {
                $variant = ProductVariant::query()
                    ->select([
                        'id',
                        'product_id',
                    ])
                    ->find(
                        $item->product_variant_id
                    );

                if (
                    ! $variant
                    || (int) $variant->product_id
                        !== (int) $item->product_id
                ) {
                    throw ValidationException::withMessages([
                        'product_variant_id' =>
                            'The selected product variant does not belong to this product.',
                    ]);
                }
            }

            if ($item->quantity < 1) {
                throw ValidationException::withMessages([
                    'quantity' => 'Cart item quantity must be at least 1.',
                ]);
            }

            if ((float) $item->price_snapshot < 0) {
                throw ValidationException::withMessages([
                    'price_snapshot' => 'Cart item price snapshot must be non-negative.',
                ]);
            }
        });
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
    }

    public function subtotal(): float
    {
        return $this->quantity * (float) $this->price_snapshot;
    }
}
