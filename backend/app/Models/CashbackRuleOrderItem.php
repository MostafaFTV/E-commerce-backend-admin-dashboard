<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashbackRuleOrderItem extends Model
{
    protected $table = 'cashback_rule_order_items';

    protected $fillable = [
        'order_item_id',
        'cashback_rule_id',
        'rule_name_snapshot',
        'percentage_snapshot',
        'eligible_amount',
        'cashback_amount',
        'expires_after_days_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'percentage_snapshot' => 'decimal:2',
            'eligible_amount' => 'decimal:2',
            'cashback_amount' => 'decimal:2',
            'expires_after_days_snapshot' => 'integer',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(
            CashbackRule::class,
            'cashback_rule_id'
        );
    }

    public function walletCredit(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(
            WalletCredit::class,
            'cashback_rule_order_item_id'
        );
    }
}