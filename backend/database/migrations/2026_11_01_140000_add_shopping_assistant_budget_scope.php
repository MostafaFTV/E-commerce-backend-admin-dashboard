<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopping_assistant_budget_reservations', function (Blueprint $table): void {
            $table->string('budget_scope', 20)->default('standard');
            $table->index(
                ['budget_scope', 'budget_date', 'status'],
                'shopping_assistant_budget_scope_date_status_index'
            );
            $table->index(
                ['budget_scope', 'budget_month', 'status'],
                'shopping_assistant_budget_scope_month_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('shopping_assistant_budget_reservations', function (Blueprint $table): void {
            $table->dropIndex('shopping_assistant_budget_scope_date_status_index');
            $table->dropIndex('shopping_assistant_budget_scope_month_status_index');
            $table->dropColumn('budget_scope');
        });
    }
};
