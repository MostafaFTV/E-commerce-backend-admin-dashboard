<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Preserve Existing Category Data
        |--------------------------------------------------------------------------
        |
        | Existing single-language values are moved to the English locale.
        |
        | Example:
        |
        | Cookware
        |
        | becomes:
        |
        | {
        |     "en": "Cookware"
        | }
        |
        |--------------------------------------------------------------------------
        */

        DB::statement("
            UPDATE categories
            SET name = JSON_OBJECT(
                'en',
                name
            )
        ");

        DB::statement("
            UPDATE categories
            SET description = CASE
                WHEN description IS NULL THEN NULL
                ELSE JSON_OBJECT(
                    'en',
                    description
                )
            END
        ");

        /*
        |--------------------------------------------------------------------------
        | Convert Columns to JSON
        |--------------------------------------------------------------------------
        */

        Schema::table('categories', function (Blueprint $table) {
            $table->json('name')->change();
            $table->json('description')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Convert JSON Back to Plain Text
        |--------------------------------------------------------------------------
        |
        | The English translation becomes the original single-language value.
        |
        |--------------------------------------------------------------------------
        */

        Schema::table('categories', function (Blueprint $table) {
            $table->string('name', 255)->change();
            $table->text('description')->nullable()->change();
        });

        DB::statement("
            UPDATE categories
            SET name = JSON_UNQUOTE(
                JSON_EXTRACT(name, '$.en')
            )
        ");

        DB::statement("
            UPDATE categories
            SET description = CASE
                WHEN description IS NULL THEN NULL
                ELSE JSON_UNQUOTE(
                    JSON_EXTRACT(description, '$.en')
                )
            END
        ");
    }
};
