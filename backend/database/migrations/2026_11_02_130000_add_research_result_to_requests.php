<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopping_assistant_research_requests', function (Blueprint $table): void {
            $table->json('result')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shopping_assistant_research_requests', function (Blueprint $table): void {
            $table->dropColumn('result');
        });
    }
};