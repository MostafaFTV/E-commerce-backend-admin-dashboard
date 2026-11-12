<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BannerController extends Controller
{
    /**
     * Display currently active banners.
     */
    public function index(): AnonymousResourceCollection
    {
        $now = now();

        $banners = Banner::query()
            ->where('is_active', true)

            ->where(function ($query) use ($now) {
                $query
                    ->whereNull('starts_at')
                    ->orWhere(
                        'starts_at',
                        '<=',
                        $now
                    );
            })

            ->where(function ($query) use ($now) {
                $query
                    ->whereNull('ends_at')
                    ->orWhere(
                        'ends_at',
                        '>=',
                        $now
                    );
            })

            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return BannerResource::collection(
            $banners
        );
    }
}