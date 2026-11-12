<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement("
                CREATE UNIQUE INDEX checkout_sessions_one_active_per_user_unique
                ON checkout_sessions (user_id)
                WHERE status = 'active'
            ");

            DB::statement("
                CREATE UNIQUE INDEX checkout_sessions_one_active_per_cart_unique
                ON checkout_sessions (cart_id)
                WHERE status = 'active'
            ");

            return;
        }

        DB::statement("
            CREATE UNIQUE INDEX checkout_sessions_one_active_per_user_unique
            ON checkout_sessions (
                (IF(status = 'active', user_id, NULL))
            )
        ");

        DB::statement("
            CREATE UNIQUE INDEX checkout_sessions_one_active_per_cart_unique
            ON checkout_sessions (
                (IF(status = 'active', cart_id, NULL))
            )
        ");
    }

    public function down(): void
    {
        DB::statement("
            DROP INDEX checkout_sessions_one_active_per_user_unique
        ");

        DB::statement("
            DROP INDEX checkout_sessions_one_active_per_cart_unique
        ");
    }
};