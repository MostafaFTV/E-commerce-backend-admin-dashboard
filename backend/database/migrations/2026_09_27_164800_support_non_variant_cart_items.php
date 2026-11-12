<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Keep the existing variant identity and unique constraint intact.
         * Add product identity so products without variants can be represented.
         */
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->foreignId('product_id')
                ->nullable()
                ->after('cart_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->index(
                ['cart_id', 'product_id'],
                'cart_items_cart_product_index'
            );
        });

        /*
         * Existing cart rows are variant-based. Backfill their product_id
         * before making product identity part of the application contract.
         */
        DB::table('cart_items')
            ->select([
                'id',
                'product_variant_id',
            ])
            ->whereNotNull('product_variant_id')
            ->orderBy('id')
            ->chunkById(
                100,
                function ($items): void {
                    $variantIds = collect($items)
                        ->pluck('product_variant_id')
                        ->filter()
                        ->unique()
                        ->values();

                    $productIds = DB::table('product_variants')
                        ->whereIn('id', $variantIds)
                        ->pluck('product_id', 'id');

                    foreach ($items as $item) {
                        $productId =
                            $productIds[
                                $item->product_variant_id
                            ] ?? null;

                        if ($productId === null) {
                            continue;
                        }

                        DB::table('cart_items')
                            ->where('id', $item->id)
                            ->update([
                                'product_id' => $productId,
                            ]);
                    }
                }
            );

        /*
         * Product-only cart items use product_variant_id = NULL.
         * Variant-based rows continue to use the existing unique
         * (cart_id, product_variant_id) constraint unchanged.
         */
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->foreignId('product_variant_id')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        /*
         * The old schema cannot represent product-only cart items.
         * Cart rows are transient, so remove only those rows before rollback.
         */
        DB::table('cart_items')
            ->whereNull('product_variant_id')
            ->delete();

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->foreignId('product_variant_id')
                ->nullable(false)
                ->change();
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropIndex(
                'cart_items_cart_product_index'
            );

            $table->dropForeign([
                'product_id',
            ]);

            $table->dropColumn(
                'product_id'
            );
        });
    }
};
