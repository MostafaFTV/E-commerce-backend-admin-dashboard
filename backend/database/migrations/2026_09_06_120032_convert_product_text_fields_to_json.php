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
        |--------------------------------------------------------------------------
        | Preserve existing values under English
        |--------------------------------------------------------------------------
        */

        DB::table('products')
            ->select([
                'id',
                'name',
                'short_description',
                'description',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($products) {
                foreach ($products as $product) {
                    $name = $product->name !== null
                        ? json_encode(
                            [
                                'en' => $product->name,
                            ],
                            JSON_UNESCAPED_UNICODE |
                            JSON_UNESCAPED_SLASHES
                        )
                        : null;

                    $shortDescription = $product->short_description !== null
                        ? json_encode(
                            [
                                'en' => $product->short_description,
                            ],
                            JSON_UNESCAPED_UNICODE |
                            JSON_UNESCAPED_SLASHES
                        )
                        : null;

                    $description = $product->description !== null
                        ? json_encode(
                            [
                                'en' => $product->description,
                            ],
                            JSON_UNESCAPED_UNICODE |
                            JSON_UNESCAPED_SLASHES
                        )
                        : null;

                    DB::table('products')
                        ->where('id', $product->id)
                        ->update([
                            'name' => $name,
                            'short_description' => $shortDescription,
                            'description' => $description,
                        ]);
                }
            });

        /*
        |--------------------------------------------------------------------------
        | Convert text fields to JSON
        |--------------------------------------------------------------------------
        */

        Schema::table('products', function (Blueprint $table) {
            $table->json('name')->change();

            $table->json('short_description')->nullable()->change();

            $table->json('description')->nullable()->change();
        });
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Convert JSON translations back to text
        |--------------------------------------------------------------------------
        */

        $products = DB::table('products')
            ->select([
                'id',
                'name',
                'short_description',
                'description',
            ])
            ->orderBy('id')
            ->get();

        foreach ($products as $product) {
            $name = $this->extractEnglish($product->name);

            $shortDescription = $this->extractEnglish(
                $product->short_description
            );

            $description = $this->extractEnglish(
                $product->description
            );

            DB::table('products')
                ->where('id', $product->id)
                ->update([
                    'name' => $name,
                    'short_description' => $shortDescription,
                    'description' => $description,
                ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('name', 255)->change();

            $table->text('short_description')
                ->nullable()
                ->change();

            $table->text('description')
                ->nullable()
                ->change();
        });
    }

    protected function extractEnglish(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value['en'] ?? null;
        }

        $decoded = json_decode(
            $value,
            true
        );

        if (!is_array($decoded)) {
            return null;
        }

        return $decoded['en'] ?? null;
    }
};