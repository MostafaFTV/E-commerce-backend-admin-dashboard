<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('cart_items', function (Blueprint $table): void {
			$table->dropIndex('cart_items_cart_product_index');
		});

		Schema::table('cart_items', function (Blueprint $table): void {
			$table->dropForeign(['product_id']);
			$table->dropColumn('product_id');
		});

		Schema::table('cart_items', function (Blueprint $table): void {
			$table->foreignId('product_variant_id')->nullable(false)->change();
			$table->renameColumn('unit_price', 'price_snapshot');
			$table->unique(
				['cart_id', 'product_variant_id'],
				'cart_items_cart_variant_unique'
			);
		});
	}

	public function down(): void
	{
		Schema::table('cart_items', function (Blueprint $table): void {
			$table->dropUnique('cart_items_cart_variant_unique');
			$table->renameColumn('price_snapshot', 'unit_price');
			$table->foreignId('product_id')
				->nullable()
				->constrained('products', 'id', 'cart_items_product_id_foreign')
				->restrictOnDelete();
			$table->index(
				['cart_id', 'product_id'],
				'cart_items_cart_product_index'
			);
		});
	}
};
