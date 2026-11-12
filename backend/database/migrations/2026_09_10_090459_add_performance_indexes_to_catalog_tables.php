<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(
                [
                    'status',
                    'deleted_at',
                    'created_at',
                    'id',
                ],
                'products_status_deleted_created_id_index'
            );

            $table->index(
                [
                    'category_id',
                    'status',
                    'deleted_at',
                ],
                'products_category_status_deleted_index'
            );
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->index(
                [
                    'product_id',
                    'status',
                    'deleted_at',
                    'price',
                ],
                'product_variants_product_status_deleted_price_index'
            );
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(
                [
                    'parent_id',
                    'is_active',
                    'sort_order',
                ],
                'categories_parent_active_sort_index'
            );
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->index(
                [
                    'is_active',
                    'sort_order',
                ],
                'collections_active_sort_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(
                'products_status_deleted_created_id_index'
            );

            $table->dropIndex(
                'products_category_status_deleted_index'
            );
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex(
                'product_variants_product_status_deleted_price_index'
            );
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(
                'categories_parent_active_sort_index'
            );
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->dropIndex(
                'collections_active_sort_index'
            );
        });
    }
};