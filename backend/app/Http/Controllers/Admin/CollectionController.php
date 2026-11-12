<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CollectionResource;
use App\Models\Collection;
use App\Services\ActivityLogService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CollectionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */

    private const INDEX_CACHE_VERSION_KEY =
        'tkb:collections:index:version';

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    public function __construct(
        protected ActivityLogService $activityLogService
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $collections = Collection::query()
            ->withCount('products')
            ->with([
                'products',
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
                                'name->en',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'name->fa',
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
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(
                $request->integer(
                    'per_page',
                    20
                )
            );

        return CollectionResource::adminCollection($collections);
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
            /*
            |--------------------------------------------------------------------------
            | Basic Information
            |--------------------------------------------------------------------------
            */

            'name' => [
                'required',
                'array',
            ],

            'name.en' => [
                'required',
                'string',
                'max:150',
            ],

            'name.fa' => [
                'required',
                'string',
                'max:150',
            ],

            'slug' => [
                'nullable',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'unique:collections,slug',
            ],

            'description' => [
                'nullable',
                'array',
            ],

            'description.en' => [
                'nullable',
                'string',
            ],

            'description.fa' => [
                'nullable',
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | Media / Management
            |--------------------------------------------------------------------------
            */

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

            'image' => [
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

            'is_active' => [
                'required',
                'boolean',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            /*
            |--------------------------------------------------------------------------
            | Products
            |--------------------------------------------------------------------------
            */

            'product_ids' => [
                'sometimes',
                'array',
            ],

            'product_ids.*' => [
                'integer',
                'distinct',
                'exists:products,id',
            ],
        ]);

        $collection = Collection::create([
            'name' => $validated['name'],

            'slug' => $validated['slug']
                ?? $this->generateSlug(
                    $validated['name']
                ),

            'description' =>
                $validated['description']
                ?? null,

            'seo_title' =>
                $validated['seo_title']
                ?? null,

            'seo_description' =>
                $validated['seo_description']
                ?? null,

            'seo_image' =>
                $validated['seo_image']
                ?? null,

            'image' =>
                $validated['image']
                ?? null,

            'is_active' =>
                $validated['is_active'],

            'sort_order' =>
                $validated['sort_order']
                ?? 0,
        ]);

        if (
            array_key_exists(
                'product_ids',
                $validated
            )
        ) {
            $collection
                ->products()
                ->sync(
                    $validated['product_ids']
                );
        }

        $collection->load([
            'products',
        ]);

        $collection->loadCount([
            'products',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Cache Invalidation
        |--------------------------------------------------------------------------
        |
        | The public collection index depends on:
        | - collection existence
        | - collection active state
        | - sort order
        | - collection name/slug
        | - product relationship / products_count
        |
        | A new collection therefore invalidates the current
        | public collection index cache version.
        |--------------------------------------------------------------------------
        */

        $this->bumpCollectionIndexCacheVersion();

        $this->activityLogService->log(
            action: 'collections.created',
            description: 'Collection created.',
            properties: [
                'collection' => [
                    'id' => $collection->id,
                    'slug' => $collection->slug,
                ],
                'product_ids' =>
                    $validated['product_ids']
                    ?? [],
            ],
            subject: $collection,
            request: $request
        );

        return (CollectionResource::admin($collection))
            ->response()
            ->setStatusCode(201);
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function show(
        Collection $collection
    ): CollectionResource {
        $collection->load([
            'products',
        ]);

        $collection->loadCount([
            'products',
        ]);

        return CollectionResource::admin($collection);
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        Collection $collection
    ): CollectionResource {
        $validated = $request->validate([
            /*
            |--------------------------------------------------------------------------
            | Basic Information
            |--------------------------------------------------------------------------
            */

            'name' => [
                'sometimes',
                'array',
            ],

            'name.en' => [
                'sometimes',
                'string',
                'max:150',
            ],

            'name.fa' => [
                'sometimes',
                'string',
                'max:150',
            ],

            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(
                    'collections',
                    'slug'
                )->ignore(
                    $collection->id
                ),
            ],

            'description' => [
                'sometimes',
                'nullable',
                'array',
            ],

            'description.en' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'description.fa' => [
                'sometimes',
                'nullable',
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | Media / Management
            |--------------------------------------------------------------------------
            */

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

            'is_active' => [
                'sometimes',
                'boolean',
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            /*
            |--------------------------------------------------------------------------
            | Products
            |--------------------------------------------------------------------------
            */

            'product_ids' => [
                'sometimes',
                'array',
            ],

            'product_ids.*' => [
                'integer',
                'distinct',
                'exists:products,id',
            ],
        ]);

        $updateData = [];

        /*
        |--------------------------------------------------------------------------
        | Locale-independent fields
        |--------------------------------------------------------------------------
        */

        foreach (
            [
                'slug',
                'image',
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
        | Name translations
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists(
                'name',
                $validated
            )
        ) {
            $currentName =
                $this->decodeTranslations(
                    $collection->getRawOriginal(
                        'name'
                    )
                );

            $updateData['name'] =
                array_merge(
                    $currentName,
                    $validated['name']
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Description translations
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists(
                'description',
                $validated
            )
        ) {
            if (
                $validated['description'] === null
            ) {
                $updateData['description'] =
                    null;
            } else {
                $currentDescription =
                    $this->decodeTranslations(
                        $collection->getRawOriginal(
                            'description'
                        )
                    );

                $updateData['description'] =
                    array_merge(
                        $currentDescription,
                        $validated['description']
                    );
            }
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
                $collection->getRawOriginal($field)
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
        | Generate slug from English name
        |--------------------------------------------------------------------------
        */

        if (
            array_key_exists(
                'name',
                $validated
            )
            && ! array_key_exists(
                'slug',
                $validated
            )
        ) {
            $updateData['slug'] =
                $this->generateSlug(
                    $updateData['name']
                );
        }

        if ($updateData !== []) {
            $collection->update(
                $updateData
            );
        }

        $changes = $collection->getChanges();

        $productsUpdated =
            array_key_exists(
                'product_ids',
                $validated
            );

        if ($productsUpdated) {
            $collection
                ->products()
                ->sync(
                    $validated['product_ids']
                );
        }

        $collection->load([
            'products',
        ]);

        $collection->loadCount([
            'products',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Cache Invalidation
        |--------------------------------------------------------------------------
        |
        | Invalidate when either:
        | - collection model data changed
        | - product relationship changed
        |
        | Product relationship affects products_count in public index.
        |--------------------------------------------------------------------------
        */

        if (
            $changes !== []
            || $productsUpdated
        ) {
            $this->bumpCollectionIndexCacheVersion();

            $this->activityLogService->log(
                action: 'collections.updated',
                description: 'Collection updated.',
                properties: [
                    'collection' => [
                        'id' => $collection->id,
                        'slug' => $collection->slug,
                    ],
                    'changed_fields' => array_keys(
                        $changes
                    ),
                    'changes' => $changes,
                    'products_updated' =>
                        $productsUpdated,
                    'product_ids' =>
                        $productsUpdated
                        ? $validated['product_ids']
                        : null,
                ],
                subject: $collection,
                request: $request
            );
        }

        return CollectionResource::admin($collection);
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Collection $collection
    ): JsonResponse {
        $collectionMetadata = [
            'collection' => [
                'id' => $collection->id,
                'slug' => $collection->slug,
            ],
            'product_count' => $collection
                ->products()
                ->count(),
        ];

        $collection
            ->products()
            ->detach();

        $collection->delete();

        /*
        |--------------------------------------------------------------------------
        | Cache Invalidation
        |--------------------------------------------------------------------------
        |
        | The deleted collection must disappear from the
        | public collection index.
        |--------------------------------------------------------------------------
        */

        $this->bumpCollectionIndexCacheVersion();

        $this->activityLogService->log(
            action: 'collections.deleted',
            description: 'Collection deleted.',
            properties: $collectionMetadata,
            subject: $collection
        );

        return response()->json([
            'message' =>
                'Collection deleted successfully.',
        ]);
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
        | Reject control characters and dangerous URL/path characters
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
        | Reject path traversal
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
        | Reject protocol-relative URLs
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
        | Absolute internal paths are allowed
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
        | HTTP(S) URLs are allowed
        |--------------------------------------------------------------------------
        */

        $scheme = parse_url(
            $value,
            PHP_URL_SCHEME
        );

        if (
            $scheme !== null
            && $scheme !== ''
        ) {
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

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Relative internal paths are allowed
        |--------------------------------------------------------------------------
        |
        | Examples:
        |   collections/luxury-cookware.webp
        |   banners/home.webp
        |   products/item-1.jpg
        |
        | These are valid application-internal media references.
        |--------------------------------------------------------------------------
        */

        if (
            preg_match(
                '#^[a-zA-Z0-9][a-zA-Z0-9._/-]*$#',
                $value
            ) !== 1
        ) {
            $fail(
                'The image must be an internal path or an HTTP(S) URL.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Cache Helpers
    |--------------------------------------------------------------------------
    */

    protected function bumpCollectionIndexCacheVersion(): void
    {
        $currentVersion = (int) Cache::store(config('cache.default'))->get(
            self::INDEX_CACHE_VERSION_KEY,
            1
        );

        Cache::store(config('cache.default'))->forever(
            self::INDEX_CACHE_VERSION_KEY,
            $currentVersion + 1
        );
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

    /*
    |--------------------------------------------------------------------------
    | Slug Helpers
    |--------------------------------------------------------------------------
    */

    protected function generateSlug(
        array $name
    ): string {
        $englishName =
            $name['en'] ?? null;

        if (
            ! is_string($englishName)
            || trim($englishName) === ''
        ) {
            return Str::lower(
                Str::random(12)
            );
        }

        return Str::slug(
            $englishName
        );
    }
}