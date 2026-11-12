<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use App\Services\ActivityLogService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreController extends Controller
{
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
        $stores = Store::query()
            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $search = $request
                        ->string('search')
                        ->toString();

                    $query->where(function ($searchQuery) use ($search) {
                        $searchQuery
                            ->where(
                                'name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'slug',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'city',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'address',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'name_en',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'city_en',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'address_en',
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
            ->orderBy(
                'sort_order'
            )
            ->orderBy(
                'id'
            )
            ->paginate(
                min(
                    max(
                        $request->integer(
                            'per_page',
                            20
                        ),
                        1
                    ),
                    100
                )
            );

        return StoreResource::collection(
            $stores
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function show(
        Store $store
    ): StoreResource {
        return new StoreResource(
            $store
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
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'name_en' => [
                'nullable',
                'string',
                'max:150',
            ],

            'slug' => [
                'nullable',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'unique:stores,slug',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'description_en' => [
                'nullable',
                'string',
            ],

            'address' => [
                'required',
                'string',
            ],

            'address_en' => [
                'nullable',
                'string',
            ],

            'city' => [
                'required',
                'string',
                'max:100',
            ],

            'city_en' => [
                'nullable',
                'string',
                'max:100',
            ],

            'floor_unit' => [
                'nullable',
                'string',
                'max:150',
            ],

            'floor_unit_en' => [
                'nullable',
                'string',
                'max:150',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'opening_hours' => [
                'nullable',
                'array',
            ],

            'opening_hours_en' => [
                'nullable',
                'array',
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
                'max:10000',
            ],
        ]);

        $store = Store::create([
            'name' =>
                $validated['name'],

            'name_en' =>
                $validated['name_en']
                ?? null,

            'slug' =>
                $validated['slug']
                ?? $this->generateSlug(
                    $validated['name_en']
                    ?? $validated['name']
                ),

            'description' =>
                $validated['description']
                ?? null,

            'description_en' =>
                $validated['description_en']
                ?? null,

            'address' =>
                $validated['address'],

            'address_en' =>
                $validated['address_en']
                ?? null,

            'city' =>
                $validated['city'],

            'city_en' =>
                $validated['city_en']
                ?? null,

            'floor_unit' =>
                $validated['floor_unit']
                ?? null,

            'floor_unit_en' =>
                $validated['floor_unit_en']
                ?? null,

            'phone' =>
                $validated['phone']
                ?? null,

            'email' =>
                $validated['email']
                ?? null,

            'latitude' =>
                $validated['latitude']
                ?? null,

            'longitude' =>
                $validated['longitude']
                ?? null,

            'opening_hours' =>
                $validated['opening_hours']
                ?? null,

            'opening_hours_en' =>
                $validated['opening_hours_en']
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

        $this->activityLogService->log(
            action: 'stores.created',
            description: 'Store created.',
            properties: [
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'slug' => $store->slug,
                ],
            ],
            subject: $store,
            request: $request
        );

        return (new StoreResource(
            $store
        ))
            ->response()
            ->setStatusCode(201);
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        Store $store
    ): StoreResource {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:150',
            ],

            'name_en' => [
                'sometimes',
                'nullable',
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
                    'stores',
                    'slug'
                )->ignore(
                    $store->id
                ),
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'description_en' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'address' => [
                'sometimes',
                'string',
            ],

            'address_en' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'city' => [
                'sometimes',
                'string',
                'max:100',
            ],

            'city_en' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'floor_unit' => [
                'sometimes',
                'nullable',
                'string',
                'max:150',
            ],

            'floor_unit_en' => [
                'sometimes',
                'nullable',
                'string',
                'max:150',
            ],

            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],

            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
            ],

            'latitude' => [
                'sometimes',
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'sometimes',
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'opening_hours' => [
                'sometimes',
                'nullable',
                'array',
            ],

            'opening_hours_en' => [
                'sometimes',
                'nullable',
                'array',
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
                'max:10000',
            ],
        ]);

        $updateData = [];

        foreach (
            [
                'name',
                'name_en',
                'slug',
                'description',
                'description_en',
                'address',
                'address_en',
                'city',
                'city_en',
                'floor_unit',
                'floor_unit_en',
                'phone',
                'email',
                'latitude',
                'longitude',
                'opening_hours',
                'opening_hours_en',
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

        if (
            (
                array_key_exists(
                    'name',
                    $validated
                )
                || array_key_exists(
                    'name_en',
                    $validated
                )
            )
            && ! array_key_exists(
                'slug',
                $validated
            )
        ) {
            $updateData['slug'] =
                $this->generateSlug(
                    $validated['name_en']
                    ?? $store->name_en
                    ?? $validated['name']
                    ?? $store->name
                );
        }

        if ($updateData !== []) {
            $store->update(
                $updateData
            );
        }

        $changes = $store->getChanges();

        $store->refresh();

        if ($changes !== []) {
            $this->activityLogService->log(
                action: 'stores.updated',
                description: 'Store updated.',
                properties: [
                    'changed_fields' => array_keys(
                        $changes
                    ),
                    'changes' => $changes,
                ],
                subject: $store,
                request: $request
            );
        }

        return new StoreResource(
            $store
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Store $store
    ): JsonResponse {
        $storeMetadata = [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
            ],
        ];

        $store->delete();

        $this->activityLogService->log(
            action: 'stores.deleted',
            description: 'Store deleted.',
            properties: $storeMetadata,
            subject: $store
        );

        return response()->json([
            'message' =>
                'Store deleted successfully.',

            'deleted' =>
                true,
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
        | Absolute URLs
        |--------------------------------------------------------------------------
        */

        $scheme = parse_url(
            $value,
            PHP_URL_SCHEME
        );

        if ($scheme !== null) {
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
                    'The image must use HTTP or HTTPS.'
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
        | Relative / Internal Paths
        |--------------------------------------------------------------------------
        */

        if (
            ! preg_match(
                '/^[A-Za-z0-9._~!$&()*+,;=:@%\/?-]+$/',
                $value
            )
        ) {
            $fail(
                'The image contains invalid path characters.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function generateSlug(
        string $name
    ): string {
        $slug = Str::slug(
            $name
        );

        if ($slug === '') {
            return Str::lower(
                Str::random(12)
            );
        }

        return $slug;
    }
}