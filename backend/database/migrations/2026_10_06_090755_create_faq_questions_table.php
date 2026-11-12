<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'faq_questions',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId(
                        'faq_group_id'
                    )
                    ->constrained(
                        'faq_groups'
                    )
                    ->cascadeOnDelete();

                $table
                    ->string(
                        'question',
                        500
                    );

                $table
                    ->string(
                        'question_en',
                        500
                    )
                    ->nullable();

                $table->text(
                    'answer'
                );

                $table
                    ->text(
                        'answer_en'
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
                    'faq_group_id',
                    'is_active',
                    'sort_order',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'faq_questions'
        );
    }
};