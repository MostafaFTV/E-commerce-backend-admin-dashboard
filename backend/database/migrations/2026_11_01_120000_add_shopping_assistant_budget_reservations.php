<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_assistant_budget_locks', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::table('shopping_assistant_budget_locks')->insert([
            'id' => 1,
            'created_at' => now(),
        ]);

        Schema::create('shopping_assistant_budget_reservations', function (Blueprint $table): void {
            $table->id();
            $table->char('conversation_fingerprint', 64);
            $table->date('budget_date');
            $table->char('budget_month', 7);
            $table->string('reserved_cost_usd', 32);
            $table->string('actual_cost_usd', 32)->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->index(['budget_date', 'status'], 'shopping_assistant_budget_date_status_index');
            $table->index(['budget_month', 'status'], 'shopping_assistant_budget_month_status_index');
        });

        Schema::table('shopping_assistant_usage_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('budget_reservation_id')->nullable()->unique();
            $table->string('estimated_cost_usd', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('shopping_assistant_usage_logs', function (Blueprint $table): void {
            $table->dropUnique(['budget_reservation_id']);
            $table->dropColumn('budget_reservation_id');
            $table->decimal('estimated_cost_usd', 12, 8)->nullable()->change();
        });

        Schema::dropIfExists('shopping_assistant_budget_reservations');
        Schema::dropIfExists('shopping_assistant_budget_locks');
    }
};
