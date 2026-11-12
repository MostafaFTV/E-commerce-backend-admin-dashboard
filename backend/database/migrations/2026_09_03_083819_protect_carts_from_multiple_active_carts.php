<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement("
                CREATE UNIQUE INDEX carts_one_active_cart_per_user_unique
                ON carts (user_id)
                WHERE status = 'active' AND user_id IS NOT NULL
            ");

            DB::statement("
                CREATE UNIQUE INDEX carts_one_active_cart_per_session_unique
                ON carts (session_id)
                WHERE status = 'active' AND session_id IS NOT NULL
            ");

            return;
        }

        DB::statement("
            CREATE UNIQUE INDEX carts_one_active_cart_per_user_unique
            ON carts (
                (IF(status = 'active', user_id, NULL))
            )
        ");

        DB::statement("
            CREATE UNIQUE INDEX carts_one_active_cart_per_session_unique
            ON carts (
                (IF(status = 'active', session_id, NULL))
            )
        ");
    }

    public function down(): void
    {
        DB::statement("
            DROP INDEX carts_one_active_cart_per_user_unique
        ");

        DB::statement("
            DROP INDEX carts_one_active_cart_per_session_unique
        ");
    }
};