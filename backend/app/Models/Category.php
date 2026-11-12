<?php

namespace App\Models;

use App\Casts\LocalizedText;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
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

        'sort_order',
        'is_active',

        'created_by',
        'updated_by',

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
    | Parent Category
    |--------------------------------------------------------------------------
    */

    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            Category::class,
            'parent_id'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Child Categories
    |--------------------------------------------------------------------------
    */

    public function children(): HasMany
    {
        return $this->hasMany(
            Category::class,
            'parent_id'
        )
        ->orderBy('sort_order');
    }


    /*
    |--------------------------------------------------------------------------
    | Recursive Child Categories
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

    public function products(): HasMany
    {
        return $this->hasMany(
            Product::class,
            'category_id'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Created By
    |--------------------------------------------------------------------------
    */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Updated By
    |--------------------------------------------------------------------------
    */

    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
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