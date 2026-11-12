<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopping_assistant_usage_logs', function (Blueprint $table): void {
            $table->string('failure_stage', 40)->nullable();
            $table->string('failure_category', 64)->nullable();
            $table->string('dispatch_state', 24)->nullable();
            $table->unsignedSmallInteger('provider_http_status')->nullable();
            $table->unsignedInteger('elapsed_ms')->nullable();
            $table->string('provider_request_id', 128)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shopping_assistant_usage_logs', function (Blueprint $table): void {
            $table->dropColumn([
                'failure_stage',
                'failure_category',
                'dispatch_state',
                'provider_http_status',
                'elapsed_ms',
                'provider_request_id',
            ]);
        });
    }
};
