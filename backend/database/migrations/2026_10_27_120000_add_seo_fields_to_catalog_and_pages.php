<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'products',
        'categories',
        'collections',
        'pages',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $schema): void {
                $schema->json('seo_title')->nullable();
                $schema->json('seo_description')->nullable();
                $schema->string('seo_image', 2048)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $schema): void {
                $schema->dropColumn([
                    'seo_title',
                    'seo_description',
                    'seo_image',
                ]);
            });
        }
    }
};