<?php

namespace App\Models;

use App\Casts\LocalizedText;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AttributeValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'attribute_id',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => LocalizedText::class,
        ];
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(
            Attribute::class
        );
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductVariant::class,
            'variant_attribute_values'
        )->withTimestamps();
    }
}