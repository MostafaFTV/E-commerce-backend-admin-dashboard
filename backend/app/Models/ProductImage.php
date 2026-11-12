<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'attribute_value_id',
        'image_path',
        'alt_text',
        'sort_order',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(
            function (ProductImage $image): void {
                if (
                    $image->product_variant_id !== null
                ) {
                    $variant =
                        ProductVariant::query()
                            ->whereKey(
                                $image->product_variant_id
                            )
                            ->first();

                    if (
                        $variant !== null
                        && (int) $variant->product_id
                            !== (int) $image->product_id
                    ) {
                        throw ValidationException::withMessages([
                            'product_variant_id' =>
                                'The image variant must belong to the image product.',
                        ]);
                    }
                }

                if (
                    $image->attribute_value_id === null
                ) {
                    return;
                }

                $attributeValue =
                    AttributeValue::query()
                        ->with('attribute')
                        ->find(
                            $image->attribute_value_id
                        );

                if ($attributeValue === null) {
                    return;
                }

                if (
                    $attributeValue->attribute?->type
                    !== 'color'
                ) {
                    throw ValidationException::withMessages([
                        'attribute_value_id' =>
                            'Product image attribute value must belong to a color attribute.',
                    ]);
                }

                if (
                    $image->product_variant_id !== null
                ) {
                    $variantHasColor =
                        ProductVariant::query()
                            ->whereKey(
                                $image->product_variant_id
                            )
                            ->whereHas(
                                'attributeValues',
                                fn ($query) =>
                                    $query->whereKey(
                                        $image->attribute_value_id
                                    )
                            )
                            ->exists();

                    if (! $variantHasColor) {
                        throw ValidationException::withMessages([
                            'attribute_value_id' =>
                                'The selected color does not belong to the selected variant.',
                        ]);
                    }

                    return;
                }

                $productHasColor =
                    ProductVariant::query()
                        ->where(
                            'product_id',
                            $image->product_id
                        )
                        ->whereHas(
                            'attributeValues',
                            fn ($query) =>
                                $query->whereKey(
                                    $image->attribute_value_id
                                )
                        )
                        ->exists();

                if (! $productHasColor) {
                    throw ValidationException::withMessages([
                        'attribute_value_id' =>
                            'The selected color is not used by this product.',
                    ]);
                }
            }
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
    }

    public function attributeValue(): BelongsTo
    {
        return $this->belongsTo(
            AttributeValue::class,
            'attribute_value_id'
        );
    }
}
