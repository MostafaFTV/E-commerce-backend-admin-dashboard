<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_assistant_product_knowledge', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('manufacturer');
            $table->string('model');
            $table->json('facts');
            $table->json('sources');
            $table->timestamp('verified_at')->nullable();
            $table->string('review_status', 20)->default('pending')->index();
            $table->timestamp('product_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_assistant_product_knowledge');
    }
};
