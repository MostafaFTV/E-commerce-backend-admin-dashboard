<?php

namespace App\Models;

use App\Casts\LocalizedText;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomepageSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_key',
        'title',
        'subtitle',
        'content',
        'image',
        'mobile_image',
        'link',
        'button_text',
        'category_id',
        'product_id',
        'collection_id',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'title' => LocalizedText::class,
            'subtitle' => LocalizedText::class,
            'content' => LocalizedText::class,
            'button_text' => LocalizedText::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            Category::class
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(
            Collection::class
        );
    }
}
