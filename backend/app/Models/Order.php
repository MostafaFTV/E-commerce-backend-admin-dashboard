<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'subtotal',
        'discount',
        'shipping_cost',
        'tax',
        'total',
        'total_before_wallet',
        'wallet_credit_used',
        'currency',
        'shipping_method_id',
        'shipping_address',
        'billing_address',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,

            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'total_before_wallet' => 'decimal:2',
            'wallet_credit_used' => 'decimal:2',

            'shipping_address' => 'array',
            'billing_address' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)
            ->orderBy('created_at');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function isPendingPayment(): bool
    {
        return $this->status === OrderStatus::PENDING_PAYMENT;
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::PAID;
    }

    public function isProcessing(): bool
    {
        return $this->status === OrderStatus::PROCESSING;
    }

    public function isShipped(): bool
    {
        return $this->status === OrderStatus::SHIPPED;
    }

    public function isDelivered(): bool
    {
        return $this->status === OrderStatus::DELIVERED;
    }

    public function isCancelled(): bool
    {
        return $this->status === OrderStatus::CANCELLED;
    }

    public function isReturned(): bool
    {
        return $this->status === OrderStatus::RETURNED;
    }
}