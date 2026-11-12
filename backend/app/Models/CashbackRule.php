<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashbackRule extends Model
{
    protected $fillable = [
        'name',
        'scope',
        'percentage',
        'priority',
        'is_active',
        'starts_at',
        'ends_at',
        'expires_after_days',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'expires_after_days' => 'integer',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'cashback_rule_products'
        );
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'cashback_rule_categories'
        );
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(
            CashbackRuleOrderItem::class
        );
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $this->starts_at->greaterThan($now)) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->lessThan($now)) {
            return false;
        }

        return true;
    }
}