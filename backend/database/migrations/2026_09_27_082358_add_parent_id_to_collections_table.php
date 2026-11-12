<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('id')
                ->constrained(
                    'collections',
                    'id',
                    'collections_parent_id_foreign'
                )
                ->nullOnDelete();

            $table->index(
                [
                    'parent_id',
                    'is_active',
                    'sort_order',
                ],
                'collections_parent_active_sort_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropIndex(
                'collections_parent_active_sort_index'
            );

            $table->dropForeign(
                'collections_parent_id_foreign'
            );

            $table->dropColumn('parent_id');
        });
    }
};