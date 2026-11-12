<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
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
            | Content
            |--------------------------------------------------------------------------
            */

            'title' => $this->title,

            'subtitle' => $this->subtitle,

            'button_text' => $this->button_text,

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
            | Link
            |--------------------------------------------------------------------------
            */

            'link' => $this->link,

            /*
            |--------------------------------------------------------------------------
            | Management
            |--------------------------------------------------------------------------
            */

            'sort_order' => $this->sort_order,

            'is_active' => (bool) $this->is_active,

            /*
            |--------------------------------------------------------------------------
            | Scheduling
            |--------------------------------------------------------------------------
            */

            'starts_at' => $this->starts_at,

            'ends_at' => $this->ends_at,

            'is_currently_active' =>
                $this->isCurrentlyActive(),

            /*
            |--------------------------------------------------------------------------
            | Timestamps
            |--------------------------------------------------------------------------
            */

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }

    private function resolveMediaUrl(
        ?string $path
    ): ?string {
        if (
            $path === null
            || trim($path) === ''
        ) {
            return null;
        }

        if (
            str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
        ) {
            return $path;
        }

        return url(
            '/' . ltrim($path, '/')
        );
    }
}