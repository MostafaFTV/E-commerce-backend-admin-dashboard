<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Stevebauman\Purify\Facades\Purify;

class BannerController extends Controller
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
        $banners = Banner::query()
            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $search = $request
                        ->string('search')
                        ->toString();

                    $query->where(function ($searchQuery) use ($search) {
                        $searchQuery
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

        return BannerResource::collection(
            $banners
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
        $validated = $this->validateBanner(
            $request
        );

        $banner = Banner::create([
            'title' =>
                $this->sanitizeTranslations(
                    $validated['title'] ?? null
                ),

            'subtitle' =>
                $this->sanitizeTranslations(
                    $validated['subtitle'] ?? null
                ),

            'image' =>
                $validated['image'],

            'mobile_image' =>
                $validated['mobile_image']
                ?? null,

            'link' =>
                $validated['link']
                ?? null,

            'button_text' =>
                $this->sanitizeTranslations(
                    $validated['button_text']
                    ?? null
                ),

            'sort_order' =>
                $validated['sort_order']
                ?? 0,

            'is_active' =>
                $validated['is_active'],

            'starts_at' =>
                $validated['starts_at']
                ?? null,

            'ends_at' =>
                $validated['ends_at']
                ?? null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Homepage Cache Invalidation
        |--------------------------------------------------------------------------
        */

        $this->clearHomepageCache();

        return (new BannerResource(
            $banner
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
        Banner $banner
    ): BannerResource {
        return new BannerResource(
            $banner
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        Banner $banner
    ): BannerResource {
        $validated = $this->validateBanner(
            $request,
            true
        );

        $updateData = [];

        foreach (
            [
                'image',
                'mobile_image',
                'link',
                'sort_order',
                'is_active',
                'starts_at',
                'ends_at',
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

        foreach (
            [
                'title',
                'subtitle',
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
                    $banner->getRawOriginal(
                        $field
                    )
                );

            $sanitizedValue =
                $this->sanitizeTranslations(
                    $validated[$field]
                );

            $updateData[$field] =
                array_merge(
                    $currentValue,
                    $sanitizedValue ?? []
                );
        }

        if ($updateData !== []) {
            $banner->update(
                $updateData
            );

            /*
            |--------------------------------------------------------------------------
            | Homepage Cache Invalidation
            |--------------------------------------------------------------------------
            */

            $this->clearHomepageCache();
        }

        $banner->refresh();

        return new BannerResource(
            $banner
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Banner $banner
    ): JsonResponse {
        $banner->delete();

        /*
        |--------------------------------------------------------------------------
        | Homepage Cache Invalidation
        |--------------------------------------------------------------------------
        */

        $this->clearHomepageCache();

        return response()->json([
            'message' =>
                'Banner deleted successfully.',
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

    protected function validateBanner(
        Request $request,
        bool $isUpdate = false
    ): array {
        $validated = $request->validate([

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
            | Image
            |--------------------------------------------------------------------------
            */

            'image' => [
                $isUpdate
                    ? 'sometimes'
                    : 'required',

                'string',
                'max:500',

                function (
                    string $attribute,
                    mixed $value,
                    Closure $fail
                ): void {
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

                    if (
                        preg_match(
                            '/[\x00-\x1F\x7F\\\\<>"]/',
                            $value
                        )
                    ) {
                        $fail(
                            'The banner link contains invalid characters.'
                        );

                        return;
                    }

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

                    if (
                        str_starts_with(
                            $value,
                            '/'
                        )
                    ) {
                        return;
                    }

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
                            'The banner link must be an internal path or an HTTP(S) URL.'
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
                            'The banner link must be a valid URL.'
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
                'sometimes',
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
            | Management
            |--------------------------------------------------------------------------
            */

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'is_active' => [
                $isUpdate
                    ? 'sometimes'
                    : 'required',

                'boolean',
            ],

            /*
            |--------------------------------------------------------------------------
            | Scheduling
            |--------------------------------------------------------------------------
            */

            'starts_at' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'ends_at' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:starts_at',
            ],
        ]);

        if (
            isset($validated['starts_at'])
            && isset($validated['ends_at'])
            && $validated['starts_at']
            && $validated['ends_at']
        ) {
            if (
                strtotime(
                    $validated['ends_at']
                )
                <
                strtotime(
                    $validated['starts_at']
                )
            ) {
                throw ValidationException::withMessages([
                    'ends_at' =>
                        'The end date must be after or equal to the start date.',
                ]);
            }
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
            $fail(
                "The {$attribute} field may not be empty."
            );

            return;
        }

        if (
            preg_match(
                '/[\x00-\x1F\x7F\\\\<>"\']/',
                $value
            )
        ) {
            $fail(
                "The {$attribute} field contains invalid characters."
            );

            return;
        }

        if (
            str_contains(
                $value,
                '..'
            )
        ) {
            $fail(
                "The {$attribute} field contains an invalid path."
            );

            return;
        }

        if (
            str_starts_with(
                $value,
                '//'
            )
        ) {
            $fail(
                "The {$attribute} field may not use a protocol-relative URL."
            );

            return;
        }

        if (
            str_starts_with(
                $value,
                '/'
            )
        ) {
            return;
        }

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
                "The {$attribute} field must be an internal path or an HTTP(S) URL."
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
                "The {$attribute} field must be a valid URL."
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Translation Helpers
    |--------------------------------------------------------------------------
    */

    protected function sanitizeTranslations(
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