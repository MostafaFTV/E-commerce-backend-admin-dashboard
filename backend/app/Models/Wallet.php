<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = [
        'user_id',
        'currency',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(
            WalletCredit::class
        );
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(
            WalletTransaction::class
        );
    }
}