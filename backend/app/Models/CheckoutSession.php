<?php

namespace App\Models;

use App\Enums\CheckoutSessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cart_id',
        'status',
        'shipping_address',
        'billing_address',
        'shipping_method_id',
        'wallet_credit_requested',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CheckoutSessionStatus::class,
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'wallet_credit_requested' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function isActive(): bool
    {
        return $this->status === CheckoutSessionStatus::ACTIVE;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->isPast();
    }
}