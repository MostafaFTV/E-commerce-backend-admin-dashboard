<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('birth_date')->nullable();

            $table->boolean('marketing_sms')
                ->default(false);
        });

        Schema::create('category_user_preferences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->unique(
                ['user_id', 'category_id'],
                'category_user_preferences_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_user_preferences');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'birth_date',
                'marketing_sms',
            ]);
        });
    }
};