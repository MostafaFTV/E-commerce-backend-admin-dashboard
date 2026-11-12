<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_assistant_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->char('conversation_fingerprint', 64);
            $table->string('provider', 32);
            $table->string('model', 100);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 12, 8)->nullable();
            $table->string('status', 20);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['provider', 'status', 'created_at'], 'shopping_assistant_usage_provider_status_created_index');
            $table->index('conversation_fingerprint', 'shopping_assistant_usage_conversation_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_assistant_usage_logs');
    }
};
