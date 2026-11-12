<?php

namespace App\Models;

use App\Casts\LocalizedText;
use App\Enums\MenuItemType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_id',
        'parent_id',
        'title',
        'type',
        'url',
        'category_id',
        'product_id',
        'collection_id',
        'page_id',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'title' => LocalizedText::class,
            'type' => MenuItemType::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            MenuItem::class,
            'parent_id'
        );
    }

    public function children(): HasMany
    {
        return $this->hasMany(
            MenuItem::class,
            'parent_id'
        )->orderBy('sort_order');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function isUrl(): bool
    {
        return $this->type === MenuItemType::URL;
    }

    public function isCategory(): bool
    {
        return $this->type === MenuItemType::CATEGORY;
    }

    public function isProduct(): bool
    {
        return $this->type === MenuItemType::PRODUCT;
    }

    public function isCollection(): bool
    {
        return $this->type === MenuItemType::COLLECTION;
    }

    public function isPage(): bool
    {
        return $this->type === MenuItemType::PAGE;
    }
}
