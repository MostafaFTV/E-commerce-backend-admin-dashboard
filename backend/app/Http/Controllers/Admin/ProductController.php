<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    private const HOMEPAGE_CACHE_LOCALES = [
        'en',
        'fa',
    ];

    public function __construct(
        protected ActivityLogService $activityLogService
    ) {
    }

    public function index(Request $request)
    {
        abort_unless(
            auth()->user()->hasPermission('products.view'),
            403
        );

        $products = Product::query()
            ->with([
                'category',
                'collections',
                'images',
                'variants' => function ($query) {
                    $query->with([
                        'attributeValues.attribute',
                        'images',
                        'inventory',
                    ]);
                },
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
            ->when(
                $request->filled('category_id'),
                function ($query) use ($request) {
                    $query->where(
                        'category_id',
                        $request->integer('category_id')
                    );
                }
            )
            ->when(
                $request->filled('status'),
                function ($query) use ($request) {
                    $query->where(
                        'status',
                        $request->input('status')
                    );
                }
            )
            ->orderByDesc('created_at')
            ->paginate(
                $request->integer('per_page', 15)
            );

        return ProductResource::adminCollection($products);
    }

    public function store(Request $request)
    {
        abort_unless(
            auth()->user()->hasPermission('products.create'),
            403
        );

        $payload = $this->validateProduct(
            $request
        );

        $product = DB::transaction(
            function () use ($payload) {
                $product = Product::create([
                    'category_id' => $payload['category_id'],
                    'name' => $payload['name'],
                    'slug' => $payload['slug']
                        ?? $this->generateSlug(
                            $payload['name']
                        ),
                    'short_description' =>
                        $payload['short_description']
                        ?? null,
                    'description' =>
                        $payload['description']
                        ?? null,
                    'brand' =>
                        $payload['brand']
                        ?? null,
                    'seo_title' =>
                        $payload['seo_title']
                        ?? null,
                    'seo_description' =>
                        $payload['seo_description']
                        ?? null,
                    'seo_image' =>
                        $payload['seo_image']
                        ?? null,
                    'price' =>
                        $payload['price'],
                    'discount_percentage' =>
                        $payload['discount_percentage'] ?? null,
                    'status' =>
                        $payload['status']
                        ?? ProductStatus::DRAFT->value,
                    'is_featured' =>
                        $payload['is_featured']
                        ?? false,
                ]);

                if (
                    array_key_exists(
                        'collections',
                        $payload
                    )
                ) {
                    $this->syncCollections(
                        $product,
                        $payload['collections']
                    );
                }

                if (
                    array_key_exists(
                        'variants',
                        $payload
                    )
                ) {
                    $this->syncVariants(
                        $product,
                        $payload['variants']
                    );
                }

                return $product->fresh([
                    'category',
                    'collections',
                    'images',
                    'variants' => function ($query) {
                        $query->with([
                            'attributeValues.attribute',
                            'images',
                            'inventory',
                        ]);
                    },
                ]);
            }
        );

        $this->activityLogService->log(
            action: 'products.created',
            description: 'Product created.',
            properties: [
                'product' => [
                    'id' => $product->id,
                    'slug' => $product->slug,
                ],
                'collection_ids' => $payload['collections'] ?? [],
                'variants_count' => count(
                    $payload['variants'] ?? []
                ),
            ],
            subject: $product,
            request: $request
        );

        /*
        |--------------------------------------------------------------------------
        | Homepage Cache Invalidation
        |--------------------------------------------------------------------------
        |
        | A newly created product may be immediately assigned to a homepage
        | section by subsequent CMS operations. Keeping the homepage cache
        | invalidation here guarantees that product mutations never leave
        | a stale homepage payload behind.
        |
        */
        $this->forgetHomepageCache();

        return ProductResource::admin(
            $product
        )
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product)
    {
        abort_unless(
            auth()->user()->hasPermission('products.view'),
            403
        );

        $product->load([
            'category',
            'collections',
            'images',
            'variants' => function ($query) {
                $query->with([
                    'attributeValues.attribute',
                    'images',
                    'inventory',
                ]);
            },
        ]);

        return ProductResource::admin(
            $product
        );
    }

    public function update(
        Request $request,
        Product $product
    ) {
        abort_unless(
            auth()->user()->hasPermission('products.update'),
            403
        );

        $payload = $this->validateProduct(
            $request,
            $product
        );

        $collectionPayloadProvided =
            array_key_exists(
                'collections',
                $payload
            );

        $variantPayloadProvided =
            array_key_exists(
                'variants',
                $payload
            );

        $product = DB::transaction(
            function () use (
                $product,
                $payload
            ) {
                $updateData = [];

                foreach (
                    [
                        'category_id',
                        'brand',
                        'price',
                        'discount_percentage',
                        'status',
                        'is_featured',
                    ] as $field
                ) {
                    if (
                        array_key_exists(
                            $field,
                            $payload
                        )
                    ) {
                        $updateData[$field] =
                            $payload[$field];
                    }
                }

                if (
                    array_key_exists(
                        'name',
                        $payload
                    )
                ) {
                    $currentName =
                        $this->decodeTranslations(
                            $product->getRawOriginal(
                                'name'
                            )
                        );

                    $updateData['name'] =
                        array_merge(
                            $currentName,
                            $payload['name']
                        );
                }

                if (
                    array_key_exists(
                        'short_description',
                        $payload
                    )
                ) {
                    if (
                        $payload['short_description']
                        === null
                    ) {
                        $updateData[
                            'short_description'
                        ] = null;
                    } else {
                        $currentShortDescription =
                            $this->decodeTranslations(
                                $product->getRawOriginal(
                                    'short_description'
                                )
                            );

                        $updateData[
                            'short_description'
                        ] = array_merge(
                            $currentShortDescription,
                            $payload[
                                'short_description'
                            ]
                        );
                    }
                }

                if (
                    array_key_exists(
                        'description',
                        $payload
                    )
                ) {
                    if (
                        $payload['description']
                        === null
                    ) {
                        $updateData[
                            'description'
                        ] = null;
                    } else {
                        $currentDescription =
                            $this->decodeTranslations(
                                $product->getRawOriginal(
                                    'description'
                                )
                            );

                        $updateData[
                            'description'
                        ] = array_merge(
                            $currentDescription,
                            $payload[
                                'description'
                            ]
                        );
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | SEO translations
                |--------------------------------------------------------------------------
                */
                foreach (['seo_title', 'seo_description'] as $field) {
                    if (! array_key_exists($field, $payload)) {
                        continue;
                    }

                    if ($payload[$field] === null) {
                        $updateData[$field] = null;
                        continue;
                    }

                    $current = $this->decodeTranslations(
                        $product->getRawOriginal($field)
                    );

                    $updateData[$field] = array_merge(
                        $current,
                        $payload[$field]
                    );
                }

                if (array_key_exists('seo_image', $payload)) {
                    $updateData['seo_image'] = $payload['seo_image'];
                }

                if (
                    array_key_exists(
                        'slug',
                        $payload
                    )
                ) {
                    $updateData['slug'] =
                        $payload['slug'];
                } elseif (
                    array_key_exists(
                        'name',
                        $payload
                    )
                ) {
                    $updateData['slug'] =
                        $this->generateSlug(
                            $updateData['name']
                        );
                }

                if ($updateData !== []) {
                    $product->update(
                        $updateData
                    );
                }

                if (
                    array_key_exists(
                        'collections',
                        $payload
                    )
                ) {
                    $this->syncCollections(
                        $product,
                        $payload['collections']
                    );
                }

                if (
                    array_key_exists(
                        'variants',
                        $payload
                    )
                ) {
                    $this->syncVariants(
                        $product,
                        $payload['variants']
                    );
                }

                return $product->fresh([
                    'category',
                    'collections',
                    'images',
                    'variants' => function ($query) {
                        $query->with([
                            'product',
                            'attributeValues.attribute',
                            'images',
                            'inventory',
                        ]);
                    },
                ]);
            }
        );

        $this->activityLogService->log(
            action: 'products.updated',
            description: 'Product updated.',
            properties: [
                'product' => [
                    'id' => $product->id,
                    'slug' => $product->slug,
                ],
                'product_fields_provided' => array_keys(
                    array_intersect_key(
                        $payload,
                        array_flip([
                            'category_id',
                            'name',
                            'slug',
                            'short_description',
                            'description',
                            'brand',
                            'price',
                            'status',
                            'is_featured',
                        ])
                    )
                ),
                'collections_updated' =>
                    $collectionPayloadProvided,
                'variants_updated' =>
                    $variantPayloadProvided,
                'variants_count' =>
                    count($payload['variants'] ?? []),
            ],
            subject: $product,
            request: $request
        );

        /*
        |--------------------------------------------------------------------------
        | Homepage Cache Invalidation
        |--------------------------------------------------------------------------
        */
        $this->forgetHomepageCache();

        return ProductResource::admin(
            $product
        );
    }

    public function destroy(Product $product)
    {
        abort_unless(
            auth()->user()->hasPermission('products.delete'),
            403
        );

        $productMetadata = [
            'product' => [
                'id' => $product->id,
                'slug' => $product->slug,
            ],
            'variants_count' => $product
                ->variants()
                ->count(),
        ];

        DB::transaction(
            function () use ($product) {
                $product->variants()->delete();

                $product->delete();
            }
        );

        $this->activityLogService->log(
            action: 'products.deleted',
            description: 'Product deleted.',
            properties: $productMetadata,
            subject: $product
        );

        /*
        |--------------------------------------------------------------------------
        | Homepage Cache Invalidation
        |--------------------------------------------------------------------------
        */
        $this->forgetHomepageCache();

        return response()->noContent();
    }

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */

    protected function forgetHomepageCache(): void
    {
        $cache = Cache::store(config('cache.default'));

        foreach (
            self::HOMEPAGE_CACHE_LOCALES as $locale
        ) {
            $cache->forget(
                "tkb:homepage:v2:{$locale}"
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    protected function validateProduct(
        Request $request,
        ?Product $product = null
    ): array {
        $isUpdate = $product !== null;

        $rules = [
            'category_id' => [
                $isUpdate
                    ? 'sometimes'
                    : 'required',
                'integer',
                'exists:categories,id',
            ],

            'name' => [
                $isUpdate
                    ? 'sometimes'
                    : 'required',
                'array',
            ],

            'name.en' => [
                $isUpdate
                    ? 'sometimes'
                    : 'required',
                'string',
                'max:255',
            ],

            'name.fa' => [
                $isUpdate
                    ? 'sometimes'
                    : 'required',
                'string',
                'max:255',
            ],

            'slug' => [
                $isUpdate
                    ? 'sometimes'
                    : 'nullable',
                'nullable',
                'string',
                'max:280',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(
                    'products',
                    'slug'
                )->ignore(
                    $product?->id
                ),
            ],

            'short_description' => [
                $isUpdate
                    ? 'sometimes'
                    : 'nullable',
                'nullable',
                'array',
            ],

            'short_description.en' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'short_description.fa' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'description' => [
                $isUpdate
                    ? 'sometimes'
                    : 'nullable',
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

            'brand' => [
                'sometimes',
                'nullable',
                'string',
                'max:150',
            ],

            'price' => [
                $isUpdate
                    ? 'sometimes'
                    : 'required',
                'numeric',
                'min:0',
            ],

            'discount_percentage' => [
                'sometimes',
                'nullable',
                'integer',
                'between:1,100',
            ],

            'status' => [
                'sometimes',
                'nullable',
                'in:' .
                    implode(
                        ',',
                        array_map(
                            fn (
                                ProductStatus $status
                            ) => $status->value,
                            ProductStatus::cases()
                        )
                    ),
            ],

            'is_featured' => [
                'sometimes',
                'boolean',
            ],

            'collections' => [
                'sometimes',
                'array',
            ],

            'collections.*' => [
                'integer',
                'exists:collections,id',
            ],

            'variants' => [
                'sometimes',
                'array',
            ],

            'variants.*.id' => [
                'nullable',
                'integer',

                Rule::exists(
                    'product_variants',
                    'id'
                )->when(
                    $product,
                    fn ($rule) =>
                        $rule->where(
                            'product_id',
                            $product->id
                        )
                ),
            ],

            'variants.*.sku' => [
                'required',
                'string',
                'max:100',

                function (
                    $attribute,
                    $value,
                    $fail
                ) use ($request) {
                    $variantIndex = (int) str_replace(
                        [
                            'variants.',
                            '.sku',
                        ],
                        '',
                        $attribute
                    );

                    $variantId = $request->input(
                        "variants.{$variantIndex}.id"
                    );

                    $exists =
                        ProductVariant::query()
                            ->where(
                                'sku',
                                $value
                            )
                            ->when(
                                $variantId,
                                fn ($query) =>
                                    $query->where(
                                        'id',
                                        '!=',
                                        $variantId
                                    )
                            )
                            ->exists();

                    if ($exists) {
                        $fail(
                            'The variant SKU has already been taken.'
                        );
                    }
                },
            ],

            'variants.*.price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'variants.*.status' => [
                'nullable',
                'in:' .
                    implode(
                        ',',
                        array_map(
                            fn (
                                VariantStatus $status
                            ) => $status->value,
                            VariantStatus::cases()
                        )
                    ),
            ],

            'variants.*.sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'variants.*.attribute_values' => [
                'nullable',
                'array',
            ],

            'variants.*.attribute_values.*' => [
                'integer',
                'exists:attribute_values,id',
            ],

            'variants.*.inventory' => [
                'nullable',
                'array',
            ],

            'variants.*.inventory.quantity' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'variants.*.inventory.reserved_quantity' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'variants.*.inventory.low_stock_threshold' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ];

        $validated = $request->validate(
            $rules
        );

        foreach (
            $validated['variants'] ?? []
            as $index => $variantData
        ) {
            if (
                ! array_key_exists(
                    'inventory',
                    $variantData
                )
            ) {
                continue;
            }

            $existingInventory = null;

            if (
                $product
                && isset($variantData['id'])
            ) {
                $existingInventory =
                    $product
                        ->variants()
                        ->whereKey(
                            $variantData['id']
                        )
                        ->first()
                        ?->inventory;
            }

            $quantity =
                $variantData[
                    'inventory'
                ]['quantity']
                ?? $existingInventory?->quantity
                ?? 0;

            $reservedQuantity =
                $variantData[
                    'inventory'
                ]['reserved_quantity']
                ?? $existingInventory?->reserved_quantity
                ?? 0;

            if (
                (int) $reservedQuantity
                > (int) $quantity
            ) {
                throw ValidationException::withMessages([
                    "variants.{$index}.inventory.reserved_quantity" =>
                        'The reserved quantity may not exceed the quantity.',
                ]);
            }
        }

        foreach (
            $validated['variants'] ?? []
            as $index => $variantData
        ) {
            $values = $variantData['attribute_values'] ?? [];
            $seenValues = [];

            foreach ($values as $valueIndex => $valueId) {
                if (in_array($valueId, $seenValues, true)) {
                    throw ValidationException::withMessages([
                        "variants.{$index}.attribute_values.{$valueIndex}" =>
                            'The attribute values must be distinct within each variant.',
                    ]);
                }

                $seenValues[] = $valueId;
            }

            $attributeIds =
                AttributeValue::query()
                    ->whereIn(
                        'id',
                        $variantData[
                            'attribute_values'
                        ] ?? []
                    )
                    ->pluck('attribute_id');

            if (
                $attributeIds
                    ->duplicates()
                    ->isNotEmpty()
            ) {
                throw ValidationException::withMessages([
                    "variants.{$index}.attribute_values" =>
                        'A variant may only have one value for each attribute.',
                ]);
            }
        }

        return $validated;
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

    /*
    |--------------------------------------------------------------------------
    | Collections
    |--------------------------------------------------------------------------
    */

    protected function syncCollections(
        Product $product,
        array $collectionIds
    ): void {
        $product->collections()->sync(
            $collectionIds
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Variants
    |--------------------------------------------------------------------------
    */

    protected function syncVariants(
        Product $product,
        array $variants
    ): void {
        foreach (
            $variants as $variantData
        ) {
            $variantId =
                $variantData['id']
                ?? null;

            $variant = $variantId
                ? $product
                    ->variants()
                    ->whereKey($variantId)
                    ->first()
                : null;

            if (! $variant) {
                $variant =
                    $product
                        ->variants()
                        ->create([
                            'sku' =>
                                $variantData['sku'],

                            'price' =>
                                $variantData['price']
                                ?? null,

                            'status' =>
                                $variantData['status']
                                ?? VariantStatus::ACTIVE->value,

                            'sort_order' =>
                                $variantData['sort_order']
                                ?? 0,
                        ]);
            } else {
                $variant->update([
                    'sku' =>
                        $variantData['sku'],

                    'price' =>
                        array_key_exists(
                            'price',
                            $variantData
                        )
                            ? $variantData['price']
                            : $variant->price,

                    'status' =>
                        $variantData['status']
                        ?? $variant->status->value,

                    'sort_order' =>
                        $variantData['sort_order']
                        ?? $variant->sort_order,
                ]);
            }

            if (
                array_key_exists(
                    'attribute_values',
                    $variantData
                )
            ) {
                $variant
                    ->attributeValues()
                    ->sync(
                        $variantData[
                            'attribute_values'
                        ] ?? []
                    );
            }

            if (
                array_key_exists(
                    'inventory',
                    $variantData
                )
            ) {
                $this->syncVariantInventory(
                    $variant,
                    $variantData['inventory']
                );
            }
        }
    }

    protected function syncVariantInventory(
        ProductVariant $variant,
        array $inventoryData
    ): void {
        $variant->loadMissing(
            'inventory'
        );

        $inventoryPayload = [
            'quantity' => (int) (
                $inventoryData['quantity']
                ?? $variant->inventory?->quantity
                ?? 0
            ),

            'reserved_quantity' => (int) (
                $inventoryData[
                    'reserved_quantity'
                ]
                ?? $variant->inventory?->reserved_quantity
                ?? 0
            ),

            'low_stock_threshold' => (int) (
                $inventoryData[
                    'low_stock_threshold'
                ]
                ?? $variant->inventory?->low_stock_threshold
                ?? 5
            ),
        ];

        if (
            $inventoryPayload[
                'reserved_quantity'
            ]
            > $inventoryPayload['quantity']
        ) {
            throw ValidationException::withMessages([
                'variants' =>
                    'Reserved quantity may not exceed quantity.',
            ]);
        }

        if ($variant->inventory) {
            $variant
                ->inventory()
                ->update(
                    $inventoryPayload
                );
        } else {
            $variant
                ->inventory()
                ->create(
                    $inventoryPayload
                );
        }
    }
}