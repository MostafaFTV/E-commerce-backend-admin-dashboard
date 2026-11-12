<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\ActivityLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductImageController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {
    }

    public function index(
        Product $product
    ): JsonResponse {
        abort_unless(
            auth()->user()->hasPermission('products.view'),
            403
        );

        $images = ProductImage::query()
            ->where(
                'product_id',
                $product->id
            )
            ->with([
                'variant.attributeValues.attribute',
                'attributeValue.attribute',
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $images
                ->map(
                    fn (ProductImage $image) =>
                        $this->serializeImage($image)
                )
                ->values(),
        ]);
    }

    public function store(
        Request $request,
        Product $product
    ): JsonResponse {
        abort_unless(
            auth()->user()->hasPermission('products.update'),
            403
        );

        $validated = $request->validate([
            'images' => [
                'required',
                'array',
                'min:1',
                'max:20',
            ],

            'images.*' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],

            'product_variant_id' => [
                'nullable',
                'integer',
                'exists:product_variants,id',
            ],

            'attribute_value_id' => [
                'nullable',
                'integer',
                'exists:attribute_values,id',
            ],
        ]);

        $variantId =
            $validated['product_variant_id']
            ?? null;

        $attributeValueId =
            $validated['attribute_value_id']
            ?? null;

        $this->assertAssociations(
            $product,
            $variantId,
            $attributeValueId
        );

        /*
         * sort_order is scoped.
         *
         * General images, each color gallery and each variant gallery
         * keep their own independent ordering.
         */
        $nextSortOrder =
            (int) (
                $this->scopeQuery(
                    $product,
                    $variantId,
                    $attributeValueId
                )->max('sort_order')
                ?? -1
            ) + 1;

        $storedPaths = [];

        try {
            $createdImages =
                DB::transaction(
                    function () use (
                        $validated,
                        $product,
                        $variantId,
                        $attributeValueId,
                        $nextSortOrder,
                        &$storedPaths
                    ) {
                        $created = collect();

                        foreach (
                            $validated['images']
                            as $index => $file
                        ) {
                            $storedPath =
                                $file->store(
                                    'products/' .
                                    $product->slug,
                                    'public'
                                );

                            $storedPaths[] =
                                $storedPath;

                            $image =
                                ProductImage::create([
                                    'product_id' =>
                                        $product->id,

                                    'product_variant_id' =>
                                        $variantId,

                                    'attribute_value_id' =>
                                        $attributeValueId,

                                    'image_path' =>
                                        '/storage/' .
                                        $storedPath,

                                    'alt_text' =>
                                        null,

                                    'sort_order' =>
                                        $nextSortOrder +
                                        $index,

                                    'is_primary' =>
                                        false,
                                ]);

                            $created->push(
                                $image
                            );
                        }

                        $first =
                            $created->first();

                        if (
                            $first !== null
                            && ! $this->scopeHasPrimary(
                                $product,
                                $variantId,
                                $attributeValueId
                            )
                        ) {
                            $first->update([
                                'is_primary' => true,
                            ]);
                        }

                        return $created;
                    }
                );
        } catch (\Throwable $exception) {
            foreach (
                $storedPaths as $storedPath
            ) {
                Storage::disk('public')
                    ->delete($storedPath);
            }

            throw $exception;
        }

        $ids =
            $createdImages
                ->pluck('id');

        $images =
            ProductImage::query()
                ->whereIn(
                    'id',
                    $ids
                )
                ->with([
                    'variant.attributeValues.attribute',
                    'attributeValue.attribute',
                ])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

        $this->activityLogService->log(
            action: 'product_images.created',
            description: 'Product images uploaded.',
            properties: [
                'product_id' =>
                    $product->id,

                'image_ids' =>
                    $ids->values()->all(),

                'product_variant_id' =>
                    $variantId,

                'attribute_value_id' =>
                    $attributeValueId,
            ],
            subject: $product,
            request: $request
        );

        return response()->json([
            'data' => $images
                ->map(
                    fn (ProductImage $image) =>
                        $this->serializeImage($image)
                )
                ->values(),
        ], 201);
    }

    public function update(
        Request $request,
        Product $product,
        ProductImage $image
    ): JsonResponse {
        abort_unless(
            auth()->user()->hasPermission('products.update'),
            403
        );

        $this->assertImageBelongsToProduct(
            $product,
            $image
        );

        $validated = $request->validate([
            'alt_text' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'is_primary' => [
                'sometimes',
                'boolean',
            ],

            'product_variant_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:product_variants,id',
            ],

            'attribute_value_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:attribute_values,id',
            ],
        ]);

        $oldVariantId =
            $image->product_variant_id;

        $oldAttributeValueId =
            $image->attribute_value_id;

        $wasPrimary =
            (bool) $image->is_primary;

        $variantId =
            array_key_exists(
                'product_variant_id',
                $validated
            )
                ? $validated['product_variant_id']
                : $oldVariantId;

        $attributeValueId =
            array_key_exists(
                'attribute_value_id',
                $validated
            )
                ? $validated['attribute_value_id']
                : $oldAttributeValueId;

        $this->assertAssociations(
            $product,
            $variantId,
            $attributeValueId
        );

        $scopeChanged =
            $oldVariantId !== $variantId
            || $oldAttributeValueId !== $attributeValueId;

        $willBePrimary =
            array_key_exists(
                'is_primary',
                $validated
            )
                ? (bool) $validated['is_primary']
                : $wasPrimary;

        DB::transaction(
            function () use (
                $validated,
                $product,
                $image,
                $oldVariantId,
                $oldAttributeValueId,
                $wasPrimary,
                $variantId,
                $attributeValueId,
                $scopeChanged,
                $willBePrimary
            ): void {
                if ($willBePrimary) {
                    $this->clearPrimaryScope(
                        $product,
                        $variantId,
                        $attributeValueId,
                        $image->id
                    );
                }

                $updateData = [];

                foreach (
                    [
                        'alt_text',
                        'sort_order',
                        'is_primary',
                        'product_variant_id',
                        'attribute_value_id',
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

                if ($updateData !== []) {
                    $image->update(
                        $updateData
                    );
                }

                /*
                 * If a primary image moved away from its old scope,
                 * promote the next available image there.
                 */
                if (
                    $scopeChanged
                    && $wasPrimary
                ) {
                    $this->ensurePrimaryScope(
                        $product,
                        $oldVariantId,
                        $oldAttributeValueId
                    );
                }

                /*
                 * If this image is not primary anymore, make sure an
                 * existing sibling can become primary when possible.
                 */
                if (! $willBePrimary) {
                    $this->ensurePrimaryScope(
                        $product,
                        $variantId,
                        $attributeValueId,
                        $image->id
                    );
                }
            }
        );

        $image
            ->refresh()
            ->load([
                'variant.attributeValues.attribute',
                'attributeValue.attribute',
            ]);

        $this->activityLogService->log(
            action: 'product_images.updated',
            description: 'Product image updated.',
            properties: [
                'product_id' =>
                    $product->id,

                'image_id' =>
                    $image->id,

                'changed_fields' =>
                    array_keys($validated),
            ],
            subject: $product,
            request: $request
        );

        return response()->json([
            'data' =>
                $this->serializeImage(
                    $image
                ),
        ]);
    }

    public function reorder(
        Request $request,
        Product $product
    ): JsonResponse {
        abort_unless(
            auth()->user()->hasPermission('products.update'),
            403
        );

        $validated = $request->validate([
            'image_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'image_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:product_images,id',
            ],

            'product_variant_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:product_variants,id',
            ],

            'attribute_value_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:attribute_values,id',
            ],
        ]);

        $variantId =
            $validated['product_variant_id']
            ?? null;

        $attributeValueId =
            $validated['attribute_value_id']
            ?? null;

        $this->assertAssociations(
            $product,
            $variantId,
            $attributeValueId
        );

        $imageIds =
            collect(
                $validated['image_ids']
            )
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->values();

        /*
         * A reorder request must contain the complete exact scope.
         *
         * This prevents duplicated sort_order values caused by
         * reordering only a random subset of the gallery.
         */
        $scopeIds =
            $this->scopeQuery(
                $product,
                $variantId,
                $attributeValueId
            )
                ->pluck('id')
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->values();

        $received =
            $imageIds->all();

        $expected =
            $scopeIds->all();

        sort($received);
        sort($expected);

        if ($received !== $expected) {
            throw ValidationException::withMessages([
                'image_ids' =>
                    'Image IDs must contain every image in the selected gallery exactly once.',
            ]);
        }

        DB::transaction(
            function () use (
                $imageIds
            ): void {
                foreach (
                    $imageIds
                    as $sortOrder => $imageId
                ) {
                    ProductImage::query()
                        ->whereKey($imageId)
                        ->update([
                            'sort_order' =>
                                $sortOrder,
                        ]);
                }
            }
        );

        $images =
            $this->scopeQuery(
                $product,
                $variantId,
                $attributeValueId
            )
                ->with([
                    'variant.attributeValues.attribute',
                    'attributeValue.attribute',
                ])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

        $this->activityLogService->log(
            action: 'product_images.reordered',
            description: 'Product images reordered.',
            properties: [
                'product_id' =>
                    $product->id,

                'product_variant_id' =>
                    $variantId,

                'attribute_value_id' =>
                    $attributeValueId,

                'image_ids' =>
                    $imageIds->all(),
            ],
            subject: $product,
            request: $request
        );

        return response()->json([
            'data' => $images
                ->map(
                    fn (ProductImage $image) =>
                        $this->serializeImage($image)
                )
                ->values(),
        ]);
    }

    public function destroy(
        Request $request,
        Product $product,
        ProductImage $image
    ): JsonResponse {
        abort_unless(
            auth()->user()->hasPermission('products.update'),
            403
        );

        $this->assertImageBelongsToProduct(
            $product,
            $image
        );

        $imageId =
            $image->id;

        $imagePath =
            $image->image_path;

        $variantId =
            $image->product_variant_id;

        $attributeValueId =
            $image->attribute_value_id;

        $wasPrimary =
            (bool) $image->is_primary;

        DB::transaction(
            function () use (
                $image,
                $product,
                $variantId,
                $attributeValueId,
                $wasPrimary
            ): void {
                $image->delete();

                if ($wasPrimary) {
                    $this->ensurePrimaryScope(
                        $product,
                        $variantId,
                        $attributeValueId
                    );
                }
            }
        );

        $pathStillUsed =
            ProductImage::query()
                ->where(
                    'image_path',
                    $imagePath
                )
                ->exists();

        if (
            ! $pathStillUsed
            && Str::startsWith(
                $imagePath,
                '/storage/products/'
            )
        ) {
            Storage::disk('public')
                ->delete(
                    Str::after(
                        $imagePath,
                        '/storage/'
                    )
                );
        }

        $this->activityLogService->log(
            action: 'product_images.deleted',
            description: 'Product image deleted.',
            properties: [
                'product_id' =>
                    $product->id,

                'image_id' =>
                    $imageId,

                'image_path' =>
                    $imagePath,
            ],
            subject: $product,
            request: $request
        );

        return response()->noContent();
    }

    protected function assertImageBelongsToProduct(
        Product $product,
        ProductImage $image
    ): void {
        abort_if(
            (int) $image->product_id
            !== (int) $product->id,
            404
        );
    }

    protected function assertAssociations(
        Product $product,
        ?int $variantId,
        ?int $attributeValueId
    ): void {
        $variant = null;

        if ($variantId !== null) {
            $variant =
                ProductVariant::query()
                    ->whereKey(
                        $variantId
                    )
                    ->where(
                        'product_id',
                        $product->id
                    )
                    ->first();

            if ($variant === null) {
                throw ValidationException::withMessages([
                    'product_variant_id' =>
                        'The selected variant does not belong to this product.',
                ]);
            }
        }

        if ($attributeValueId === null) {
            return;
        }

        $attributeValue =
            AttributeValue::query()
                ->with('attribute')
                ->find(
                    $attributeValueId
                );

        if (
            $attributeValue === null
            || $attributeValue
                ->attribute?->type !== 'color'
        ) {
            throw ValidationException::withMessages([
                'attribute_value_id' =>
                    'The selected attribute value must be a color.',
            ]);
        }

        if ($variant !== null) {
            $hasColor =
                $variant
                    ->attributeValues()
                    ->whereKey(
                        $attributeValueId
                    )
                    ->exists();

            if (! $hasColor) {
                throw ValidationException::withMessages([
                    'attribute_value_id' =>
                        'The selected color does not belong to this variant.',
                ]);
            }

            return;
        }

        $productHasColor =
            ProductVariant::query()
                ->where(
                    'product_id',
                    $product->id
                )
                ->whereHas(
                    'attributeValues',
                    fn ($query) =>
                        $query->whereKey(
                            $attributeValueId
                        )
                )
                ->exists();

        if (! $productHasColor) {
            throw ValidationException::withMessages([
                'attribute_value_id' =>
                    'The selected color is not used by this product.',
            ]);
        }
    }

    protected function scopeHasPrimary(
        Product $product,
        ?int $variantId,
        ?int $attributeValueId
    ): bool {
        return $this
            ->scopeQuery(
                $product,
                $variantId,
                $attributeValueId
            )
            ->where(
                'is_primary',
                true
            )
            ->exists();
    }

    protected function clearPrimaryScope(
        Product $product,
        ?int $variantId,
        ?int $attributeValueId,
        int $exceptImageId
    ): void {
        $this->scopeQuery(
            $product,
            $variantId,
            $attributeValueId
        )
            ->where(
                'id',
                '!=',
                $exceptImageId
            )
            ->update([
                'is_primary' => false,
            ]);
    }

    protected function ensurePrimaryScope(
        Product $product,
        ?int $variantId,
        ?int $attributeValueId,
        ?int $excludeImageId = null
    ): void {
        if (
            $this->scopeHasPrimary(
                $product,
                $variantId,
                $attributeValueId
            )
        ) {
            return;
        }

        $query =
            $this->scopeQuery(
                $product,
                $variantId,
                $attributeValueId
            );

        if ($excludeImageId !== null) {
            $query->where(
                'id',
                '!=',
                $excludeImageId
            );
        }

        $replacement =
            $query
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

        if ($replacement) {
            $replacement->update([
                'is_primary' => true,
            ]);
        }
    }

    protected function scopeQuery(
        Product $product,
        ?int $variantId,
        ?int $attributeValueId
    ): Builder {
        $query =
            ProductImage::query()
                ->where(
                    'product_id',
                    $product->id
                );

        if ($variantId === null) {
            $query->whereNull(
                'product_variant_id'
            );
        } else {
            $query->where(
                'product_variant_id',
                $variantId
            );
        }

        if ($attributeValueId === null) {
            $query->whereNull(
                'attribute_value_id'
            );
        } else {
            $query->where(
                'attribute_value_id',
                $attributeValueId
            );
        }

        return $query;
    }

    protected function serializeImage(
        ProductImage $image
    ): array {
        return [
            'id' =>
                $image->id,

            'product_id' =>
                $image->product_id,

            'product_variant_id' =>
                $image->product_variant_id,

            'attribute_value_id' =>
                $image->attribute_value_id,

            'image_path' =>
                $image->image_path,

            'alt_text' =>
                $image->alt_text,

            'sort_order' =>
                $image->sort_order,

            'is_primary' =>
                (bool) $image->is_primary,

            'variant' =>
                $image->relationLoaded('variant')
                && $image->variant
                    ? [
                        'id' =>
                            $image->variant->id,

                        'sku' =>
                            $image->variant->sku,
                    ]
                    : null,

            'attribute_value' =>
                $image->relationLoaded(
                    'attributeValue'
                )
                && $image->attributeValue
                    ? [
                        'id' =>
                            $image
                                ->attributeValue
                                ->id,

                        'value' =>
                            $image
                                ->attributeValue
                                ->value,

                        'attribute' =>
                            $image
                                ->attributeValue
                                ->relationLoaded(
                                    'attribute'
                                )
                                && $image
                                    ->attributeValue
                                    ->attribute
                                ? [
                                    'id' =>
                                        $image
                                            ->attributeValue
                                            ->attribute
                                            ->id,

                                    'name' =>
                                        $image
                                            ->attributeValue
                                            ->attribute
                                            ->name,

                                    'slug' =>
                                        $image
                                            ->attributeValue
                                            ->attribute
                                            ->slug,

                                    'type' =>
                                        $image
                                            ->attributeValue
                                            ->attribute
                                            ->type,
                                ]
                                : null,
                    ]
                    : null,
        ];
    }
}
