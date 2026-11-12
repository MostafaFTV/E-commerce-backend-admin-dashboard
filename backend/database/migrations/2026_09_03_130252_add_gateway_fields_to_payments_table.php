<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway_reference', 255)
                ->nullable()
                ->after('transaction_id');

            $table->json('gateway_response')
                ->nullable()
                ->after('method');

            $table->index(
                ['method', 'gateway_reference'],
                'payments_method_gateway_reference_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(
                'payments_method_gateway_reference_index'
            );

            $table->dropColumn([
                'gateway_reference',
                'gateway_response',
            ]);
        });
    }
};