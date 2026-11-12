<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDiscountCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'percentage',
        'customer_id',
        'scope',
        'category_id',
        'expires_at',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'integer',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function status(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        return $this->expires_at->isPast()
            ? 'expired'
            : 'active';
    }
}
