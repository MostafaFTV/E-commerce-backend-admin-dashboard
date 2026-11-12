<?php

namespace App\Models;

use App\Casts\LocalizedText;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    use HasFactory;

    protected $fillable = [
        'seo_title',
        'seo_description',
        'seo_image',
        'parent_id',
        'name',
        'slug',
        'description',
        'image',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'seo_title' => LocalizedText::class,
            'seo_description' => LocalizedText::class,
            'name' => LocalizedText::class,
            'description' => LocalizedText::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Parent Collection
    |--------------------------------------------------------------------------
    */

    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            Collection::class,
            'parent_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Child Collections
    |--------------------------------------------------------------------------
    */

    public function children(): HasMany
    {
        return $this->hasMany(
            Collection::class,
            'parent_id'
        )
            ->orderBy('sort_order');
    }

    /*
    |--------------------------------------------------------------------------
    | Recursive Children
    |--------------------------------------------------------------------------
    */

    public function childrenRecursive()
    {
        return $this->children()
            ->where('is_active', true)
            ->with('childrenRecursive');
    }

    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'collection_product'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Homepage Sections
    |--------------------------------------------------------------------------
    */

    public function homepageSections(): HasMany
    {
        return $this->hasMany(
            HomepageSection::class,
            'collection_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where(
            'is_active',
            true
        );
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeActiveRoot($query)
    {
        return $query
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    public function isRoot(): bool
    {
        return is_null(
            $this->parent_id
        );
    }
}