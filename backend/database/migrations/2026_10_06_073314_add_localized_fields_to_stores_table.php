<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'stores',
            function (Blueprint $table) {
                /*
                |--------------------------------------------------------------------------
                | English Content
                |--------------------------------------------------------------------------
                */

                $table
                    ->string(
                        'name_en',
                        150
                    )
                    ->nullable()
                    ->after('name');

                $table
                    ->text(
                        'description_en'
                    )
                    ->nullable()
                    ->after('description');

                $table
                    ->text(
                        'address_en'
                    )
                    ->nullable()
                    ->after('address');

                $table
                    ->string(
                        'city_en',
                        100
                    )
                    ->nullable()
                    ->after('city');


                /*
                |--------------------------------------------------------------------------
                | Floor / Unit
                |--------------------------------------------------------------------------
                */

                $table
                    ->string(
                        'floor_unit',
                        150
                    )
                    ->nullable()
                    ->after('city_en');

                $table
                    ->string(
                        'floor_unit_en',
                        150
                    )
                    ->nullable()
                    ->after('floor_unit');


                /*
                |--------------------------------------------------------------------------
                | Opening Hours
                |--------------------------------------------------------------------------
                */

                $table
                    ->json(
                        'opening_hours_en'
                    )
                    ->nullable()
                    ->after('opening_hours');


                /*
                |--------------------------------------------------------------------------
                | Display Order
                |--------------------------------------------------------------------------
                */

                $table
                    ->unsignedInteger(
                        'sort_order'
                    )
                    ->default(0)
                    ->after('is_active');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'stores',
            function (Blueprint $table) {
                $table->dropColumn([
                    'name_en',
                    'description_en',
                    'address_en',
                    'city_en',
                    'floor_unit',
                    'floor_unit_en',
                    'opening_hours_en',
                    'sort_order',
                ]);
            }
        );
    }
};