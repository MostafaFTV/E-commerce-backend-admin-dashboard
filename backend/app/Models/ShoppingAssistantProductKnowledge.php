<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShoppingAssistantProductKnowledge extends Model
{
    protected $table = 'shopping_assistant_product_knowledge';

    protected $fillable = [
        'product_id',
        'manufacturer',
        'model',
        'facts',
        'sources',
        'verified_at',
        'review_status',
    ];

    protected function casts(): array
    {
        return [
            'facts' => 'array',
            'sources' => 'array',
            'verified_at' => 'immutable_datetime',
            'product_updated_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $knowledge): void {
            $product = Product::withTrashed()->findOrFail($knowledge->product_id);
            $knowledge->product_updated_at = $product->updated_at;
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
