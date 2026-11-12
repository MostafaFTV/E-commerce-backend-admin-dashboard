<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalletAllocation extends Model
{
    protected $fillable = [
        'wallet_credit_id',
        'order_id',
        'amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function walletCredit(): BelongsTo
    {
        return $this->belongsTo(
            WalletCredit::class,
            'wallet_credit_id'
        );
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(
            WalletTransaction::class,
            'wallet_allocation_id'
        );
    }

    public function isReserved(): bool
    {
        return $this->status === 'reserved';
    }

    public function isSpent(): bool
    {
        return $this->status === 'spent';
    }

    public function isReleased(): bool
    {
        return $this->status === 'released';
    }

    public function isRestored(): bool
    {
        return $this->status === 'restored';
    }
}