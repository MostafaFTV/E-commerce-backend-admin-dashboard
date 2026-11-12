<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('stores')
            ->where('slug', 'tehran')
            ->update([
                'name_en' => 'Tehran',
                'description_en' => 'Fereshteh Boutique Mall',
                'address_en' => 'Boutique Mall, Fereshteh, Tehran',
                'city_en' => 'Tehran',

                'floor_unit' => 'طبقه اول، واحد ۱۰',
                'floor_unit_en' => '1st Floor · Unit 10',

                'opening_hours' => json_encode(
                    [
                        'شنبه تا پنج‌شنبه: ۸:۰۰ تا ۲۰:۰۰',
                        'جمعه: تعطیل',
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),

                'opening_hours_en' => json_encode(
                    [
                        'Saturday–Thursday: 08:00–20:00',
                        'Friday: Closed',
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),

                'sort_order' => 10,
            ]);

        DB::table('stores')
            ->where('slug', 'nowshahr')
            ->update([
                'name_en' => 'Nowshahr',
                'description_en' => 'Araz Hotel Nowshahr',
                'address_en' => 'Araz Hotel, Nowshahr',
                'city_en' => 'Nowshahr',

                'floor_unit' => 'طبقه سوم، واحد ۳۰',
                'floor_unit_en' => '3rd Floor · Unit 30',

                'opening_hours' => json_encode(
                    [
                        'شنبه تا پنج‌شنبه: ۸:۰۰ تا ۲۰:۰۰',
                        'جمعه: تعطیل',
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),

                'opening_hours_en' => json_encode(
                    [
                        'Saturday–Thursday: 08:00–20:00',
                        'Friday: Closed',
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),

                'sort_order' => 20,
            ]);
    }

    public function down(): void
    {
        DB::table('stores')
            ->whereIn(
                'slug',
                [
                    'tehran',
                    'nowshahr',
                ],
            )
            ->update([
                'name_en' => null,
                'description_en' => null,
                'address_en' => null,
                'city_en' => null,
                'floor_unit' => null,
                'floor_unit_en' => null,
                'opening_hours' => null,
                'opening_hours_en' => null,
                'sort_order' => 0,
            ]);
    }
};