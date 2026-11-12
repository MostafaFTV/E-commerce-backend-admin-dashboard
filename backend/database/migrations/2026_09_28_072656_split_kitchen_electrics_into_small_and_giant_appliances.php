<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $legacy = DB::table('categories')
                ->where('slug', 'kitchen-electrics')
                ->first();

            $small = DB::table('categories')
                ->where('slug', 'small-appliances')
                ->first();

            if ($legacy && ! $small) {
                DB::table('categories')
                    ->where('id', $legacy->id)
                    ->update([
                        'name' => json_encode([
                            'en' => 'Small Appliances',
                            'fa' => 'لوازم برقی کوچک',
                        ], JSON_UNESCAPED_UNICODE),

                        'description' => json_encode([
                            'en' => 'Small electrical appliances for preparation, cooking and drinks.',
                            'fa' => 'لوازم برقی کوچک برای آماده‌سازی، پخت‌وپز و نوشیدنی.',
                        ], JSON_UNESCAPED_UNICODE),

                        'slug' => 'small-appliances',
                        'parent_id' => null,
                        'sort_order' => 5,
                        'is_active' => true,
                        'updated_at' => now(),
                    ]);

                $smallId = $legacy->id;
            } elseif ($small) {
                $smallId = $small->id;

                if ($legacy) {
                    DB::table('categories')
                        ->where('parent_id', $legacy->id)
                        ->update([
                            'parent_id' => $smallId,
                            'updated_at' => now(),
                        ]);

                    DB::table('products')
                        ->where('category_id', $legacy->id)
                        ->update([
                            'category_id' => $smallId,
                            'updated_at' => now(),
                        ]);

                    DB::table('categories')
                        ->where('id', $legacy->id)
                        ->delete();
                }
            } else {
                $smallId = DB::table('categories')->insertGetId([
                    'parent_id' => null,

                    'name' => json_encode([
                        'en' => 'Small Appliances',
                        'fa' => 'لوازم برقی کوچک',
                    ], JSON_UNESCAPED_UNICODE),

                    'description' => json_encode([
                        'en' => 'Small electrical appliances for preparation, cooking and drinks.',
                        'fa' => 'لوازم برقی کوچک برای آماده‌سازی، پخت‌وپز و نوشیدنی.',
                    ], JSON_UNESCAPED_UNICODE),

                    'slug' => 'small-appliances',
                    'image' => null,
                    'is_active' => true,
                    'sort_order' => 5,
                    'created_by' => null,
                    'updated_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $giant = DB::table('categories')
                ->where('slug', 'giant-appliances')
                ->first();

            if ($giant) {
                $giantId = $giant->id;

                DB::table('categories')
                    ->where('id', $giantId)
                    ->update([
                        'parent_id' => null,
                        'sort_order' => 6,
                        'is_active' => true,
                        'updated_at' => now(),
                    ]);
            } else {
                $giantId = DB::table('categories')->insertGetId([
                    'parent_id' => null,

                    'name' => json_encode([
                        'en' => 'Giant Appliances',
                        'fa' => 'لوازم برقی بزرگ',
                    ], JSON_UNESCAPED_UNICODE),

                    'description' => json_encode([
                        'en' => 'Large kitchen appliances and statement electrical equipment.',
                        'fa' => 'لوازم برقی بزرگ آشپزخانه و تجهیزات برقی اصلی.',
                    ], JSON_UNESCAPED_UNICODE),

                    'slug' => 'giant-appliances',
                    'image' => null,
                    'is_active' => true,
                    'sort_order' => 6,
                    'created_by' => null,
                    'updated_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('products')
                ->where('slug', 'smeg-refrigerator')
                ->update([
                    'category_id' => $giantId,
                    'updated_at' => now(),
                ]);

            DB::table('products')
                ->whereIn('slug', [
                    'smeg-tea-maker',
                    'ninja-og950-dz950',
                    'ninja-sl400',
                ])
                ->update([
                    'category_id' => $smallId,
                    'updated_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $small = DB::table('categories')
                ->where('slug', 'small-appliances')
                ->first();

            $giant = DB::table('categories')
                ->where('slug', 'giant-appliances')
                ->first();

            if (! $small) {
                return;
            }

            if ($giant) {
                DB::table('products')
                    ->where('category_id', $giant->id)
                    ->update([
                        'category_id' => $small->id,
                        'updated_at' => now(),
                    ]);

                DB::table('categories')
                    ->where('parent_id', $giant->id)
                    ->update([
                        'parent_id' => $small->id,
                        'updated_at' => now(),
                    ]);

                DB::table('categories')
                    ->where('id', $giant->id)
                    ->delete();
            }

            DB::table('categories')
                ->where('id', $small->id)
                ->update([
                    'name' => json_encode([
                        'en' => 'Kitchen Electrics',
                        'fa' => 'لوازم برقی آشپزخانه',
                    ], JSON_UNESCAPED_UNICODE),

                    'description' => json_encode([
                        'en' => 'Electrical appliances designed for cooking, preparation and drinks.',
                        'fa' => 'لوازم برقی مخصوص پخت، آماده‌سازی غذا و نوشیدنی.',
                    ], JSON_UNESCAPED_UNICODE),

                    'slug' => 'kitchen-electrics',
                    'sort_order' => 5,
                    'updated_at' => now(),
                ]);
        });
    }
};