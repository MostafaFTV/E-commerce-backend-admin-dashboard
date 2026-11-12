<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'faq_contents',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->string(
                        'content_key',
                        50
                    )
                    ->unique();

                $table
                    ->string(
                        'title',
                        255
                    );

                $table
                    ->string(
                        'title_en',
                        255
                    )
                    ->nullable();

                $table
                    ->text(
                        'intro'
                    )
                    ->nullable();

                $table
                    ->text(
                        'intro_en'
                    )
                    ->nullable();

                $table
                    ->boolean(
                        'is_active'
                    )
                    ->default(true);

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'faq_contents'
        );
    }
};