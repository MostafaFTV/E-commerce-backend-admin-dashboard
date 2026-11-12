<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashback_rules', function (Blueprint $table) {
            $table->id();

            $table->string('name', 255);

            $table->enum('scope', [
                'all',
                'categories',
                'products',
            ]);

            $table->decimal('percentage', 5, 2);

            $table->unsignedInteger('priority')->default(0);

            $table->boolean('is_active')->default(false);

            $table->timestamp('starts_at')->nullable();

            $table->timestamp('ends_at')->nullable();

            $table->unsignedInteger('expires_after_days');

            $table->timestamps();

            $table->index(
                ['is_active', 'scope', 'priority'],
                'cashback_rules_active_scope_priority_index'
            );

            $table->index(
                ['starts_at', 'ends_at'],
                'cashback_rules_dates_index'
            );
        });

        Schema::create('cashback_rule_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cashback_rule_id')
                ->constrained('cashback_rules')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->unique(
                ['cashback_rule_id', 'product_id'],
                'cashback_rule_product_unique'
            );
        });

        Schema::create('cashback_rule_categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cashback_rule_id')
                ->constrained('cashback_rules')
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->unique(
                ['cashback_rule_id', 'category_id'],
                'cashback_rule_category_unique'
            );
        });

        Schema::create('cashback_rule_order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->cascadeOnDelete();

            $table->foreignId('cashback_rule_id')
                ->nullable()
                ->constrained('cashback_rules')
                ->nullOnDelete();

            $table->string('rule_name_snapshot', 255);

            $table->decimal('percentage_snapshot', 5, 2);

            $table->decimal('eligible_amount', 12, 2);

            $table->decimal('cashback_amount', 12, 2);

            $table->unsignedInteger('expires_after_days_snapshot');

            $table->timestamps();

            $table->unique(
                'order_item_id',
                'cashback_order_item_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashback_rule_order_items');
        Schema::dropIfExists('cashback_rule_categories');
        Schema::dropIfExists('cashback_rule_products');
        Schema::dropIfExists('cashback_rules');
    }
};