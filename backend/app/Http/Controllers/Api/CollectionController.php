<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CollectionResource;
use App\Models\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CollectionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */

    private const INDEX_CACHE_VERSION_KEY =
        'tkb:collections:index:version';

    private const INDEX_CACHE_TTL_MINUTES = 60;

    /*
    |--------------------------------------------------------------------------
    | Public Index
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:48',
            ],

            'page' => [
                'nullable',
                'integer',
                'min:1',
                'max:10000',
            ],
        ]);

        $perPage = (int) (
            $validated['per_page']
            ?? 20
        );

        $page = (int) (
            $validated['page']
            ?? 1
        );

        $locale = app()->getLocale();

        $cacheVersion = (int) Cache::store(config('cache.default'))->get(
            self::INDEX_CACHE_VERSION_KEY,
            1
        );

        $cacheKey = implode(':', [
            'tkb',
            'collections',
            'index',
            "v{$cacheVersion}",
            $locale,
            "per_page_{$perPage}",
            "page_{$page}",
        ]);

        $payload = Cache::store(config('cache.default'))->remember(
            $cacheKey,
            now()->addMinutes(
                self::INDEX_CACHE_TTL_MINUTES
            ),
            function () use (
                $perPage,
                $page
            ): array {
                $query = Collection::query()
                    ->where('is_active', true)
                    ->withCount([
                        'products' => function ($productQuery): void {
                            $productQuery
                                ->where(
                                    'status',
                                    'active'
                                )
                                ->whereHas(
                                    'category',
                                    function ($categoryQuery): void {
                                        $categoryQuery->where(
                                            'is_active',
                                            true
                                        );
                                    }
                                );
                        },
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('id');

                $collections = $query->paginate(
                    perPage: $perPage,
                    columns: ['*'],
                    pageName: 'page',
                    page: $page
                );

                return CollectionResource::collection(
                    $collections
                )
                    ->response()
                    ->getData(true);
            }
        );

        return response()->json(
            $payload
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Public Show
    |--------------------------------------------------------------------------
    */

    public function show(
        string $slug
    ): JsonResponse {
        abort_unless(
            preg_match(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $slug
            ),
            404
        );

        $collection = Collection::query()
            ->where(
                'slug',
                $slug
            )
            ->where(
                'is_active',
                true
            )
            ->withCount([
                'products' => function ($productQuery): void {
                    $productQuery
                        ->where(
                            'status',
                            'active'
                        )
                        ->whereHas(
                            'category',
                            function ($categoryQuery): void {
                                $categoryQuery->where(
                                    'is_active',
                                    true
                                );
                            }
                        );
                },
            ])
            ->with([
                'products' => function ($productQuery): void {
                    $productQuery
                        ->where(
                            'status',
                            'active'
                        )
                        ->whereHas(
                            'category',
                            function ($categoryQuery): void {
                                $categoryQuery->where(
                                    'is_active',
                                    true
                                );
                            }
                        )
                        ->with([
                            'category' => function ($categoryQuery): void {
                                $categoryQuery->where(
                                    'is_active',
                                    true
                                );
                            },

                            'collections' => function ($collectionQuery): void {
                                $collectionQuery->where(
                                    'is_active',
                                    true
                                );
                            },

                            'images',

                            'variants' => function ($variantQuery): void {
                                $variantQuery
                                    ->whereIn(
                                        'status',
                                        [
                                            'active',
                                            'made_to_order',
                                        ]
                                    )
                                    ->with([
                                        'attributeValues.attribute',
                                        'images',
                                        'inventory',
                                    ]);
                            },
                        ]);
                },
            ])
            ->firstOrFail();

        return (new CollectionResource(
            $collection
        ))
            ->response();
    }
}