<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductListResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Support\ProductSearchIndexer;
use App\Support\ProductSearchRelevanceRanker;
use App\Support\SearchTextFuzzyMatcher;
use App\Support\SearchTextNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'category_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
            ],

            'category' => [
                'nullable',
                'string',
                'max:255',
            ],

            'search' => [
                'nullable',
                'string',
                'max:120',
            ],

            'price_min' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],

            'price_max' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],

            'color' => [
                'nullable',
                'string',
                'max:255',
            ],

            'size' => [
                'nullable',
                'string',
                'max:255',
            ],

            'material' => [
                'nullable',
                'string',
                'max:255',
            ],

            'availability' => [
                'nullable',
                'string',
                'max:100',
            ],

            'sort' => [
                'nullable',
                'string',
                'in:relevance,newest,price_asc,price_desc,popular,bestselling',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:48',
            ],

            'page' => [
                'nullable',
                'integer',
                'min:1',
                'max:10000',
            ],
        ]);

        /*
         * -----------------------------------------------------------
         * Validate Price Range
         * -----------------------------------------------------------
         */

        if (
            isset($validated['price_min'])
            && isset($validated['price_max'])
            && (float) $validated['price_min']
                > (float) $validated['price_max']
        ) {
            throw ValidationException::withMessages([
                'price_min' => [
                    'price_min must be less than or equal to price_max.',
                ],
            ]);
        }

        /*
         * -----------------------------------------------------------
         * Validate Attribute Filter Values
         * -----------------------------------------------------------
         */

        foreach (
            [
                'color' => $validated['color'] ?? null,
                'size' => $validated['size'] ?? null,
                'material' => $validated['material'] ?? null,
            ] as $attribute => $value
        ) {
            if (
                $value === null
                || trim($value) === ''
            ) {
                continue;
            }

            $values = collect(
                explode(',', $value)
            )
                ->map(
                    fn ($item) => trim($item)
                )
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (count($values) > 10) {
                throw ValidationException::withMessages([
                    $attribute => [
                        "{$attribute} may contain at most 10 values.",
                    ],
                ]);
            }

            foreach ($values as $filterValue) {
                if (mb_strlen($filterValue) > 100) {
                    throw ValidationException::withMessages([
                        $attribute => [
                            "Each {$attribute} value may not exceed 100 characters.",
                        ],
                    ]);
                }
            }
        }

        /*
         * -----------------------------------------------------------
         * Purchasable Variant Statuses
         * -----------------------------------------------------------
         */

        $purchasableStatuses = [
            VariantStatus::ACTIVE->value,
            VariantStatus::MADE_TO_ORDER->value,
        ];

        /*
         * -----------------------------------------------------------
         * Requested Sort
         * -----------------------------------------------------------
         */

        $search = trim(
            (string) ($validated['search'] ?? '')
        );

        $sort = $validated['sort']
            ?? (
                $search !== ''
                    ? 'relevance'
                    : 'newest'
            );

        if (
            $sort === 'relevance'
            && $search === ''
        ) {
            $sort = 'newest';
        }

        /*
         * -----------------------------------------------------------
         * Price Statistics Subquery
         * -----------------------------------------------------------
         */

        $priceStats = DB::table(
            'products as price_products'
        )
            ->leftJoin(
                'product_variants as price_variants',
                function ($join) use (
                    $purchasableStatuses
                ): void {
                    $join
                        ->on(
                            'price_variants.product_id',
                            '=',
                            'price_products.id'
                        )
                        ->whereIn(
                            'price_variants.status',
                            $purchasableStatuses
                        )
                        ->whereNull(
                            'price_variants.deleted_at'
                        );
                }
            )
            ->where(
                'price_products.status',
                ProductStatus::ACTIVE->value
            )
            ->whereNull(
                'price_products.deleted_at'
            )
            ->select(
                'price_products.id'
            )
            ->selectRaw(
                'COALESCE(
                    MIN(
                        COALESCE(
                            price_variants.price,
                            price_products.price
                        )
                    ),
                    price_products.price
                ) AS min_price'
            )
            ->selectRaw(
                'COALESCE(
                    MAX(
                        COALESCE(
                            price_variants.price,
                            price_products.price
                        )
                    ),
                    price_products.price
                ) AS max_price'
            )
            ->selectRaw(
                'COALESCE(
                    MIN(
                        CASE
                            WHEN price_variants.id IS NOT NULL
                              AND COALESCE(
                                price_variants.price,
                                price_products.price
                            ) > 0
                            THEN ROUND(
                                COALESCE(
                                    price_variants.price,
                                    price_products.price
                                ) * (
                                    100 - COALESCE(
                                        price_products.discount_percentage,
                                        0
                                    )
                                ) / 100,
                                2
                            )
                        END
                    ),
                    CASE
                        WHEN NOT EXISTS (
                            SELECT 1
                            FROM product_variants AS any_price_variants
                            WHERE any_price_variants.product_id = price_products.id
                              AND any_price_variants.deleted_at IS NULL
                        )
                          AND price_products.price > 0
                        THEN ROUND(
                            price_products.price * (
                                100 - COALESCE(
                                    price_products.discount_percentage,
                                    0
                                )
                            ) / 100,
                            2
                        )
                    END
                ) AS min_discounted_price'
            )
            ->selectRaw(
                'COALESCE(
                    MAX(
                        CASE
                            WHEN price_variants.id IS NOT NULL
                              AND COALESCE(
                                price_variants.price,
                                price_products.price
                            ) > 0
                            THEN ROUND(
                                COALESCE(
                                    price_variants.price,
                                    price_products.price
                                ) * (
                                    100 - COALESCE(
                                        price_products.discount_percentage,
                                        0
                                    )
                                ) / 100,
                                2
                            )
                        END
                    ),
                    CASE
                        WHEN NOT EXISTS (
                            SELECT 1
                            FROM product_variants AS any_price_variants
                            WHERE any_price_variants.product_id = price_products.id
                              AND any_price_variants.deleted_at IS NULL
                        )
                          AND price_products.price > 0
                        THEN ROUND(
                            price_products.price * (
                                100 - COALESCE(
                                    price_products.discount_percentage,
                                    0
                                )
                            ) / 100,
                            2
                        )
                    END
                ) AS max_discounted_price'
            )
            ->groupBy(
                'price_products.id',
                'price_products.price',
                'price_products.discount_percentage'
            );

        /*
         * -----------------------------------------------------------
         * Main Query
         * -----------------------------------------------------------
         */

        $query = Product::query()
            ->select([
                'products.id',
                'products.category_id',
                'products.name',
                'products.slug',
                'products.short_description',
                'products.brand',
                'products.price',
                'products.discount_percentage',
                'products.status',
                'products.is_featured',
                'products.created_at',
            ])

            ->leftJoinSub(
                $priceStats,
                'price_stats',
                function ($join): void {
                    $join->on(
                        'price_stats.id',
                        '=',
                        'products.id'
                    );
                }
            )

            ->addSelect([
                'price_stats.min_price',
                'price_stats.max_price',
                'price_stats.min_discounted_price',
                'price_stats.max_discounted_price',
            ])

            ->selectRaw(
                'EXISTS (
                    SELECT 1
                    FROM product_variants AS any_variants
                    WHERE any_variants.product_id = products.id
                      AND any_variants.deleted_at IS NULL
                ) AS has_any_variant'
            )

            ->selectRaw(
                'EXISTS (
                    SELECT 1
                    FROM product_variants AS purchasable_variants
                    WHERE purchasable_variants.product_id = products.id
                      AND purchasable_variants.status IN (?, ?)
                      AND purchasable_variants.deleted_at IS NULL
                ) AS has_purchasable_variant',
                $purchasableStatuses
            )

            ->selectRaw(
                'EXISTS (
                    SELECT 1
                    FROM product_variants AS mto_variants
                    WHERE mto_variants.product_id = products.id
                      AND mto_variants.status = ?
                      AND mto_variants.deleted_at IS NULL
                ) AS has_made_to_order_variant',
                [
                    VariantStatus::MADE_TO_ORDER->value,
                ]
            )

            ->selectRaw(
                'EXISTS (
                    SELECT 1
                    FROM product_variants AS inventory_variants
                    INNER JOIN inventories
                        ON inventories.product_variant_id = inventory_variants.id
                    WHERE inventory_variants.product_id = products.id
                      AND inventory_variants.status = ?
                      AND inventory_variants.deleted_at IS NULL
                      AND inventories.quantity > inventories.reserved_quantity
                ) AS has_available_inventory',
                [
                    VariantStatus::ACTIVE->value,
                ]
            )

            ->where(
                'products.status',
                ProductStatus::ACTIVE->value
            )

            ->whereHas(
                'category',
                function (Builder $categoryQuery): void {
                    $categoryQuery->where(
                        'is_active',
                        true
                    );
                }
            )

            ->with([
                'category' => function ($categoryQuery): void {
                    $categoryQuery
                        ->where(
                            'is_active',
                            true
                        )
                        ->select([
                            'categories.id',
                            'categories.name',
                            'categories.slug',
                        ]);
                },

                'collections' => function ($collectionQuery): void {
                    $collectionQuery
                        ->where(
                            'is_active',
                            true
                        )
                        ->select([
                            'collections.id',
                            'collections.name',
                            'collections.slug',
                        ]);
                },


                'galleryImages' => function ($imageQuery): void {
                    $imageQuery->select([
                        'product_images.id',
                        'product_images.product_id',
                        'product_images.attribute_value_id',
                        'product_images.image_path',
                        'product_images.alt_text',
                        'product_images.sort_order',
                        'product_images.is_primary',
                    ]);
                },
            ]);

        /*
         * -----------------------------------------------------------
         * Public Product Search
         * -----------------------------------------------------------
         */

        $searchCategoryIds = [];

        if ($search !== '') {
            $normalizedSearch =
                SearchTextNormalizer::normalize(
                    $search
                );

            $compactSearch =
                SearchTextNormalizer::compact(
                    $search
                );

            if (
                $normalizedSearch === '' &&
                $compactSearch === ''
            ) {
                $query->whereRaw('1 = 0');
            } else {
                $baseSearchQuery =
                    clone $query;

                $normalizedLike =
                    '%' . $normalizedSearch . '%';

                $compactLike =
                    '%' . $compactSearch . '%';

                $normalizedQuery =
                    clone $baseSearchQuery;

                $normalizedQuery->where(
                    function (
                        Builder $searchQuery
                    ) use (
                        $normalizedLike,
                        $compactLike
                    ): void {
                        $searchQuery
                            ->where(
                                'products.search_text',
                                'like',
                                $normalizedLike
                            )
                            ->orWhere(
                                'products.search_compact',
                                'like',
                                $compactLike
                            );
                    }
                );

                if (
                    (clone $normalizedQuery)
                        ->exists()
                ) {
                    $query =
                        $normalizedQuery;
                } else {
                    $fuzzyProductIds = [];

                    $candidateQuery =
                        clone $baseSearchQuery;

                    $candidateQuery
                        ->setEagerLoads([]);

                    $candidateQuery
                        ->reorder()
                        ->select([
                            'products.id',
                            'products.name',
                            'products.brand',
                            'products.slug',
                        ])
                        ->chunkById(
                            200,
                            function (
                                $products
                            ) use (
                                $search,
                                &$fuzzyProductIds
                            ): void {
                                foreach (
                                    $products
                                    as $product
                                ) {
                                    if (
                                        !SearchTextFuzzyMatcher::matches(
                                            $search,
                                            ProductSearchIndexer::candidates(
                                                $product
                                            )
                                        )
                                    ) {
                                        continue;
                                    }

                                    $fuzzyProductIds[] =
                                        (int) $product->id;
                                }
                            },
                            'products.id',
                            'id'
                        );

                    $query =
                        $baseSearchQuery;

                    if ($fuzzyProductIds === []) {
                        $query->whereRaw(
                            '1 = 0'
                        );
                    } else {
                        $query->whereIn(
                            'products.id',
                            $fuzzyProductIds
                        );
                    }
                }
            }
        }

        if ($search !== '') {
            $searchCategoryIds = (clone $query)
                ->reorder()
                ->select(
                    'products.category_id'
                )
                ->distinct()
                ->pluck(
                    'products.category_id'
                )
                ->map(
                    static fn ($categoryId): int =>
                        (int) $categoryId
                )
                ->sort()
                ->values()
                ->all();
        }


        /*
         * -----------------------------------------------------------
         * Best Selling Statistics
         * -----------------------------------------------------------
         */

        if ($sort === 'bestselling') {
            $query->addSelect([
                'sold_quantity' => DB::table(
                    'order_items as sales_items'
                )
                    ->join(
                        'orders as sales_orders',
                        'sales_orders.id',
                        '=',
                        'sales_items.order_id'
                    )
                    ->whereColumn(
                        'sales_items.product_id',
                        'products.id'
                    )
                    ->whereIn(
                        'sales_orders.status',
                        [
                            OrderStatus::PAID->value,
                            OrderStatus::PROCESSING->value,
                            OrderStatus::SHIPPED->value,
                            OrderStatus::DELIVERED->value,
                        ]
                    )
                    ->selectRaw(
                        'COALESCE(
                            SUM(sales_items.quantity),
                            0
                        )'
                    ),
            ]);
        }

        /*
         * -----------------------------------------------------------
         * Wishlist Popularity Statistics
         * -----------------------------------------------------------
         */
        if ($sort === 'popular') {
            $query->addSelect([
                'wishlist_count' => DB::table(
                    'wishlist_items as popularity_items'
                )
                    ->whereColumn(
                        'popularity_items.product_id',
                        'products.id'
                    )
                    ->selectRaw('COUNT(*)'),
            ]);
        }
        /*
         * -----------------------------------------------------------
         * Hierarchical Category Filters
         * -----------------------------------------------------------
         *
         * A selected category includes:
         *
         * - the selected category itself
         * - every active child
         * - every active grandchild / deeper descendant
         *
         * Examples:
         *
         * kitchen-electrics
         *     -> kettles
         *     -> toasters
         *     -> stand-mixers
         *     -> coffee-espresso-machines
         *     -> soda-makers
         *
         * cookware
         *     -> cookware-sets
         *     -> frying-pans
         *     -> woks
         *     -> grills
         */

        if (
            isset($validated['category_id'])
            || ! empty($validated['category'])
        ) {
            /*
             * Load the active taxonomy once.
             *
             * Category trees are small, so resolving descendants in memory
             * avoids recursive N+1 queries while remaining database-agnostic.
             */
            $activeCategories = Category::query()
                ->where(
                    'is_active',
                    true
                )
                ->get([
                    'id',
                    'parent_id',
                    'slug',
                ]);

            /*
             * category_id filter
             */
            if (isset($validated['category_id'])) {
                $rootCategory = $activeCategories
                    ->firstWhere(
                        'id',
                        (int) $validated['category_id']
                    );

                $categoryIds =
                    $this->categoryAndDescendantIds(
                        $activeCategories,
                        $rootCategory
                    );

                if ($categoryIds === []) {
                    /*
                     * Requested category exists in the database because
                     * validation already passed, but it is not publicly active.
                     */
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn(
                        'products.category_id',
                        $categoryIds
                    );
                }
            }

            /*
             * category slug filter
             */
            if (! empty($validated['category'])) {
                $rootCategory = $activeCategories
                    ->firstWhere(
                        'slug',
                        $validated['category']
                    );

                $categoryIds =
                    $this->categoryAndDescendantIds(
                        $activeCategories,
                        $rootCategory
                    );

                if ($categoryIds === []) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn(
                        'products.category_id',
                        $categoryIds
                    );
                }
            }
        }

        /*
         * -----------------------------------------------------------
         * Price Filters
         * -----------------------------------------------------------
         */

        if (
            isset($validated['price_min'])
            || isset($validated['price_max'])
        ) {
            $priceMin = $validated['price_min'] ?? null;
            $priceMax = $validated['price_max'] ?? null;

            $query->where(function (Builder $priceQuery) use (
                $priceMin,
                $priceMax,
                $purchasableStatuses
            ): void {
                $priceQuery->where(function (
                    Builder $basePriceQuery
                ) use (
                    $priceMin,
                    $priceMax
                ): void {
                    if ($priceMin !== null) {
                        $basePriceQuery->where(
                            'products.price',
                            '>=',
                            $priceMin
                        );
                    }

                    if ($priceMax !== null) {
                        $basePriceQuery->where(
                            'products.price',
                            '<=',
                            $priceMax
                        );
                    }
                });

                $priceQuery->orWhereHas(
                    'variants',
                    function (Builder $variantQuery) use (
                        $priceMin,
                        $priceMax,
                        $purchasableStatuses
                    ): void {
                        $variantQuery
                            ->whereIn(
                                'status',
                                $purchasableStatuses
                            )
                            ->where(function (
                                Builder $variantPriceQuery
                            ) use (
                                $priceMin,
                                $priceMax
                            ): void {
                                if ($priceMin !== null) {
                                    $variantPriceQuery->whereRaw(
                                        'COALESCE(
                                            product_variants.price,
                                            products.price
                                        ) >= ?',
                                        [$priceMin]
                                    );
                                }

                                if ($priceMax !== null) {
                                    $variantPriceQuery->whereRaw(
                                        'COALESCE(
                                            product_variants.price,
                                            products.price
                                        ) <= ?',
                                        [$priceMax]
                                    );
                                }
                            });
                    }
                );
            });
        }

        /*
         * -----------------------------------------------------------
         * Attribute Filters
         * -----------------------------------------------------------
         */

        $this->applyAttributeFilter(
            $query,
            'color',
            $validated['color'] ?? null
        );

        $this->applyAttributeFilter(
            $query,
            'size',
            $validated['size'] ?? null
        );

        $this->applyAttributeFilter(
            $query,
            'material',
            $validated['material'] ?? null
        );

        /*
         * Storefront availability filter.
         * Applied before sorting and pagination.
         */
        $availabilityValues = collect(
            explode(',', (string) ($validated['availability'] ?? ''))
        )
            ->map(static fn ($value) => trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $allowedAvailability = [
            'available',
            'pre_order',
            'out_of_stock',
        ];

        if (array_diff($availabilityValues, $allowedAvailability)) {
            throw ValidationException::withMessages([
                'availability' => ['Invalid availability filter.'],
            ]);
        }

        if ($availabilityValues !== []) {
            $availableSql = 'EXISTS (
                SELECT 1
                FROM product_variants AS stock_variants
                INNER JOIN inventories AS stock_inventory
                    ON stock_inventory.product_variant_id = stock_variants.id
                WHERE stock_variants.product_id = products.id
                  AND stock_variants.status = ?
                  AND stock_variants.deleted_at IS NULL
                  AND stock_inventory.quantity > stock_inventory.reserved_quantity
            )';

            $madeToOrderSql = 'EXISTS (
                SELECT 1
                FROM product_variants AS preorder_variants
                WHERE preorder_variants.product_id = products.id
                  AND preorder_variants.status = ?
                  AND preorder_variants.deleted_at IS NULL
            )';

            $anyVariantSql = 'EXISTS (
                SELECT 1
                FROM product_variants AS any_stock_variants
                WHERE any_stock_variants.product_id = products.id
                  AND any_stock_variants.deleted_at IS NULL
            )';

            $hasPurchasableSql = 'EXISTS (
                SELECT 1
                FROM product_variants AS purchasable_stock_variants
                WHERE purchasable_stock_variants.product_id = products.id
                  AND purchasable_stock_variants.status IN (?, ?)
                  AND purchasable_stock_variants.deleted_at IS NULL
            )';

            $onlineCheckoutEnabled = (bool) config(
                'storefront.online_checkout_enabled',
                false
            );

            $query->where(function (Builder $stockQuery) use (
                $availabilityValues,
                $availableSql,
                $madeToOrderSql,
                $anyVariantSql,
                $hasPurchasableSql,
                $onlineCheckoutEnabled
            ): void {
                foreach ($availabilityValues as $value) {
                    if ($value === 'available') {
                        $stockQuery->orWhereRaw(
                            $availableSql,
                            [VariantStatus::ACTIVE->value]
                        );
                    }

                    if ($value === 'pre_order') {
                        $stockQuery->orWhereRaw(
                            'NOT ' . $availableSql . ' AND ' . $madeToOrderSql,
                            [
                                VariantStatus::ACTIVE->value,
                                VariantStatus::MADE_TO_ORDER->value,
                            ]
                        );
                    }

                    if ($value === 'out_of_stock') {
                        $notPurchasable = $onlineCheckoutEnabled
                            ? 'NOT ' . $hasPurchasableSql
                            : '(NOT ' . $hasPurchasableSql .
                                ' AND ' . $anyVariantSql . ')';

                        $stockQuery->orWhereRaw(
                            '(' . $notPurchasable . ') OR (' .
                                'NOT ' . $availableSql .
                                ' AND NOT ' . $madeToOrderSql . ')',
                            [
                                VariantStatus::ACTIVE->value,
                                VariantStatus::MADE_TO_ORDER->value,
                                VariantStatus::ACTIVE->value,
                                VariantStatus::MADE_TO_ORDER->value,
                            ]
                        );
                    }
                }
            });
        }

        /*
         * -----------------------------------------------------------
         * Sorting
         * -----------------------------------------------------------
         */

        $relevanceOrderSql =
            'CASE WHEN 1 = 1 THEN 1000 END';

        $relevanceOrderBindings = [];

        if (
            $sort === 'relevance' &&
            $search !== ''
        ) {
            $relevanceScores = [];

            $relevanceQuery =
                clone $query;

            $relevanceQuery
                ->setEagerLoads([]);

            $relevanceQuery
                ->reorder()
                ->select([
                    'products.id',
                    'products.name',
                    'products.brand',
                    'products.slug',
                ])
                ->chunkById(
                    200,
                    function (
                        $products
                    ) use (
                        $search,
                        &$relevanceScores
                    ): void {
                        foreach (
                            $products
                            as $product
                        ) {
                            $relevanceScores[
                                (int) $product->id
                            ] =
                                ProductSearchRelevanceRanker::score(
                                    $product,
                                    $search
                                );
                        }
                    },
                    'products.id',
                    'id'
                );

            if ($relevanceScores !== []) {
                $cases = [];

                foreach (
                    $relevanceScores
                    as $productId => $score
                ) {
                    $cases[] =
                        'WHEN products.id = ? THEN ?';

                    $relevanceOrderBindings[] =
                        $productId;

                    $relevanceOrderBindings[] =
                        $score;
                }

                $relevanceOrderSql =
                    'CASE ' .
                    implode(
                        ' ',
                        $cases
                    ) .
                    ' ELSE 1000 END';
            }
        }
        match ($sort) {
            'relevance' => $query
                ->orderByRaw(
                    $relevanceOrderSql,
                    $relevanceOrderBindings
                )
                ->orderByDesc(
                    'products.created_at'
                )
                ->orderByDesc(
                    'products.id'
                ),
            'price_asc' => $query
                ->orderByRaw(
                    'COALESCE(
                        price_stats.min_discounted_price,
                        price_stats.min_price
                    ) ASC'
                )
                ->orderByDesc(
                    'products.id'
                ),

            'price_desc' => $query
                ->orderByRaw(
                    'COALESCE(
                        price_stats.min_discounted_price,
                        price_stats.min_price
                    ) DESC'
                )
                ->orderByDesc(
                    'products.id'
                ),

            'bestselling' => $query
                ->orderByDesc(
                    'sold_quantity'
                )
                ->orderByDesc(
                    'products.created_at'
                )
                ->orderByDesc(
                    'products.id'
                ),

            'popular' => $query
                ->orderByDesc(
                    'wishlist_count'
                )
                ->orderByDesc(
                    'products.created_at'
                )
                ->orderByDesc(
                    'products.id'
                ),

            'newest' => $query
                ->orderByDesc(
                    'products.created_at'
                )
                ->orderByDesc(
                    'products.id'
                ),

            default => $query
                ->orderByDesc(
                    'products.created_at'
                )
                ->orderByDesc(
                    'products.id'
                ),
        };

        /*
         * -----------------------------------------------------------
         * Simple Pagination
         * -----------------------------------------------------------
         */

        $products = $query->simplePaginate(
            perPage: $validated['per_page'] ?? 12,
            columns: ['*'],
            pageName: 'page',
            page: $validated['page'] ?? 1
        );

        return ProductListResource::collection(
            $products
        )->additional([
            'facets' => [
                'category_ids' =>
                    $searchCategoryIds,
            ],
        ]);
    }

    public function show(string $slug)
    {
        /*
         * -----------------------------------------------------------
         * Slug Hardening
         * -----------------------------------------------------------
         */

        if (
            ! preg_match(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $slug
            )
        ) {
            abort(
                404,
                'Product not found.'
            );
        }

        /*
         * -----------------------------------------------------------
         * Product Detail Query
         * -----------------------------------------------------------
         */

        $product = Product::query()
            ->withCount('variants')
            ->where(
                'slug',
                $slug
            )
            ->where(
                'status',
                ProductStatus::ACTIVE->value
            )
            ->with([
                'category' => function ($query): void {
                    $query->where(
                        'is_active',
                        true
                    );
                },

                'collections' => function ($query): void {
                    $query->where(
                        'is_active',
                        true
                    );
                },

                'galleryImages',

                'variants' => function ($query) {
                    $query
                        ->whereIn(
                            'status',
                            [
                                VariantStatus::ACTIVE->value,
                                VariantStatus::MADE_TO_ORDER->value,
                            ]
                        )
                        ->with([
                            'attributeValues.attribute',
                            'images',
                            'inventory',
                        ]);
                },
            ])
            ->first();

        if (! $product) {
            abort(
                404,
                'Product not found.'
            );
        }

        return new ProductResource($product);
    }

    /**
     * Resolve one active category plus all of its active descendants.
     *
     * @param mixed $categories
     * @return array<int>
     */
    private function categoryAndDescendantIds(
        $categories,
        ?Category $rootCategory
    ): array {
        if ($rootCategory === null) {
            return [];
        }

        $ids = [];
        $visited = [];
        $queue = [
            (int) $rootCategory->id,
        ];

        while ($queue !== []) {
            $currentId = array_shift(
                $queue
            );

            if (
                isset(
                    $visited[$currentId]
                )
            ) {
                continue;
            }

            $visited[$currentId] = true;
            $ids[] = $currentId;

            $childIds = $categories
                ->where(
                    'parent_id',
                    $currentId
                )
                ->pluck('id')
                ->map(
                    fn ($id) => (int) $id
                )
                ->all();

            foreach ($childIds as $childId) {
                if (
                    ! isset(
                        $visited[$childId]
                    )
                ) {
                    $queue[] = $childId;
                }
            }
        }

        return array_values(
            array_unique(
                $ids
            )
        );
    }

    /**
     * Apply a Variant attribute filter.
     */
    private function applyAttributeFilter(
        Builder $query,
        string $attributeSlug,
        ?string $value
    ): void {
        if (
            $value === null
            || trim($value) === ''
        ) {
            return;
        }

        $values = collect(
            explode(',', $value)
        )
            ->map(
                fn ($item) => trim($item)
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($values === []) {
            return;
        }

        $query->whereHas(
            'variants',
            function (Builder $variantQuery) use (
                $attributeSlug,
                $values
            ): void {
                $variantQuery
                    ->whereIn(
                        'status',
                        [
                            VariantStatus::ACTIVE->value,
                            VariantStatus::MADE_TO_ORDER->value,
                        ]
                    )
                    ->whereHas(
                        'attributeValues',
                        function (Builder $valueQuery) use (
                            $attributeSlug,
                            $values
                        ): void {
                            $valueQuery
                                ->where(
                                    function (
                                        Builder $localizedValueQuery
                                    ) use (
                                        $values
                                    ): void {
                                        $localizedValueQuery
                                            ->whereIn(
                                                'value_en',
                                                $values
                                            )
                                            ->orWhereIn(
                                                'value_fa',
                                                $values
                                            );
                                    }
                                )
                                ->whereHas(
                                    'attribute',
                                    function (
                                        Builder $attributeQuery
                                    ) use (
                                        $attributeSlug
                                    ): void {
                                        $attributeQuery->where(
                                            'slug',
                                            $attributeSlug
                                        );
                                    }
                                );
                        }
                    );
            }
        );
    }
}
