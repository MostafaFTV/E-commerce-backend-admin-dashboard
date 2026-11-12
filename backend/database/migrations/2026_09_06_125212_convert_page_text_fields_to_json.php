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

        DB::table('pages')
            ->select([
                'id',
                'title',
                'content',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($pages) {
                foreach ($pages as $page) {
                    $title = $page->title !== null
                        ? json_encode(
                            [
                                'en' => $page->title,
                            ],
                            JSON_UNESCAPED_UNICODE |
                            JSON_UNESCAPED_SLASHES
                        )
                        : null;

                    $content = $page->content !== null
                        ? json_encode(
                            [
                                'en' => $page->content,
                            ],
                            JSON_UNESCAPED_UNICODE |
                            JSON_UNESCAPED_SLASHES
                        )
                        : null;

                    DB::table('pages')
                        ->where('id', $page->id)
                        ->update([
                            'title' => $title,
                            'content' => $content,
                        ]);
                }
            });

        /*
        |--------------------------------------------------------------------------
        | Convert text fields to JSON
        |--------------------------------------------------------------------------
        */

        Schema::table('pages', function (Blueprint $table) {
            $table->json('title')->change();

            $table->json('content')->change();
        });
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Convert JSON translations back to text
        |--------------------------------------------------------------------------
        */

        $pages = DB::table('pages')
            ->select([
                'id',
                'title',
                'content',
            ])
            ->orderBy('id')
            ->get();

        foreach ($pages as $page) {
            $title = $this->extractEnglish(
                $page->title
            );

            $content = $this->extractEnglish(
                $page->content
            );

            DB::table('pages')
                ->where('id', $page->id)
                ->update([
                    'title' => $title,
                    'content' => $content,
                ]);
        }

        Schema::table('pages', function (Blueprint $table) {
            $table->string('title', 255)->change();

            $table->text('content')->change();
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

        if (! is_array($decoded)) {
            return null;
        }

        return $decoded['en'] ?? null;
    }
};
