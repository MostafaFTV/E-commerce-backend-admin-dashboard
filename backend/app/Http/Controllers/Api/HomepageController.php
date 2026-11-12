<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Http\Resources\HomepageSectionResource;
use App\Models\Banner;
use App\Models\HomepageSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class HomepageController extends Controller
{
    private const CACHE_TTL_MINUTES = 60;

    public function index(): JsonResponse
    {
        $locale = app()->getLocale();

        $cacheKey = "tkb:homepage:v2:{$locale}";

        $cache = Cache::store(config('cache.default'));

        $cachedPayload = $cache->get($cacheKey);

        if ($cachedPayload !== null) {
            return response()->json($cachedPayload);
        }

        $now = now();

        /*
        |--------------------------------------------------------------------------
        | Banners
        |--------------------------------------------------------------------------
        |
        | Load currently active banners plus future scheduled banners.
        | This allows us to:
        |
        | 1. Return only currently active banners.
        | 2. Determine the nearest schedule boundary.
        | 3. Avoid extra queries when calculating cache TTL.
        |
        |--------------------------------------------------------------------------
        */

        $scheduledBanners = Banner::query()
            ->where('is_active', true)
            ->where(function ($query) use ($now): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhere(
                        'ends_at',
                        '>=',
                        $now
                    );
            })
            ->where(function ($query): void {
                $query
                    ->whereNull('starts_at')
                    ->orWhere(
                        'starts_at',
                        '<=',
                        now()->addYears(10)
                    );
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $banners = $scheduledBanners
            ->filter(
                function (Banner $banner) use ($now): bool {
                    if (
                        $banner->starts_at
                        && $now->lt($banner->starts_at)
                    ) {
                        return false;
                    }

                    if (
                        $banner->ends_at
                        && $now->gt($banner->ends_at)
                    ) {
                        return false;
                    }

                    return true;
                }
            )
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Homepage Sections
        |--------------------------------------------------------------------------
        */

        $sections = HomepageSection::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->with([
                'category' => function ($query): void {
                    $query
                        ->where('is_active', true)
                        ->select([
                            'id',
                            'name',
                            'slug',
                            'is_active',
                        ]);
                },

                'product' => function ($query): void {
                    $query
                        ->whereIn('status', [
                            'active',
                            'made_to_order',
                        ])
                        ->whereHas(
                            'category',
                            function ($categoryQuery): void {
                                $categoryQuery->where(
                                    'is_active',
                                    true
                                );
                            }
                        )
                        ->select([
                            'id',
                            'category_id',
                            'name',
                            'slug',
                            'price',
                            'status',
                        ]);
                },

                'collection' => function ($query): void {
                    $query
                        ->where('is_active', true)
                        ->select([
                            'id',
                            'name',
                            'slug',
                            'is_active',
                        ]);
                },
            ])
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Determine Cache Expiration
        |--------------------------------------------------------------------------
        */

        $nearestBoundary = null;

        foreach ($scheduledBanners as $banner) {
            foreach (
                [
                    $banner->starts_at,
                    $banner->ends_at,
                ] as $boundary
            ) {
                if (! $boundary) {
                    continue;
                }

                $boundary = $boundary instanceof Carbon
                    ? $boundary
                    : Carbon::parse($boundary);

                if ($boundary->lte($now)) {
                    continue;
                }

                if (
                    $nearestBoundary === null
                    || $boundary->lt($nearestBoundary)
                ) {
                    $nearestBoundary = $boundary;
                }
            }
        }

        $cacheExpiresAt = $now->copy()->addMinutes(
            self::CACHE_TTL_MINUTES
        );

        if (
            $nearestBoundary !== null
            && $nearestBoundary->lt($cacheExpiresAt)
        ) {
            $cacheExpiresAt = $nearestBoundary;
        }

        /*
        |--------------------------------------------------------------------------
        | Build Payload
        |--------------------------------------------------------------------------
        */

        $payload = [
            'data' => [
                'banners' => BannerResource::collection(
                    $banners
                )->resolve(),

                'sections' => HomepageSectionResource::collection(
                    $sections
                )->resolve(),
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | Store Final Payload
        |--------------------------------------------------------------------------
        |
        | We cache the final JSON-ready array, never Eloquent models.
        |
        |--------------------------------------------------------------------------
        */

        $payload = json_decode(
            json_encode($payload, JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $cache->put(
            $cacheKey,
            $payload,
            $cacheExpiresAt
        );

        return response()->json($payload);
    }
}