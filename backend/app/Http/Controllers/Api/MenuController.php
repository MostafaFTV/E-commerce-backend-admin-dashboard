<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;

class MenuController extends Controller
{
    public function show(
        Request $request,
        string $slug
    ): JsonResource {
        /*
         * Public menu slugs must use the canonical
         * lowercase URL-safe format.
         */
        if (
            ! preg_match(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $slug
            )
        ) {
            throw ValidationException::withMessages([
                'slug' => [
                    'The selected slug is invalid.',
                ],
            ]);
        }

        $menu = Menu::query()
            ->where(
                'slug',
                $slug
            )
            ->where(
                'is_active',
                true
            )
            ->firstOrFail();

        $items = MenuItem::query()
            ->where(
                'menu_id',
                $menu->id
            )
            ->where(
                'is_active',
                true
            )
            ->whereNull('parent_id')
            ->with([
                'children' => function ($query) {
                    $query
                        ->where(
                            'is_active',
                            true
                        )
                        ->orderBy(
                            'sort_order'
                        )
                        ->orderBy(
                            'id'
                        )
                        ->with([
                            'children' => function ($childQuery) {
                                $childQuery
                                    ->where(
                                        'is_active',
                                        true
                                    )
                                    ->orderBy(
                                        'sort_order'
                                    )
                                    ->orderBy(
                                        'id'
                                    );
                            },
                            'category',
                            'product',
                            'collection',
                            'page',
                        ]);
                },
                'category',
                'product',
                'collection',
                'page',
            ])
            ->orderBy(
                'sort_order'
            )
            ->orderBy(
                'id'
            )            ->get();

        $categoryNamesBySlug =
            Category::query()
                ->where(
                    'is_active',
                    true
                )
                ->get([
                    'slug',
                    'name',
                ])
                ->mapWithKeys(
                    fn (Category $category): array => [
                        $category->slug =>
                            $category->name,
                    ]
                )
                ->all();

        return JsonResource::make([
            'id' =>
                $menu->id,

            'locale' =>
                app()->getLocale(),

            'direction' =>
                $request->attributes->get(
                    'direction',
                    'ltr'
                ),

            'name' =>
                $menu->name,

            'slug' =>
                $menu->slug,

            'location' =>
                $menu->location,

            'items' =>
                $items
                    ->map(
                        fn (MenuItem $item) =>
                            $this->transformItem($item, $categoryNamesBySlug)
                    )
                    ->values()
                    ->all(),
        ]);
    }

    protected function transformItem(
        MenuItem $item,
        array $categoryNamesBySlug
    ): array {
        $title =
            $item->title;

        $categorySlug =
            $this->categorySlugFromUrl(
                $item->url
            );

        if (
            $categorySlug !== null
            && array_key_exists(
                $categorySlug,
                $categoryNamesBySlug
            )
        ) {
            $title =
                $categoryNamesBySlug[
                    $categorySlug
                ];
        }
        return [
            'id' =>
                $item->id,

            'parent_id' =>
                $item->parent_id,

            'title' =>
                $title,

            'type' =>
                $item->type?->value,

            'url' =>
                $item->url,

            'target_id' =>
                match ($item->type?->value) {
                    'category' =>
                        $item->category_id,

                    'product' =>
                        $item->product_id,

                    'collection' =>
                        $item->collection_id,

                    'page' =>
                        $item->page_id,

                    default =>
                        null,
                },

            'sort_order' =>
                $item->sort_order,

            'children' =>
                $item->relationLoaded('children')
                    ? $item->children
                        ->map(
                            fn (MenuItem $child) =>
                                $this->transformItem($child, $categoryNamesBySlug)
                        )
                        ->values()
                        ->all()
                    : [],
        ];
    }

    protected function categorySlugFromUrl(
        ?string $url
    ): ?string {
        if (
            ! is_string($url)
            || trim($url) === ''
        ) {
            return null;
        }

        $path =
            parse_url(
                $url,
                PHP_URL_PATH
            );

        if (
            ! is_string($path)
            || ! str_starts_with(
                $path,
                '/category/'
            )
        ) {
            return null;
        }

        $segments =
            array_values(
                array_filter(
                    explode(
                        '/',
                        trim(
                            $path,
                            '/'
                        )
                    )
                )
            );

        if (
            ($segments[0] ?? null)
                !== 'category'
        ) {
            return null;
        }

        $slug =
            end($segments);

        return is_string($slug)
            && $slug !== ''
                ? $slug
                : null;
    }}