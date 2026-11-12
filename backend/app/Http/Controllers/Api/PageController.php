<?php

namespace App\Http\Controllers\Api;

use App\Enums\PageStatus;
use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;

class PageController extends Controller
{
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $validated = $request->validate([
            'per_page' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:48',
            ],
            'page' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:10000',
            ],
        ]);

        $perPage = $validated['per_page'] ?? 20;
        $page = $validated['page'] ?? null;

        $query = Page::query()
            ->where(
                'status',
                PageStatus::PUBLISHED
            )
            ->orderBy(
                'title->en'
            );

        if ($page !== null) {
            $pages = $query->paginate(
                $perPage,
                ['*'],
                'page',
                $page
            );
        } else {
            $pages = $query->paginate(
                $perPage
            );
        }

        return JsonResource::collection(
            $pages
        );
    }

    public function show(
        Request $request,
        string $slug
    ): JsonResource {
        /*
         * Public page slugs must use the canonical
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

        $page = Page::query()
            ->where(
                'slug',
                $slug
            )
            ->where(
                'status',
                PageStatus::PUBLISHED
            )
            ->firstOrFail();

        return JsonResource::make([
            'id' => $page->id,

            'locale' =>
                app()->getLocale(),

            'direction' =>
                $request->attributes->get(
                    'direction',
                    'ltr'
                ),

            'title' =>
                $page->title,

            'slug' =>
                $page->slug,

            /*
            |--------------------------------------------------------------------------
            | SEO Metadata
            |--------------------------------------------------------------------------
            */
            'seo_title' =>
                $page->seo_title,

            'seo_description' =>
                $page->seo_description,

            'seo_image' =>
                $page->seo_image,

            'content' =>
                $page->content,

            'status' =>
                $page->status?->value,

            'created_at' =>
                $page->created_at,

            'updated_at' =>
                $page->updated_at,
        ]);
    }
}