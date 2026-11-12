<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalletCredit extends Model
{
    protected $fillable = [
        'wallet_id',
        'source_order_id',
        'cashback_rule_order_item_id',
        'original_amount',
        'remaining_amount',
        'status',
        'expires_after_days_snapshot',
        'available_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'expires_after_days_snapshot' => 'integer',
            'available_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function sourceOrder(): BelongsTo
    {
        return $this->belongsTo(
            Order::class,
            'source_order_id'
        );
    }

    public function ruleOrderItem(): BelongsTo
    {
        return $this->belongsTo(
            CashbackRuleOrderItem::class,
            'cashback_rule_order_item_id'
        );
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(WalletAllocation::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available'
            && $this->remaining_amount > 0
            && $this->available_at !== null
            && $this->available_at->lte(now())
            && $this->expires_at !== null
            && $this->expires_at->gt(now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->lte(now());
    }
}