<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\HomepageSectionResource;
use App\Models\HomepageSection;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Stevebauman\Purify\Facades\Purify;

class HomepageSectionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Homepage Cache
    |--------------------------------------------------------------------------
    */

    private const HOMEPAGE_CACHE_KEYS = [
        'tkb:homepage:v2:en',
        'tkb:homepage:v2:fa',
    ];

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $sections = HomepageSection::query()
            ->with([
                'category',
                'product',
                'collection',
            ])
            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $search = $request
                        ->string('search')
                        ->toString();

                    $query->where(function ($searchQuery) use ($search) {
                        $searchQuery
                            ->where(
                                'section_key',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
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
                                'subtitle->en',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'subtitle->fa',
                                'like',
                                "%{$search}%"
                            );
                    });
                }
            )
            ->when(
                $request->filled('is_active'),
                function ($query) use ($request) {
                    $query->where(
                        'is_active',
                        $request->boolean('is_active')
                    );
                }
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(
                $request->integer(
                    'per_page',
                    20
                )
            );

        return HomepageSectionResource::collection(
            $sections
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
        $validated = $this->validateSection(
            $request
        );

        $section = HomepageSection::create([
            'section_key' =>
                $validated['section_key'],

            'title' =>
                $this->sanitizePlainTranslations(
                    $validated['title'] ?? null
                ),

            'subtitle' =>
                $this->sanitizePlainTranslations(
                    $validated['subtitle'] ?? null
                ),

            'content' =>
                $this->sanitizeHtmlTranslations(
                    $validated['content'] ?? null
                ),

            'image' =>
                $validated['image']
                ?? null,

            'mobile_image' =>
                $validated['mobile_image']
                ?? null,

            'link' =>
                $validated['link']
                ?? null,

            'button_text' =>
                $this->sanitizePlainTranslations(
                    $validated['button_text'] ?? null
                ),

            'category_id' =>
                $validated['category_id']
                ?? null,

            'product_id' =>
                $validated['product_id']
                ?? null,

            'collection_id' =>
                $validated['collection_id']
                ?? null,

            'is_active' =>
                $validated['is_active']
                ?? true,

            'sort_order' =>
                $validated['sort_order']
                ?? 0,
        ]);

        $section->load([
            'category',
            'product',
            'collection',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Homepage Cache Invalidation
        |--------------------------------------------------------------------------
        */

        $this->clearHomepageCache();

        return (new HomepageSectionResource(
            $section
        ))
            ->response()
            ->setStatusCode(201);
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function show(
        HomepageSection $homepageSection
    ): HomepageSectionResource {
        $homepageSection->load([
            'category',
            'product',
            'collection',
        ]);

        return new HomepageSectionResource(
            $homepageSection
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        HomepageSection $homepageSection
    ): HomepageSectionResource {
        $validated = $this->validateSection(
            $request,
            true,
            $homepageSection
        );

        $updateData = [];

        /*
        |--------------------------------------------------------------------------
        | Non-localized Fields
        |--------------------------------------------------------------------------
        */

        foreach (
            [
                'image',
                'mobile_image',
                'link',
                'category_id',
                'product_id',
                'collection_id',
                'is_active',
                'sort_order',
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
        | Localized Fields
        |--------------------------------------------------------------------------
        */

        foreach (
            [
                'title',
                'subtitle',
                'content',
                'button_text',
            ] as $field
        ) {
            if (
                ! array_key_exists(
                    $field,
                    $validated
                )
            ) {
                continue;
            }

            if (
                $validated[$field] === null
            ) {
                $updateData[$field] = null;

                continue;
            }

            $currentValue =
                $this->decodeTranslations(
                    $homepageSection->getRawOriginal(
                        $field
                    )
                );

            if (
                $field === 'content'
            ) {
                $sanitizedValue =
                    $this->sanitizeHtmlTranslations(
                        $validated[$field]
                    );
            } else {
                $sanitizedValue =
                    $this->sanitizePlainTranslations(
                        $validated[$field]
                    );
            }

            $updateData[$field] =
                array_merge(
                    $currentValue,
                    $sanitizedValue ?? []
                );
        }

        if ($updateData !== []) {
            $homepageSection->update(
                $updateData
            );

            /*
            |--------------------------------------------------------------------------
            | Homepage Cache Invalidation
            |--------------------------------------------------------------------------
            */

            $this->clearHomepageCache();
        }

        $homepageSection
            ->refresh()
            ->load([
                'category',
                'product',
                'collection',
            ]);

        return new HomepageSectionResource(
            $homepageSection
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function destroy(
        HomepageSection $homepageSection
    ): JsonResponse {
        $homepageSection->delete();

        /*
        |--------------------------------------------------------------------------
        | Homepage Cache Invalidation
        |--------------------------------------------------------------------------
        */

        $this->clearHomepageCache();

        return response()->json([
            'message' =>
                'Homepage section deleted successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Homepage Cache Helper
    |--------------------------------------------------------------------------
    */

    protected function clearHomepageCache(): void
    {
        $cache = Cache::store(config('cache.default'));

        foreach (
            self::HOMEPAGE_CACHE_KEYS as $cacheKey
        ) {
            $cache->forget($cacheKey);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    protected function validateSection(
        Request $request,
        bool $isUpdate = false,
        ?HomepageSection $homepageSection = null
    ): array {
        $rules = [

            /*
            |--------------------------------------------------------------------------
            | Fixed Section Key
            |--------------------------------------------------------------------------
            */

            'section_key' => [
                $isUpdate
                    ? 'prohibited'
                    : 'required',

                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/',

                $isUpdate
                    ? null
                    : 'unique:homepage_sections,section_key',
            ],

            /*
            |--------------------------------------------------------------------------
            | Title
            |--------------------------------------------------------------------------
            */

            'title' => [
                $isUpdate
                    ? 'sometimes'
                    : 'nullable',

                'nullable',
                'array',
            ],

            'title.en' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'title.fa' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            /*
            |--------------------------------------------------------------------------
            | Subtitle
            |--------------------------------------------------------------------------
            */

            'subtitle' => [
                $isUpdate
                    ? 'sometimes'
                    : 'nullable',

                'nullable',
                'array',
            ],

            'subtitle.en' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],

            'subtitle.fa' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],

            /*
            |--------------------------------------------------------------------------
            | Content
            |--------------------------------------------------------------------------
            */

            'content' => [
                $isUpdate
                    ? 'sometimes'
                    : 'nullable',

                'nullable',
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
            | Media
            |--------------------------------------------------------------------------
            */

            'image' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',

                function (
                    string $attribute,
                    mixed $value,
                    Closure $fail
                ): void {
                    if ($value === null) {
                        return;
                    }

                    $this->validateImageReference(
                        $attribute,
                        $value,
                        $fail
                    );
                },
            ],

            'mobile_image' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',

                function (
                    string $attribute,
                    mixed $value,
                    Closure $fail
                ): void {
                    if ($value === null) {
                        return;
                    }

                    $this->validateImageReference(
                        $attribute,
                        $value,
                        $fail
                    );
                },
            ],

            /*
            |--------------------------------------------------------------------------
            | Link
            |--------------------------------------------------------------------------
            */

            'link' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',

                function (
                    string $attribute,
                    mixed $value,
                    Closure $fail
                ): void {
                    if ($value === null) {
                        return;
                    }

                    $value = trim(
                        (string) $value
                    );

                    if ($value === '') {
                        return;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Control / Dangerous Characters
                    |--------------------------------------------------------------------------
                    */

                    if (
                        preg_match(
                            '/[\x00-\x1F\x7F\\\\<>"]/',
                            $value
                        )
                    ) {
                        $fail(
                            'The homepage section link contains invalid characters.'
                        );

                        return;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Protocol-relative URLs
                    |--------------------------------------------------------------------------
                    */

                    if (
                        str_starts_with(
                            $value,
                            '//'
                        )
                    ) {
                        $fail(
                            'Protocol-relative URLs are not allowed.'
                        );

                        return;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Internal Paths
                    |--------------------------------------------------------------------------
                    */

                    if (
                        str_starts_with(
                            $value,
                            '/'
                        )
                    ) {
                        return;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | External URLs
                    |--------------------------------------------------------------------------
                    */

                    $scheme = parse_url(
                        $value,
                        PHP_URL_SCHEME
                    );

                    if (
                        ! in_array(
                            strtolower(
                                (string) $scheme
                            ),
                            [
                                'http',
                                'https',
                            ],
                            true
                        )
                    ) {
                        $fail(
                            'The homepage section link must be an internal path or an HTTP(S) URL.'
                        );

                        return;
                    }

                    if (
                        filter_var(
                            $value,
                            FILTER_VALIDATE_URL
                        ) === false
                    ) {
                        $fail(
                            'The homepage section link must be a valid URL.'
                        );
                    }
                },
            ],

            /*
            |--------------------------------------------------------------------------
            | Button
            |--------------------------------------------------------------------------
            */

            'button_text' => [
                $isUpdate
                    ? 'sometimes'
                    : 'nullable',

                'nullable',
                'array',
            ],

            'button_text.en' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'button_text.fa' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            /*
            |--------------------------------------------------------------------------
            | Relations
            |--------------------------------------------------------------------------
            */

            'category_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:categories,id',
            ],

            'product_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:products,id',
            ],

            'collection_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:collections,id',
            ],

            /*
            |--------------------------------------------------------------------------
            | Management
            |--------------------------------------------------------------------------
            */

            'is_active' => [
                'sometimes',
                'boolean',
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],
        ];

        $rules = array_filter(
            $rules,
            fn ($rule) => $rule !== null
        );

        $validated = $request->validate(
            $rules
        );

        /*
        |--------------------------------------------------------------------------
        | Relation Validation
        |--------------------------------------------------------------------------
        */

        $relationFields = [
            'category_id',
            'product_id',
            'collection_id',
        ];

        $providedRelations = 0;

        foreach ($relationFields as $field) {
            if (
                array_key_exists(
                    $field,
                    $validated
                )
                && $validated[$field] !== null
            ) {
                $providedRelations++;
            }
        }

        if ($providedRelations > 1) {
            throw ValidationException::withMessages([
                'section' =>
                    'A homepage section may reference only one of category, product, or collection.',
            ]);
        }

        return $validated;
    }

    /*
    |--------------------------------------------------------------------------
    | Image Security
    |--------------------------------------------------------------------------
    */

    protected function validateImageReference(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        $value = trim(
            (string) $value
        );

        if ($value === '') {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Control / Dangerous Characters
        |--------------------------------------------------------------------------
        */

        if (
            preg_match(
                '/[\x00-\x1F\x7F\\\\<>"\']/',
                $value
            )
        ) {
            $fail(
                'The image contains invalid characters.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Path Traversal
        |--------------------------------------------------------------------------
        */

        if (
            str_contains(
                $value,
                '..'
            )
        ) {
            $fail(
                'The image contains an invalid path.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Protocol-relative URLs
        |--------------------------------------------------------------------------
        */

        if (
            str_starts_with(
                $value,
                '//'
            )
        ) {
            $fail(
                'Protocol-relative image URLs are not allowed.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Internal Paths
        |--------------------------------------------------------------------------
        */

        if (
            str_starts_with(
                $value,
                '/'
            )
        ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | External URLs
        |--------------------------------------------------------------------------
        */

        $scheme = parse_url(
            $value,
            PHP_URL_SCHEME
        );

        if (
            ! in_array(
                strtolower(
                    (string) $scheme
                ),
                [
                    'http',
                    'https',
                ],
                true
            )
        ) {
            $fail(
                'The image must be an internal path or an HTTP(S) URL.'
            );

            return;
        }

        if (
            filter_var(
                $value,
                FILTER_VALIDATE_URL
            ) === false
        ) {
            $fail(
                'The image must be a valid URL.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Plain Translation Sanitization
    |--------------------------------------------------------------------------
    */

    protected function sanitizePlainTranslations(
        mixed $value
    ): ?array {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            return null;
        }

        $sanitized = [];

        foreach (
            [
                'en',
                'fa',
            ] as $locale
        ) {
            if (
                ! array_key_exists(
                    $locale,
                    $value
                )
            ) {
                continue;
            }

            if (
                $value[$locale] === null
            ) {
                $sanitized[$locale] = null;

                continue;
            }

            $sanitized[$locale] =
                trim(
                    strip_tags(
                        Purify::config('banner')->clean(
                            (string) $value[$locale]
                        )
                    )
                );
        }

        return $sanitized;
    }

    /*
    |--------------------------------------------------------------------------
    | HTML Translation Sanitization
    |--------------------------------------------------------------------------
    */

    protected function sanitizeHtmlTranslations(
        mixed $value
    ): ?array {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            return null;
        }

        $sanitized = [];

        foreach (
            [
                'en',
                'fa',
            ] as $locale
        ) {
            if (
                ! array_key_exists(
                    $locale,
                    $value
                )
            ) {
                continue;
            }

            if (
                $value[$locale] === null
            ) {
                $sanitized[$locale] = null;

                continue;
            }

            $sanitized[$locale] =
                Purify::config('homepage')->clean(
                    (string) $value[$locale]
                );
        }

        return $sanitized;
    }

    /*
    |--------------------------------------------------------------------------
    | Translation Helpers
    |--------------------------------------------------------------------------
    */

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
}