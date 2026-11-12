<?php

namespace App\Models;

use App\Enums\CartStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => CartStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Cart $cart): void {
            $hasUserOwner = $cart->user_id !== null;
            $hasSessionOwner = $cart->session_id !== null;

            if ($hasUserOwner === $hasSessionOwner) {
                throw ValidationException::withMessages([
                    'owner' => 'A cart must belong to exactly one user or session owner.',
                ]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function calculateSubtotal(): float
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return (float) $items
            ->sum(function (CartItem $item) {
                return $item->quantity * (float) $item->price_snapshot;
            });
    }

    public function isActive(): bool
    {
        return $this->status === CartStatus::ACTIVE;
    }

    public static function activeForUser(User $user): ?self
    {
        return static::query()
            ->where('user_id', $user->id)
            ->where('status', CartStatus::ACTIVE)
            ->latest('id')
            ->first();
    }
}