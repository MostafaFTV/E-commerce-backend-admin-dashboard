<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('currency', 10)->default('IRT');

            $table->timestamps();
        });

        Schema::create('wallet_credits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->foreignId('source_order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            $table->foreignId('cashback_rule_order_item_id')
                ->unique('wallet_credits_rule_item_unique')
                ->constrained('cashback_rule_order_items')
                ->restrictOnDelete();

            $table->decimal('original_amount', 12, 2);

            $table->decimal('remaining_amount', 12, 2);

            $table->enum('status', [
                'pending',
                'available',
                'exhausted',
                'expired',
                'reversed',
            ])->default('pending');

            $table->unsignedInteger('expires_after_days_snapshot');

            $table->timestamp('available_at')->nullable();

            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(
                'source_order_id',
                'wallet_credits_source_order_index'
            );

            $table->index(
                ['wallet_id', 'status', 'expires_at'],
                'wallet_credits_availability_index'
            );
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wallet_allocation_id')
                ->nullable();

            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->restrictOnDelete();

            $table->enum('type', [
                'cashback_pending',
                'cashback_available',
                'cashback_reversed',
                'credit_reserved',
                'credit_spent',
                'credit_released',
                'credit_restored',
                'credit_expired',
            ]);

            $table->decimal('amount', 12, 2);

            $table->string('idempotency_key', 191)->unique();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(
                ['wallet_id', 'created_at'],
                'wallet_transactions_history_index'
            );

            $table->index(
                ['order_id', 'type'],
                'wallet_transactions_order_type_index'
            );
        });

        Schema::create('wallet_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wallet_credit_id')
                ->constrained('wallet_credits')
                ->restrictOnDelete();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            $table->decimal('amount', 12, 2);

            $table->enum('status', [
                'reserved',
                'spent',
                'released',
                'restored',
            ])->default('reserved');

            $table->timestamps();

            $table->index(
                ['wallet_credit_id', 'order_id'],
                'wallet_allocations_credit_order_index'
            );

            $table->index(
                ['order_id', 'status'],
                'wallet_allocations_order_status_index'
            );
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreign('wallet_allocation_id',
                'wallet_transactions_allocation_foreign'
            )
                ->references('id')
                ->on('wallet_allocations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_allocations');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallet_credits');
        Schema::dropIfExists('wallets');
    }
};