<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Checkout Sessions
        |--------------------------------------------------------------------------
        |
        | A checkout session represents the temporary state of a user's
        | checkout process before an order is actually created.
        |
        | Inventory is NOT reserved at this stage.
        |
        */

        Schema::create('checkout_sessions', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Ownership
            |--------------------------------------------------------------------------
            */

            $table->foreignId('user_id')
                ->constrained(
                    'users',
                    'id',
                    'checkout_sessions_user_id_foreign'
                )
                ->cascadeOnDelete();

            $table->foreignId('cart_id')
                ->constrained(
                    'carts',
                    'id',
                    'checkout_sessions_cart_id_foreign'
                )
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [
                'active',
                'completed',
                'expired',
                'cancelled',
            ])->default('active');

            /*
            |--------------------------------------------------------------------------
            | Address Snapshots
            |--------------------------------------------------------------------------
            */

            $table->json('shipping_address')->nullable();

            $table->json('billing_address')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Shipping
            |--------------------------------------------------------------------------
            */

            $table->foreignId('shipping_method_id')
                ->nullable()
                ->constrained(
                    'shipping_methods',
                    'id',
                    'checkout_sessions_shipping_method_id_foreign'
                )
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Expiration
            |--------------------------------------------------------------------------
            */

            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['user_id', 'status'],
                'checkout_sessions_user_status_index'
            );

            $table->index(
                ['cart_id', 'status'],
                'checkout_sessions_cart_status_index'
            );

            $table->index(
                ['status', 'expires_at'],
                'checkout_sessions_status_expires_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_sessions');
    }
};