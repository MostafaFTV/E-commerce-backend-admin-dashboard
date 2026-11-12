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
        | Preserve existing titles under English
        |--------------------------------------------------------------------------
        */

        DB::table('menu_items')
            ->orderBy('id')
            ->chunkById(
                100,
                function ($items): void {
                    foreach ($items as $item) {
                        if ($item->title === null) {
                            continue;
                        }

                        DB::table('menu_items')
                            ->where('id', $item->id)
                            ->update([
                                'title' => json_encode(
                                    [
                                        'en' => $item->title,
                                    ],
                                    JSON_UNESCAPED_UNICODE |
                                    JSON_UNESCAPED_SLASHES |
                                    JSON_THROW_ON_ERROR
                                ),
                            ]);
                    }
                }
            );

        /*
        |--------------------------------------------------------------------------
        | Change column type
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'menu_items',
            function (Blueprint $table): void {
                $table->json('title')
                    ->change();
            }
        );
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Convert JSON titles back to English text
        |--------------------------------------------------------------------------
        */

        DB::table('menu_items')
            ->orderBy('id')
            ->chunkById(
                100,
                function ($items): void {
                    foreach ($items as $item) {
                        if ($item->title === null) {
                            continue;
                        }

                        $translations = json_decode(
                            $item->title,
                            true
                        );

                        $englishTitle =
                            is_array($translations)
                                ? (
                                    $translations['en']
                                    ?? collect($translations)
                                        ->filter(
                                            fn ($value) => filled($value)
                                        )
                                        ->first()
                                )
                                : $item->title;

                        DB::table('menu_items')
                            ->where('id', $item->id)
                            ->update([
                                'title' => $englishTitle,
                            ]);
                    }
                }
            );

        Schema::table(
            'menu_items',
            function (Blueprint $table): void {
                $table->string(
                    'title',
                    150
                )->change();
            }
        );
    }
};
