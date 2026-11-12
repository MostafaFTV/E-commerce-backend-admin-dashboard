<?php

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'status',
        'phone_verified_at',
        'birth_date',
        'marketing_sms',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'birth_date' => 'date:Y-m-d',
            'marketing_sms' => 'boolean',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Roles & Permissions
    |--------------------------------------------------------------------------
    */

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_user',
            'user_id',
            'role_id'
        );
    }

    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()
            ->where(
                'slug',
                $roleSlug
            )
            ->exists();
    }

    public function hasAnyRole(array $roleSlugs): bool
    {
        return $this->roles()
            ->whereIn(
                'slug',
                $roleSlugs
            )
            ->exists();
    }

    public function hasPermission(string $permissionSlug): bool
    {
        return $this->roles()
            ->whereHas(
                'permissions',
                function ($query) use ($permissionSlug) {
                    $query->where(
                        'slug',
                        $permissionSlug
                    );
                }
            )
            ->exists();
    }

    public function hasAnyPermission(array $permissions): bool
    {
        return $this->roles()
            ->whereHas(
                'permissions',
                function ($query) use ($permissions) {
                    $query->whereIn(
                        'slug',
                        $permissions
                    );
                }
            )
            ->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(
            'super_admin'
        );
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole([
            'super_admin',
            'admin',
        ]);
    }

    public function isCustomer(): bool
    {
        return $this->hasRole(
            'customer'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Customer
    |--------------------------------------------------------------------------
    */

    public function preferredCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_user_preferences',
            'user_id',
            'category_id'
        );
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(
            Address::class
        );
    }

    public function wishlist(): HasOne
    {
        return $this->hasOne(
            Wishlist::class
        );
    }

    public function carts(): HasMany
    {
        return $this->hasMany(
            Cart::class
        );
    }

    public function orders(): HasMany
    {
        return $this->hasMany(
            Order::class
        );
    }

    public function customerDiscountCodes(): HasMany
    {
        return $this->hasMany(
            CustomerDiscountCode::class,
            'customer_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Inventory / Orders
    |--------------------------------------------------------------------------
    */

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(
            InventoryMovement::class,
            'created_by'
        );
    }

    public function orderStatusChanges(): HasMany
    {
        return $this->hasMany(
            OrderStatusHistory::class,
            'changed_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CMS
    |--------------------------------------------------------------------------
    */

    public function createdPages(): HasMany
    {
        return $this->hasMany(
            Page::class,
            'created_by'
        );
    }

    public function updatedPages(): HasMany
    {
        return $this->hasMany(
            Page::class,
            'updated_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Activity Logs
    |--------------------------------------------------------------------------
    */

    public function activityLogs(): HasMany
    {
        return $this->hasMany(
            ActivityLog::class
        );
    }
}