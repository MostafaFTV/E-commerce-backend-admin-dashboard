<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE product_variants
            MODIFY status ENUM(
                'active',
                'made_to_order',
                'sold',
                'inactive'
            ) NOT NULL DEFAULT 'active'
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            UPDATE product_variants
            SET status = 'inactive'
            WHERE status IN ('made_to_order', 'sold')
        ");

        DB::statement("
            ALTER TABLE product_variants
            MODIFY status ENUM(
                'active',
                'inactive'
            ) NOT NULL DEFAULT 'active'
        ");
    }
};
