<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(
        Request $request
    ): array {
        $locale =
            $request->query(
                'locale'
            ) === 'en'
                ? 'en'
                : 'fa';

        $isEnglish =
            $locale === 'en';

        return [
            'id' =>
                $this->id,

            /*
            |--------------------------------------------------------------------------
            | Localized Content
            |--------------------------------------------------------------------------
            */

            'name' =>
                $isEnglish
                    ? (
                        $this->name_en
                        ?? $this->name
                    )
                    : $this->name,

            'description' =>
                $isEnglish
                    ? (
                        $this->description_en
                        ?? $this->description
                    )
                    : $this->description,

            'address' =>
                $isEnglish
                    ? (
                        $this->address_en
                        ?? $this->address
                    )
                    : $this->address,

            'city' =>
                $isEnglish
                    ? (
                        $this->city_en
                        ?? $this->city
                    )
                    : $this->city,

            'floor_unit' =>
                $isEnglish
                    ? (
                        $this->floor_unit_en
                        ?? $this->floor_unit
                    )
                    : $this->floor_unit,

            'opening_hours' =>
                $isEnglish
                    ? (
                        $this->opening_hours_en
                        ?? $this->opening_hours
                    )
                    : $this->opening_hours,


            /*
            |--------------------------------------------------------------------------
            | English Source Fields
            |--------------------------------------------------------------------------
            |
            | Admin uses these fields to edit the English version.
            |
            */

            'name_en' =>
                $this->name_en,

            'description_en' =>
                $this->description_en,

            'address_en' =>
                $this->address_en,

            'city_en' =>
                $this->city_en,

            'floor_unit_en' =>
                $this->floor_unit_en,

            'opening_hours_en' =>
                $this->opening_hours_en,


            /*
            |--------------------------------------------------------------------------
            | Shared Store Data
            |--------------------------------------------------------------------------
            */

            'slug' =>
                $this->slug,

            'phone' =>
                $this->phone,

            'email' =>
                $this->email,

            'latitude' =>
                $this->latitude !== null
                    ? (float) $this->latitude
                    : null,

            'longitude' =>
                $this->longitude !== null
                    ? (float) $this->longitude
                    : null,

            'image' =>
                $this->image,

            'is_active' =>
                (bool) $this->is_active,

            'sort_order' =>
                (int) $this->sort_order,

            'created_at' =>
                $this->created_at,

            'updated_at' =>
                $this->updated_at,
        ];
    }
}