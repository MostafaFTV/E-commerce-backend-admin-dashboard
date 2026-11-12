<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    private const TREE_CACHE_TTL_MINUTES = 60;

    public function tree(): JsonResponse
    {
        $locale = app()->getLocale();
        $cacheKey = "tkb:categories:tree:{$locale}";

        $payload = Cache::store(config('cache.default'))->remember(
            $cacheKey,
            now()->addMinutes(self::TREE_CACHE_TTL_MINUTES),
            function (): array {
                $categories = Category::activeRoot()
                    ->with('childrenRecursive')
                    ->get();

                return CategoryResource::collection(
                    $categories
                )
                    ->response()
                    ->getData(true);
            }
        );

        return response()->json(
            $payload
        );
    }

    public function index()
    {
        $categories = Category::active()
            ->orderBy('sort_order')
            ->get();

        return CategoryResource::collection(
            $categories
        );
    }
}