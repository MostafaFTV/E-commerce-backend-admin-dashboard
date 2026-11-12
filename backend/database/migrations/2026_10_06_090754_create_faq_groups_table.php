<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'faq_groups',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId(
                        'faq_content_id'
                    )
                    ->constrained(
                        'faq_contents'
                    )
                    ->cascadeOnDelete();

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
                    ->unsignedInteger(
                        'sort_order'
                    )
                    ->default(0);

                $table
                    ->boolean(
                        'is_active'
                    )
                    ->default(true);

                $table->timestamps();

                $table->index([
                    'faq_content_id',
                    'is_active',
                    'sort_order',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'faq_groups'
        );
    }
};