<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomepageSectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            /*
            |--------------------------------------------------------------------------
            | Localization
            |--------------------------------------------------------------------------
            */

            'locale' => app()->getLocale(),

            'direction' => $request->attributes->get(
                'direction',
                'ltr'
            ),

            /*
            |--------------------------------------------------------------------------
            | Section
            |--------------------------------------------------------------------------
            */

            'section_key' => $this->section_key,

            'title' => $this->title,

            'subtitle' => $this->subtitle,

            'content' => $this->content,

            /*
            |--------------------------------------------------------------------------
            | Media
            |--------------------------------------------------------------------------
            */

            'image' => $this->resolveMediaUrl(
                $this->image
            ),

            'mobile_image' => $this->resolveMediaUrl(
                $this->mobile_image
            ),

            /*
            |--------------------------------------------------------------------------
            | Link / CTA
            |--------------------------------------------------------------------------
            */

            'link' => $this->link,

            'button_text' => $this->button_text,

            /*
            |--------------------------------------------------------------------------
            | Relations
            |--------------------------------------------------------------------------
            */

            'category' => $this->when(
                $this->relationLoaded('category')
                && $this->category,
                function () {

                    return [
                        'id' => $this->category->id,
                        'name' => $this->category->name,
                        'slug' => $this->category->slug,
                    ];

                }
            ),

            'product' => $this->when(
                $this->relationLoaded('product')
                && $this->product,
                function () {

                    return [
                        'id' => $this->product->id,
                        'name' => $this->product->name,
                        'slug' => $this->product->slug,
                        'price' => $this->product->price,
                        'status' => $this->product->status?->value,
                    ];

                }
            ),

            'collection' => $this->when(
                $this->relationLoaded('collection')
                && $this->collection,
                function () {

                    return [
                        'id' => $this->collection->id,
                        'name' => $this->collection->name,
                        'slug' => $this->collection->slug,
                    ];

                }
            ),

            /*
            |--------------------------------------------------------------------------
            | Management
            |--------------------------------------------------------------------------
            */

            'is_active' => (bool) $this->is_active,

            'sort_order' => $this->sort_order,

            /*
            |--------------------------------------------------------------------------
            | Timestamps
            |--------------------------------------------------------------------------
            */

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,

        ];
    }

    /**
     * Resolve backend-managed media paths.
     *
     * - Absolute HTTP(S) URLs are returned unchanged.
     * - /storage/... paths are converted to absolute backend URLs.
     * - Existing frontend paths such as /assets/... remain unchanged
     *   until those sections are migrated to backend storage.
     */
    protected function resolveMediaUrl(
        ?string $value
    ): ?string {
        if (
            $value === null
            || trim($value) === ''
        ) {
            return null;
        }

        $value = trim($value);

        /*
        |--------------------------------------------------------------------------
        | Already Absolute URL
        |--------------------------------------------------------------------------
        */

        if (
            str_starts_with(
                $value,
                'http://'
            )
            || str_starts_with(
                $value,
                'https://'
            )
        ) {
            return $value;
        }

        /*
        |--------------------------------------------------------------------------
        | Backend-managed Storage
        |--------------------------------------------------------------------------
        */

        if (
            str_starts_with(
                $value,
                '/storage/'
            )
        ) {
            return url($value);
        }

        /*
        |--------------------------------------------------------------------------
        | Existing Frontend / Relative Paths
        |--------------------------------------------------------------------------
        */

        return $value;
    }
}