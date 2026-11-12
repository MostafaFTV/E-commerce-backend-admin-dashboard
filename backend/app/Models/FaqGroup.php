<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FaqGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'faq_content_id',
        'title',
        'title_en',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function faqContent(): BelongsTo
    {
        return $this->belongsTo(
            FaqContent::class
        );
    }

    public function questions(): HasMany
    {
        return $this->hasMany(
            FaqQuestion::class
        );
    }
}