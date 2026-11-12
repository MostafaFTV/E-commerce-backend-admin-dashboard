<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public bool $includeAdminTranslations = false;

    public static function admin($resource): self
    {
        $category =
            new static($resource);

        $category->includeAdminTranslations =
            true;

        return $category;
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
                    self $category
                ): void {
                    $category
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

            'parent_id' =>
                $this->parent_id,

            'locale' =>
                app()->getLocale(),

            'direction' =>
                $request->attributes->get(
                    'direction',
                    'ltr'
                ),

            /*
             * Existing API contract:
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

            'parent' =>
                $this->when(
                    $this->relationLoaded(
                        'parent'
                    ) &&
                    $this->parent,
                    function (): array {
                        $parent = [
                            'id' =>
                                $this
                                    ->parent
                                    ->id,

                            'name' =>
                                $this
                                    ->parent
                                    ->name,

                            'slug' =>
                                $this
                                    ->parent
                                    ->slug,
                        ];

                        if (
                            $this
                                ->includeAdminTranslations
                        ) {
                            $parent[
                                'name_translations'
                            ] =
                                $this->translationMap(
                                    $this->parent,
                                    'name'
                                );
                        }

                        return $parent;
                    }
                ),

            'children' =>
                $this->when(
                    $this->relationLoaded(
                        'childrenRecursive'
                    ),
                    function () use (
                        $request
                    ) {
                        return $this
                            ->childrenRecursive
                            ->map(
                                function (
                                    $child
                                ) use (
                                    $request
                                ): array {
                                    $resource =
                                        $this
                                            ->includeAdminTranslations
                                            ? self::admin(
                                                $child
                                            )
                                            : new self(
                                                $child
                                            );

                                    return $resource
                                        ->toArray(
                                            $request
                                        );
                                }
                            )
                            ->values()
                            ->all();
                    }
                ),

            'products_count' =>
                $this->whenCounted(
                    'products'
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