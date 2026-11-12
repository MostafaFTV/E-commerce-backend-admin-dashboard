<?php

namespace App\Models;

use App\Casts\LocalizedText;
use App\Enums\PageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'seo_title',
        'seo_description',
        'seo_image',
        'title',
        'slug',
        'content',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'seo_title' => LocalizedText::class,
            'seo_description' => LocalizedText::class,
            'title' => LocalizedText::class,
            'content' => LocalizedText::class,
            'status' => PageStatus::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function isPublished(): bool
    {
        return $this->status === PageStatus::PUBLISHED;
    }
}
