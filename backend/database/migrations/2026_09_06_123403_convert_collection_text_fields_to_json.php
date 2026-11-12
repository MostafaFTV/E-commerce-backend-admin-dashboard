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

        DB::table('collections')
            ->select([
                'id',
                'name',
                'description',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($collections) {
                foreach ($collections as $collection) {
                    $name = $collection->name !== null
                        ? json_encode(
                            [
                                'en' => $collection->name,
                            ],
                            JSON_UNESCAPED_UNICODE |
                            JSON_UNESCAPED_SLASHES
                        )
                        : null;

                    $description = $collection->description !== null
                        ? json_encode(
                            [
                                'en' => $collection->description,
                            ],
                            JSON_UNESCAPED_UNICODE |
                            JSON_UNESCAPED_SLASHES
                        )
                        : null;

                    DB::table('collections')
                        ->where('id', $collection->id)
                        ->update([
                            'name' => $name,
                            'description' => $description,
                        ]);
                }
            });

        /*
        |--------------------------------------------------------------------------
        | Convert text fields to JSON
        |--------------------------------------------------------------------------
        */

        Schema::table('collections', function (Blueprint $table) {
            $table->json('name')->change();

            $table->json('description')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Convert JSON translations back to text
        |--------------------------------------------------------------------------
        */

        $collections = DB::table('collections')
            ->select([
                'id',
                'name',
                'description',
            ])
            ->orderBy('id')
            ->get();

        foreach ($collections as $collection) {
            $name = $this->extractEnglish(
                $collection->name
            );

            $description = $this->extractEnglish(
                $collection->description
            );

            DB::table('collections')
                ->where('id', $collection->id)
                ->update([
                    'name' => $name,
                    'description' => $description,
                ]);
        }

        Schema::table('collections', function (Blueprint $table) {
            $table->string('name', 150)->change();

            $table->text('description')
                ->nullable()
                ->change();
        });
    }

    protected function extractEnglish(
        mixed $value
    ): ?string {
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
