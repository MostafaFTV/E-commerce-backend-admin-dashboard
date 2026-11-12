<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        /*
        |--------------------------------------------------------------------------
        | Persian Content
        |--------------------------------------------------------------------------
        */

        'name',
        'description',
        'address',
        'city',
        'floor_unit',
        'opening_hours',

        /*
        |--------------------------------------------------------------------------
        | English Content
        |--------------------------------------------------------------------------
        */

        'name_en',
        'description_en',
        'address_en',
        'city_en',
        'floor_unit_en',
        'opening_hours_en',

        /*
        |--------------------------------------------------------------------------
        | Shared Store Data
        |--------------------------------------------------------------------------
        */

        'slug',
        'phone',
        'email',
        'latitude',
        'longitude',
        'image',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'latitude' =>
                'decimal:7',

            'longitude' =>
                'decimal:7',

            'opening_hours' =>
                'array',

            'opening_hours_en' =>
                'array',

            'is_active' =>
                'boolean',

            'sort_order' =>
                'integer',
        ];
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }
}