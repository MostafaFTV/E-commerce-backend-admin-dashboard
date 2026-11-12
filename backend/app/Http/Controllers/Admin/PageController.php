<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PageStatus;
use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Stevebauman\Purify\Facades\Purify;

class PageController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $query = Page::query()
            ->with([
                'creator',
                'updater',
            ])
            ->latest('id');

        if ($request->filled('search')) {
            $search = $request
                ->string('search')
                ->toString();

            $query->where(function ($pageQuery) use ($search) {
                $pageQuery
                    ->where(
                        'title->en',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'title->fa',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'slug',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        if ($request->filled('status')) {
            $status = $request
                ->string('status')
                ->toString();

            if (PageStatus::tryFrom($status)) {
                $query->where(
                    'status',
                    $status
                );
            }
        }

        return JsonResource::collection(
            $query->paginate(
                $request->integer(
                    'per_page',
                    20
                )
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function show(
        Page $page
    ): JsonResource {
        $page->load([
            'creator',
            'updater',
        ]);

        return JsonResource::make(
            $this->transformPage($page)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'title' => [
                'required',
                'array',
            ],

            'title.en' => [
                'required',
                'string',
                'max:255',
            ],

            'title.fa' => [
                'required',
                'string',
                'max:255',
            ],

            'slug' => [
                'nullable',
                'string',
                'max:280',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'unique:pages,slug',
            ],

            'content' => [
                'required',
                'array',
            ],

            'content.en' => [
                'required',
                'string',
            ],

            'content.fa' => [
                'required',
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | SEO Metadata
            |--------------------------------------------------------------------------
            */
            'seo_title' => [
                'sometimes',
                'nullable',
                'array:fa,en',
            ],
            'seo_title.fa' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'seo_title.en' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'seo_description' => [
                'sometimes',
                'nullable',
                'array:fa,en',
            ],
            'seo_description.fa' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
            'seo_description.en' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
            'seo_image' => [
                'sometimes',
                'nullable',
                'string',
                'max:2048',
            ],

            'status' => [
                'required',
                Rule::enum(
                    PageStatus::class
                ),
            ],
        ]);

        $validated['content'] =
            $this->sanitizeContent(
                $validated['content']
            );

        $page = DB::transaction(
            function () use (
                $request,
                $validated
            ): Page {
                return Page::create([
                    'title' => $validated['title'],

                    'slug' => $validated['slug']
                        ?? $this->generateSlug(
                            $validated['title']
                        ),

                    'content' => $validated['content'],

                    'seo_title' =>
                        $validated['seo_title'] ?? null,

                    'seo_description' =>
                        $validated['seo_description'] ?? null,

                    'seo_image' =>
                        $validated['seo_image'] ?? null,

                    'status' => $validated['status'],

                    'created_by' =>
                        $request->user()->id,

                    'updated_by' =>
                        $request->user()->id,
                ]);
            }
        );

        $page->load([
            'creator',
            'updater',
        ]);

        return response()->json([
            'message' =>
                'Page created successfully.',

            'data' => $this->transformPage(
                $page
            ),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        Page $page
    ): JsonResource {
        $validated = $request->validate([
            'title' => [
                'sometimes',
                'array',
            ],

            'title.en' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'title.fa' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:280',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(
                    'pages',
                    'slug'
                )->ignore(
                    $page->id
                ),
            ],

            'content' => [
                'sometimes',
                'array',
            ],

            'content.en' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'content.fa' => [
                'sometimes',
                'nullable',
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | SEO Metadata
            |--------------------------------------------------------------------------
            */
            'seo_title' => [
                'sometimes',
                'nullable',
                'array:fa,en',
            ],
            'seo_title.fa' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'seo_title.en' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'seo_description' => [
                'sometimes',
                'nullable',
                'array:fa,en',
            ],
            'seo_description.fa' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
            'seo_description.en' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
            'seo_image' => [
                'sometimes',
                'nullable',
                'string',
                'max:2048',
            ],

            'status' => [
                'sometimes',
                Rule::enum(
                    PageStatus::class
                ),
            ],
        ]);

        DB::transaction(
            function () use (
                $request,
                $page,
                $validated
            ): void {
                $updateData = [];

                /*
                |--------------------------------------------------------------------------
                | Title translations
                |--------------------------------------------------------------------------
                */

                if (
                    array_key_exists(
                        'title',
                        $validated
                    )
                ) {
                    $currentTitle =
                        $this->decodeTranslations(
                            $page->getRawOriginal(
                                'title'
                            )
                        );

                    $updateData['title'] =
                        array_merge(
                            $currentTitle,
                            $validated['title']
                        );
                }

                /*
                |--------------------------------------------------------------------------
                | Content translations
                |--------------------------------------------------------------------------
                */

                if (
                    array_key_exists(
                        'content',
                        $validated
                    )
                ) {
                    $currentContent =
                        $this->decodeTranslations(
                            $page->getRawOriginal(
                                'content'
                            )
                        );

                    $mergedContent =
                        array_merge(
                            $currentContent,
                            $validated['content']
                        );

                    $updateData['content'] =
                        $this->sanitizeContent(
                            $mergedContent
                        );
                }

                /*
                |--------------------------------------------------------------------------
                | SEO translations
                |--------------------------------------------------------------------------
                */
                foreach (['seo_title', 'seo_description'] as $field) {
                    if (! array_key_exists($field, $validated)) {
                        continue;
                    }

                    if ($validated[$field] === null) {
                        $updateData[$field] = null;
                        continue;
                    }

                    $current = $this->decodeTranslations(
                        $page->getRawOriginal($field)
                    );

                    $updateData[$field] = array_merge(
                        $current,
                        $validated[$field]
                    );
                }

                if (array_key_exists('seo_image', $validated)) {
                    $updateData['seo_image'] = $validated['seo_image'];
                }

                /*
                |--------------------------------------------------------------------------
                | Language-independent fields
                |--------------------------------------------------------------------------
                */

                foreach (
                    [
                        'slug',
                        'status',
                    ] as $field
                ) {
                    if (
                        array_key_exists(
                            $field,
                            $validated
                        )
                    ) {
                        $updateData[$field] =
                            $validated[$field];
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Generate slug from English title
                |--------------------------------------------------------------------------
                */

                if (
                    array_key_exists(
                        'title',
                        $validated
                    )
                    && ! array_key_exists(
                        'slug',
                        $validated
                    )
                ) {
                    $title =
                        $updateData['title']
                        ?? $page->title;

                    $title = is_array($title)
                        ? $title
                        : $this->decodeTranslations(
                            $page->getRawOriginal(
                                'title'
                            )
                        );

                    $updateData['slug'] =
                        $this->generateSlug(
                            $title
                        );
                }

                $updateData['updated_by'] =
                    $request->user()->id;

                $page->update(
                    $updateData
                );
            }
        );

        return JsonResource::make([
            'message' =>
                'Page updated successfully.',

            'data' => $this->transformPage(
                $page->fresh([
                    'creator',
                    'updater',
                ])
            ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Page $page
    ): JsonResponse {
        $page->delete();

        return response()->json([
            'message' =>
                'Page deleted successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function transformPage(
        Page $page
    ): array {
        return [
            'id' => $page->id,

            'locale' =>
                app()->getLocale(),

            'direction' =>
                request()
                    ->attributes
                    ->get(
                        'direction',
                        'ltr'
                    ),

            'title' => $page->title,

            'slug' => $page->slug,

            /*
            |--------------------------------------------------------------------------
            | SEO Metadata
            |--------------------------------------------------------------------------
            */
            'seo_title' => $page->seo_title,

            'seo_description' => $page->seo_description,

            'seo_image' => $page->seo_image,

            'seo_title_translations' =>
                $this->decodeTranslations(
                    $page->getRawOriginal('seo_title')
                ),

            'seo_description_translations' =>
                $this->decodeTranslations(
                    $page->getRawOriginal('seo_description')
                ),

            'content' =>
                Purify::config('page')->clean(
                    (string) $page->content
                ),

            'status' =>
                $page->status?->value,

            'created_by' => $page->creator
                ? [
                    'id' =>
                        $page->creator->id,

                    'first_name' =>
                        $page->creator->first_name,

                    'last_name' =>
                        $page->creator->last_name,
                ]
                : null,

            'updated_by' => $page->updater
                ? [
                    'id' =>
                        $page->updater->id,

                    'first_name' =>
                        $page->updater->first_name,

                    'last_name' =>
                        $page->updater->last_name,
                ]
                : null,

            'created_at' =>
                $page->created_at,

            'updated_at' =>
                $page->updated_at,
        ];
    }

    protected function sanitizeContent(
        array $content
    ): array {
        $sanitized = [];

        foreach ($content as $locale => $value) {
            if ($value === null) {
                $sanitized[$locale] = null;

                continue;
            }

            $sanitized[$locale] =
                Purify::config('page')->clean(
                    (string) $value
                );
        }

        return $sanitized;
    }

    protected function decodeTranslations(
        mixed $value
    ): array {
        if (is_array($value)) {
            return $value;
        }

        if (
            ! is_string($value)
            || trim($value) === ''
        ) {
            return [];
        }

        $decoded = json_decode(
            $value,
            true
        );

        return is_array($decoded)
            ? $decoded
            : [];
    }

    protected function generateSlug(
        array $title
    ): string {
        $englishTitle =
            $title['en'] ?? null;

        if (
            ! is_string($englishTitle)
            || trim($englishTitle) === ''
        ) {
            return Str::lower(
                Str::random(12)
            );
        }

        return Str::slug(
            $englishTitle
        );
    }
}
