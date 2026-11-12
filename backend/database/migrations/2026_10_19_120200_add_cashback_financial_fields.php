<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('total_before_wallet', 12, 2)
                ->nullable()
                ->after('total');

            $table->decimal('wallet_credit_used', 12, 2)
                ->default(0)
                ->after('total_before_wallet');
        });

        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->decimal('wallet_credit_requested', 12, 2)
                ->default(0)
                ->after('shipping_method_id');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->dropColumn('wallet_credit_requested');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'total_before_wallet',
                'wallet_credit_used',
            ]);
        });
    }
};