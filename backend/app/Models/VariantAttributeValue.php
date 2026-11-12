<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariantAttributeValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'attribute_value_id',
    ];

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