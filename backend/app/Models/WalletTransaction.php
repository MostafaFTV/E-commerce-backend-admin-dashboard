<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $fillable = [
        'wallet_id',
        'wallet_allocation_id',
        'order_id',
        'type',
        'amount',
        'idempotency_key',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(
            WalletAllocation::class,
            'wallet_allocation_id'
        );
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}