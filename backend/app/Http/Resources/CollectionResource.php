<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionResource extends JsonResource
{
    public bool $includeAdminTranslations = false;

    public static function admin($resource): self
    {
        $collection =
            new static($resource);

        $collection->includeAdminTranslations =
            true;

        return $collection;
    }

    public static function adminCollection($resource)
    {
        $collection =
            static::collection(
                $resource
            );

        $collection
            ->collection
            ->each(
                function (
                    self $item
                ): void {
                    $item
                        ->includeAdminTranslations =
                        true;
                }
            );

        return $collection;
    }

    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'locale' =>
                app()->getLocale(),

            'direction' =>
                $request->attributes->get(
                    'direction',
                    'ltr'
                ),

            /*
             * Existing public API contract:
             * keep these localized.
             */
            'name' =>
                $this->name,

            'slug' =>
                $this->slug,

            'description' =>
                $this->description,

            /*
             * Admin-only complete translations.
             */
            'name_translations' =>
                $this->when(
                    $this->includeAdminTranslations,
                    fn () =>
                        $this->translationMap(
                            $this->resource,
                            'name'
                        )
                ),

            'description_translations' =>
                $this->when(
                    $this->includeAdminTranslations,
                    fn () =>
                        $this->translationMap(
                            $this->resource,
                            'description'
                        )
                ),

            /*
            |--------------------------------------------------------------------------
            | SEO Metadata
            |--------------------------------------------------------------------------
            */
            'seo_title' =>
                $this->seo_title,

            'seo_description' =>
                $this->seo_description,

            'seo_image' =>
                $this->seo_image,

            'seo_title_translations' =>
                $this->when(
                    $this->includeAdminTranslations,
                    fn () =>
                        $this->translationMap(
                            $this->resource,
                            'seo_title'
                        )
                ),

            'seo_description_translations' =>
                $this->when(
                    $this->includeAdminTranslations,
                    fn () =>
                        $this->translationMap(
                            $this->resource,
                            'seo_description'
                        )
                ),

            'image' =>
                $this->image,

            'is_active' =>
                (bool) $this->is_active,

            'sort_order' =>
                (int) $this->sort_order,

            'products_count' =>
                $this->whenCounted(
                    'products'
                ),

            'products' =>
                ProductResource::collection(
                    $this->whenLoaded(
                        'products'
                    )
                ),

            'created_at' =>
                $this->created_at,

            'updated_at' =>
                $this->updated_at,
        ];
    }

    protected function translationMap(
        $model,
        string $field
    ): ?array {
        $raw =
            $model->getRawOriginal(
                $field
            );

        if ($raw === null) {
            return null;
        }

        if (is_array($raw)) {
            return $raw;
        }

        if (
            ! is_string($raw) ||
            trim($raw) === ''
        ) {
            return null;
        }

        $decoded =
            json_decode(
                $raw,
                true
            );

        return is_array($decoded)
            ? $decoded
            : null;
    }
}