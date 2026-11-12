<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'product_images',
            function (Blueprint $table): void {
                $table->foreignId('attribute_value_id')
                    ->nullable()
                    ->after('product_variant_id')
                    ->constrained('attribute_values')
                    ->nullOnDelete();

                $table->index(
                    [
                        'product_id',
                        'attribute_value_id',
                        'sort_order',
                    ],
                    'product_images_product_attribute_sort_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'product_images',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'product_images_product_attribute_sort_index'
                );

                $table->dropForeign([
                    'attribute_value_id',
                ]);

                $table->dropColumn(
                    'attribute_value_id'
                );
            }
        );
    }
};
