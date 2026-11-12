<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();

            $table->string('name', 150)
                ->unique();

            $table->string('slug', 180)
                ->unique();

            $table->boolean('is_active')
                ->default(true);

            $table->unsignedInteger('sort_order')
                ->default(0);

            $table->timestamps();
        });

        $existingBrands = DB::table('products')
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->select('brand')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

        $now = now();

        foreach ($existingBrands as $existingBrand) {
            $name = trim(
                (string) $existingBrand
            );

            if ($name === '') {
                continue;
            }

            $baseSlug = Str::slug($name);

            if ($baseSlug === '') {
                $baseSlug = Str::lower(
                    Str::random(12)
                );
            }

            $slug = $baseSlug;
            $suffix = 2;

            while (
                DB::table('brands')
                    ->where('slug', $slug)
                    ->exists()
            ) {
                $slug =
                    $baseSlug .
                    '-' .
                    $suffix;

                $suffix++;
            }

            DB::table('brands')->insert([
                'name' => $name,
                'slug' => $slug,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
