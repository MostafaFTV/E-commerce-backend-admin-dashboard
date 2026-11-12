<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();

            /*
             * Active catalog products must have at least one non-deleted variant.
             *
             * Only products with no current variant at all are repaired here.
             * Existing MADE_TO_ORDER / SOLD / INACTIVE variants are not changed.
             */
            $productIds = DB::table('products as products')
                ->where('products.status', 'active')
                ->whereNull('products.deleted_at')
                ->whereNotExists(function ($query): void {
                    $query
                        ->selectRaw('1')
                        ->from('product_variants as variants')
                        ->whereColumn(
                            'variants.product_id',
                            'products.id'
                        )
                        ->whereNull('variants.deleted_at');
                })
                ->orderBy('products.id')
                ->pluck('products.id');

            foreach ($productIds as $productId) {
                $baseSku = 'TKB-DEFAULT-' . $productId;
                $sku = $baseSku;
                $suffix = 2;

                /*
                 * SKU is globally unique, including soft-deleted variants.
                 *
                 * Use the canonical deterministic SKU when available.
                 * If historical data already owns it, use a deterministic
                 * numeric suffix instead of modifying or restoring that row.
                 */
                while (
                    DB::table('product_variants')
                        ->where('sku', $sku)
                        ->exists()
                ) {
                    $sku = $baseSku . '-' . $suffix;
                    $suffix++;
                }

                DB::table('product_variants')->insert([
                    'product_id' => $productId,
                    'sku' => $sku,
                    'price' => null,
                    'status' => 'active',
                    'sort_order' => 0,
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            /*
             * Every ACTIVE non-deleted variant requires physical inventory.
             *
             * Create only missing inventory rows and always start them at zero.
             * MADE_TO_ORDER / SOLD / INACTIVE variants are intentionally ignored.
             */
            $variantIds = DB::table('product_variants as variants')
                ->where('variants.status', 'active')
                ->whereNull('variants.deleted_at')
                ->whereNotExists(function ($query): void {
                    $query
                        ->selectRaw('1')
                        ->from('inventories as inventories')
                        ->whereColumn(
                            'inventories.product_variant_id',
                            'variants.id'
                        );
                })
                ->orderBy('variants.id')
                ->pluck('variants.id');

            foreach ($variantIds as $variantId) {
                DB::table('inventories')->insertOrIgnore([
                    'product_variant_id' => $variantId,
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'low_stock_threshold' => 5,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        /*
         * Intentionally irreversible.
         *
         * This migration repairs catalog configuration data. Inventory created
         * here may later receive real stock values through Admin, so deleting it
         * during rollback could destroy legitimate operational data.
         */
    }
};