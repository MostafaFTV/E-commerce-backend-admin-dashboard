<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Product;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {
    }

    public function index(
        Request $request
    ): JsonResponse {
        abort_unless(
            auth()->user()->hasPermission('products.view'),
            403
        );

        $brands = Brand::query()
            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $search = trim(
                        $request
                            ->string('search')
                            ->toString()
                    );

                    $query->where(
                        function ($query) use ($search) {
                            $query
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'slug',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->when(
                $request->has('is_active'),
                fn ($query) =>
                    $query->where(
                        'is_active',
                        $request->boolean('is_active')
                    )
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $brands,
        ]);
    }

    public function store(
        Request $request
    ): JsonResponse {
        abort_unless(
            auth()->user()->hasAnyPermission([
                'products.create',
                'products.update',
            ]),
            403
        );

        if ($request->has('name')) {
            $request->merge([
                'name' => trim(
                    (string) $request->input('name')
                ),
            ]);
        }

        if ($request->has('slug')) {
            $slug = trim(
                (string) $request->input('slug')
            );

            $request->merge([
                'slug' => $slug !== ''
                    ? Str::lower($slug)
                    : null,
            ]);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                'unique:brands,name',
            ],

            'slug' => [
                'nullable',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'unique:brands,slug',
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
        ]);

        $brand = Brand::create([
            'name' => $validated['name'],

            'slug' => $validated['slug']
                ?? $this->generateUniqueSlug(
                    $validated['name']
                ),

            'is_active' =>
                $validated['is_active']
                ?? true,

            'sort_order' =>
                $validated['sort_order']
                ?? 0,
        ]);

        $this->activityLogService->log(
            action: 'brands.created',
            description: 'Brand created.',
            properties: [
                'brand' => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                ],
            ],
            subject: $brand,
            request: $request
        );

        return response()->json([
            'data' => $brand,
        ], 201);
    }

    public function update(
        Request $request,
        Brand $brand
    ): JsonResponse {
        abort_unless(
            auth()->user()->hasPermission('products.update'),
            403
        );

        if ($request->has('name')) {
            $request->merge([
                'name' => trim(
                    (string) $request->input('name')
                ),
            ]);
        }

        if ($request->has('slug')) {
            $slug = trim(
                (string) $request->input('slug')
            );

            $request->merge([
                'slug' => $slug !== ''
                    ? Str::lower($slug)
                    : null,
            ]);
        }

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:150',

                Rule::unique(
                    'brands',
                    'name'
                )->ignore(
                    $brand->id
                ),
            ],

            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',

                Rule::unique(
                    'brands',
                    'slug'
                )->ignore(
                    $brand->id
                ),
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
        ]);

        $oldName = $brand->name;

        DB::transaction(
            function () use (
                $brand,
                $validated,
                $oldName
            ): void {
                $updateData = [];

                if (
                    array_key_exists(
                        'name',
                        $validated
                    )
                ) {
                    $updateData['name'] =
                        $validated['name'];
                }

                if (
                    array_key_exists(
                        'slug',
                        $validated
                    )
                ) {
                    if (
                        $validated['slug'] === null
                    ) {
                        $updateData['slug'] =
                            $this->generateUniqueSlug(
                                $validated['name']
                                    ?? $brand->name,
                                $brand
                            );
                    } else {
                        $updateData['slug'] =
                            $validated['slug'];
                    }
                } elseif (
                    array_key_exists(
                        'name',
                        $validated
                    )
                ) {
                    $updateData['slug'] =
                        $this->generateUniqueSlug(
                            $validated['name'],
                            $brand
                        );
                }

                foreach (
                    [
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

                if ($updateData !== []) {
                    $brand->update(
                        $updateData
                    );
                }

                $brand->refresh();

                if (
                    $oldName !== $brand->name
                ) {
                    Product::query()
                        ->where(
                            'brand',
                            $oldName
                        )
                        ->update([
                            'brand' => $brand->name,
                        ]);
                }
            }
        );

        $brand->refresh();

        $this->activityLogService->log(
            action: 'brands.updated',
            description: 'Brand updated.',
            properties: [
                'brand' => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                ],
                'previous_name' => $oldName,
            ],
            subject: $brand,
            request: $request
        );

        return response()->json([
            'data' => $brand,
        ]);
    }

    public function destroy(
        Request $request,
        Brand $brand
    ): JsonResponse {
        abort_unless(
            auth()->user()->hasPermission('products.update'),
            403
        );

        if (
            Product::query()
                ->where(
                    'brand',
                    $brand->name
                )
                ->exists()
        ) {
            return response()->json([
                'message' =>
                    'Cannot delete a brand that is used by products.',
            ], 422);
        }

        $metadata = [
            'id' => $brand->id,
            'name' => $brand->name,
            'slug' => $brand->slug,
        ];

        $brand->delete();

        $this->activityLogService->log(
            action: 'brands.deleted',
            description: 'Brand deleted.',
            properties: [
                'brand' => $metadata,
            ],
            subject: $brand,
            request: $request
        );

        return response()->json([
            'message' =>
                'Brand deleted successfully.',
        ]);
    }

    protected function generateUniqueSlug(
        string $name,
        ?Brand $ignore = null
    ): string {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = Str::lower(
                Str::random(12)
            );
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (
            Brand::query()
                ->where(
                    'slug',
                    $slug
                )
                ->when(
                    $ignore !== null,
                    fn ($query) =>
                        $query->where(
                            'id',
                            '!=',
                            $ignore->id
                        )
                )
                ->exists()
        ) {
            $slug =
                $baseSlug .
                '-' .
                $suffix;

            $suffix++;
        }

        return $slug;
    }
}
