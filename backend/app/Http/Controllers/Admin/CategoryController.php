<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller implements HasMiddleware
{
    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */

    private const CATEGORY_TREE_CACHE_LOCALES = [
        'en',
        'fa',
    ];

    private const HOMEPAGE_CACHE_LOCALES = [
        'en',
        'fa',
    ];

    public function __construct(
        protected ActivityLogService $activityLogService
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('auth:sanctum'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        abort_unless(
            auth()->user()->hasPermission('categories.view'),
            403
        );

        $categories = Category::query()
            ->with([
                'children',
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
            ->orderBy(
                'sort_order'
            )
            ->paginate(20);

        return CategoryResource::adminCollection(
            $categories
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Tree
    |--------------------------------------------------------------------------
    */

    public function tree()
    {
        abort_unless(
            auth()->user()->hasPermission('categories.view'),
            403
        );

        $categories = Category::query()
            ->whereNull('parent_id')
            ->where(
                'is_active',
                true
            )
            ->with(
                'childrenRecursive'
            )
            ->orderBy(
                'sort_order'
            )
            ->get();

        return CategoryResource::adminCollection(
            $categories
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function store(
        StoreCategoryRequest $request
    ) {
        abort_unless(
            auth()->user()->hasPermission('categories.create'),
            403
        );

        $validated = $request->validated();

        $name = $validated['name'];

        $slug = $validated['slug']
            ?? $this->generateSlug($name);

        $category = Category::create([
            'parent_id' => $validated['parent_id']
                ?? null,

            'name' => $name,

            'slug' => $slug,

            'description' => $validated['description']
                ?? null,

            'seo_title' => $validated['seo_title']
                ?? null,

            'seo_description' => $validated['seo_description']
                ?? null,

            'seo_image' => $validated['seo_image']
                ?? null,

            'image' => $validated['image']
                ?? null,

            'sort_order' => $validated['sort_order']
                ?? 0,

            'is_active' => $validated['is_active']
                ?? true,
        ]);

        /*
         * A newly created category can affect:
         *
         * - the public category tree
         * - homepage sections referencing this category
         */
        $this->forgetCategoryTreeCache();
        $this->forgetHomepageCache();

        $this->activityLogService->log(
            action: 'categories.created',
            description: 'Category created.',
            properties: [
                'category' => [
                    'id' => $category->id,
                    'slug' => $category->slug,
                ],
            ],
            subject: $category,
            request: $request
        );

        return (CategoryResource::admin(
            $category->load('children')
        ))
            ->response()
            ->setStatusCode(201);
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function show(Category $category)
    {
        abort_unless(
            auth()->user()->hasPermission('categories.view'),
            403
        );

        $category->load([
            'parent',
            'children',
        ]);

        return CategoryResource::admin(
            $category
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(
        UpdateCategoryRequest $request,
        Category $category
    ) {
        abort_unless(
            auth()->user()->hasPermission('categories.update'),
            403
        );

        $validated = $request->validated();

        if (
            array_key_exists(
                'parent_id',
                $validated
            )
            && $validated['parent_id'] !== null
        ) {
            $candidateParent =
                Category::query()->find(
                    $validated['parent_id']
                );

            while ($candidateParent) {
                if (
                    (int) $candidateParent->id
                    === (int) $category->id
                ) {
                    throw ValidationException::withMessages([
                        'parent_id' =>
                            'A category cannot be moved under one of its descendants.',
                    ]);
                }

                $candidateParent =
                    $candidateParent
                        ->parent()
                        ->first();
            }
        }

        if (array_key_exists('name', $validated)) {
            $currentName = $this->decodeTranslations(
                $category->getRawOriginal('name')
            );

            $validated['name'] = array_merge(
                $currentName,
                $validated['name']
            );
        }

        if (array_key_exists('description', $validated)) {
            if ($validated['description'] === null) {
                $validated['description'] = null;
            } else {
                $currentDescription = $this->decodeTranslations(
                    $category->getRawOriginal('description')
                );

                $validated['description'] = array_merge(
                    $currentDescription,
                    $validated['description']
                );
            }
        }

        if (
            array_key_exists('name', $validated)
            && ! array_key_exists('slug', $validated)
        ) {
            $validated['slug'] = $this->generateSlug(
                $validated['name']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Merge SEO translations without losing other locales
        |--------------------------------------------------------------------------
        */
        foreach (['seo_title', 'seo_description'] as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            if ($validated[$field] === null) {
                continue;
            }

            $currentTranslations = $this->decodeTranslations(
                $category->getRawOriginal($field)
            );

            $validated[$field] = array_merge(
                $currentTranslations,
                $validated[$field]
            );
        }

        $updateData = [];

        foreach (
            [
                'parent_id',
                'name',
                'slug',
                'description',
                'seo_title',
                'seo_description',
                'seo_image',
                'image',
                'sort_order',
                'is_active',
            ] as $field
        ) {
            if (array_key_exists($field, $validated)) {
                $updateData[$field] = $validated[$field];
            }
        }

        if ($updateData !== []) {
            $category->update(
                $updateData
            );
        }

        $changes = $category->getChanges();

        $category->refresh();

        if ($changes !== []) {
            /*
             * Any successful category change can affect:
             *
             * - category tree visibility
             * - category hierarchy
             * - localized name
             * - slug
             * - image
             * - sort order
             * - homepage category references
             */
            $this->forgetCategoryTreeCache();
            $this->forgetHomepageCache();

            $this->activityLogService->log(
                action: 'categories.updated',
                description: 'Category updated.',
                properties: [
                    'changed_fields' => array_keys(
                        $changes
                    ),
                    'changes' => $changes,
                ],
                subject: $category,
                request: $request
            );
        }

        return CategoryResource::admin(
            $category->fresh()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function destroy(Category $category)
    {
        abort_unless(
            auth()->user()->hasPermission('categories.delete'),
            403
        );

        if ($category->children()->exists()) {
            return response()->json([
                'message' =>
                    'Cannot delete category with children',
            ], 422);
        }

        if ($category->products()->withTrashed()->exists()) {
            return response()->json([
                'message' =>
                    'Cannot delete category with products',
            ], 422);
        }

        $categoryMetadata = [
            'category' => [
                'id' => $category->id,
                'slug' => $category->slug,
            ],
        ];

        try {
            $category->delete();
        } catch (QueryException $exception) {
            // A concurrent reference or another FK can still block deletion.
            // Never disable referential integrity to delete a category.
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1451) {
                throw $exception;
            }

            return response()->json([
                'message' => 'Cannot delete category because it is referenced by other records',
            ], 409);
        }

        /*
         * Deleted category must disappear from:
         *
         * - the public category tree
         * - homepage category references
         */
        $this->forgetCategoryTreeCache();
        $this->forgetHomepageCache();

        $this->activityLogService->log(
            action: 'categories.deleted',
            description: 'Category deleted.',
            properties: $categoryMetadata,
            subject: $category
        );

        return response()->json([
            'message' =>
                'Category deleted successfully',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Cache Helpers
    |--------------------------------------------------------------------------
    */

    protected function forgetCategoryTreeCache(): void
    {
        foreach (self::CATEGORY_TREE_CACHE_LOCALES as $locale) {
            Cache::store(config('cache.default'))->forget(
                "tkb:categories:tree:{$locale}"
            );
        }
    }

    protected function forgetHomepageCache(): void
    {
        foreach (self::HOMEPAGE_CACHE_LOCALES as $locale) {
            Cache::store(config('cache.default'))->forget(
                "tkb:homepage:v2:{$locale}"
            );
        }
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
        $englishName = $name['en'] ?? null;

        if (! $englishName) {
            return Str::lower(
                Str::random(12)
            );
        }

        return Str::slug(
            $englishName
        );
    }
}
