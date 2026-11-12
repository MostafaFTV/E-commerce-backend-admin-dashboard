<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $localizedFields = [
        'title',
        'subtitle',
        'button_text',
    ];

    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Preserve existing banner content under English
        |--------------------------------------------------------------------------
        */

        DB::table('banners')
            ->orderBy('id')
            ->chunkById(
                100,
                function ($banners): void {
                    foreach ($banners as $banner) {
                        $updates = [];

                        foreach ($this->localizedFields as $field) {
                            $value = $banner->{$field};

                            if ($value === null) {
                                continue;
                            }

                            $updates[$field] = json_encode(
                                [
                                    'en' => $value,
                                ],
                                JSON_UNESCAPED_UNICODE |
                                JSON_UNESCAPED_SLASHES |
                                JSON_THROW_ON_ERROR
                            );
                        }

                        if ($updates !== []) {
                            DB::table('banners')
                                ->where('id', $banner->id)
                                ->update($updates);
                        }
                    }
                }
            );

        /*
        |--------------------------------------------------------------------------
        | Convert text columns to JSON
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'banners',
            function (Blueprint $table): void {
                $table->json('title')
                    ->nullable()
                    ->change();

                $table->json('subtitle')
                    ->nullable()
                    ->change();

                $table->json('button_text')
                    ->nullable()
                    ->change();
            }
        );
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Restore English values
        |--------------------------------------------------------------------------
        */

        DB::table('banners')
            ->orderBy('id')
            ->chunkById(
                100,
                function ($banners): void {
                    foreach ($banners as $banner) {
                        $updates = [];

                        foreach ($this->localizedFields as $field) {
                            $rawValue = $banner->{$field};

                            if ($rawValue === null) {
                                continue;
                            }

                            $translations = json_decode(
                                $rawValue,
                                true
                            );

                            if (is_array($translations)) {
                                $value =
                                    $translations['en']
                                    ?? collect($translations)
                                        ->filter(
                                            fn ($translation) =>
                                                filled($translation)
                                        )
                                        ->first();
                            } else {
                                $value = $rawValue;
                            }

                            $updates[$field] = $value;
                        }

                        if ($updates !== []) {
                            DB::table('banners')
                                ->where('id', $banner->id)
                                ->update($updates);
                        }
                    }
                }
            );

        /*
        |--------------------------------------------------------------------------
        | Convert columns back to original types
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'banners',
            function (Blueprint $table): void {
                $table->string(
                    'title',
                    255
                )
                    ->nullable()
                    ->change();

                $table->string(
                    'subtitle',
                    500
                )
                    ->nullable()
                    ->change();

                $table->string(
                    'button_text',
                    100
                )
                    ->nullable()
                    ->change();
            }
        );
    }
};
