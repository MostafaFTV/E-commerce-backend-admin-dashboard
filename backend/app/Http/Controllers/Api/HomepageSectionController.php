<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HomepageSectionResource;
use App\Models\HomepageSection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HomepageSectionController extends Controller
{
    /**
     * Display active homepage sections.
     */
    public function index(): AnonymousResourceCollection
    {
        $sections = HomepageSection::query()
            ->where(
                'is_active',
                true
            )
            ->with([
                'category',
                'product',
                'collection',
            ])
            ->orderBy(
                'sort_order'
            )
            ->orderBy(
                'id'
            )
            ->get();

        return HomepageSectionResource::collection(
            $sections
        );
    }
}
