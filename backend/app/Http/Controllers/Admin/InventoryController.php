<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InventoryConflictException;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Product;
use App\Services\InventoryService;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {
    }

    public function products(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:150',
            ],

            'stock_status' => [
                'nullable',
                Rule::in([
                    'in_stock',
                    'low_stock',
                    'out_of_stock',
                    'not_configured',
                ]),
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],

            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ]);

        $search = trim(
            (string) (
                $validated['search']
                ?? ''
            )
        );

        $stockStatus =
            $validated['stock_status']
            ?? null;

        $products = Product::query()
            ->with([
                'variants.attributeValues.attribute',
                'variants.inventory',
            ])
            ->when(
                $search !== '',
                function ($query) use ($search) {
                    $query->where(
                        function ($productQuery) use ($search) {
                            $productQuery
                                ->where(
                                    'slug',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'brand',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'name->en',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'name->fa',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhereHas(
                                    'variants',
                                    fn ($variantQuery) =>
                                        $variantQuery->where(
                                            'sku',
                                            'like',
                                            "%{$search}%"
                                        )
                                );
                        }
                    );
                }
            )
            ->when(
                $stockStatus === 'not_configured',
                fn ($query) =>
                    $query->where(
                        function ($productQuery) {
                            $productQuery
                                ->whereDoesntHave(
                                    'variants'
                                )
                                ->orWhereHas(
                                    'variants',
                                    fn ($variantQuery) =>
                                        $variantQuery
                                            ->where(
                                                'status',
                                                'active'
                                            )
                                            ->whereDoesntHave(
                                                'inventory'
                                            )
                                );
                        }
                    )
            )
            ->when(
                $stockStatus === 'out_of_stock',
                fn ($query) =>
                    $query
                        ->whereHas(
                            'variants'
                        )
                        ->whereDoesntHave(
                            'variants',
                            fn ($variantQuery) =>
                                $variantQuery
                                    ->where(
                                        'status',
                                        'active'
                                    )
                                    ->whereDoesntHave(
                                        'inventory'
                                    )
                        )
                        ->whereDoesntHave(
                            'variants',
                            fn ($variantQuery) =>
                                $variantQuery
                                    ->where(
                                        'status',
                                        'active'
                                    )
                                    ->whereHas(
                                        'inventory',
                                        fn ($inventoryQuery) =>
                                            $inventoryQuery->whereRaw(
                                                'inventories.quantity > inventories.reserved_quantity'
                                            )
                                    )
                        )
            )
            ->when(
                $stockStatus === 'low_stock',
                fn ($query) =>
                    $query
                        ->whereDoesntHave(
                            'variants',
                            fn ($variantQuery) =>
                                $variantQuery
                                    ->where(
                                        'status',
                                        'active'
                                    )
                                    ->whereDoesntHave(
                                        'inventory'
                                    )
                        )
                        ->whereHas(
                            'variants',
                            fn ($variantQuery) =>
                                $variantQuery
                                    ->where(
                                        'status',
                                        'active'
                                    )
                                    ->whereHas(
                                        'inventory',
                                        fn ($inventoryQuery) =>
                                            $inventoryQuery
                                                ->whereRaw(
                                                    'inventories.quantity > inventories.reserved_quantity'
                                                )
                                                ->whereRaw(
                                                    '(inventories.quantity - inventories.reserved_quantity) <= inventories.low_stock_threshold'
                                                )
                                    )
                        )
            )
            ->when(
                $stockStatus === 'in_stock',
                fn ($query) =>
                    $query
                        ->whereDoesntHave(
                            'variants',
                            fn ($variantQuery) =>
                                $variantQuery
                                    ->where(
                                        'status',
                                        'active'
                                    )
                                    ->whereDoesntHave(
                                        'inventory'
                                    )
                        )
                        ->whereDoesntHave(
                            'variants',
                            fn ($variantQuery) =>
                                $variantQuery
                                    ->where(
                                        'status',
                                        'active'
                                    )
                                    ->whereHas(
                                        'inventory',
                                        fn ($inventoryQuery) =>
                                            $inventoryQuery
                                                ->whereRaw(
                                                    'inventories.quantity > inventories.reserved_quantity'
                                                )
                                                ->whereRaw(
                                                    '(inventories.quantity - inventories.reserved_quantity) <= inventories.low_stock_threshold'
                                                )
                                    )
                        )
                        ->whereHas(
                            'variants',
                            fn ($variantQuery) =>
                                $variantQuery
                                    ->where(
                                        'status',
                                        'active'
                                    )
                                    ->whereHas(
                                        'inventory',
                                        fn ($inventoryQuery) =>
                                            $inventoryQuery->whereRaw(
                                                'inventories.quantity > inventories.reserved_quantity'
                                            )
                                    )
                        )
            )
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(
                (int) (
                    $validated['per_page']
                    ?? 25
                )
            );

        return response()->json([
            'data' =>
                $products
                    ->getCollection()
                    ->map(
                        fn (Product $product) =>
                            $this->productInventoryData(
                                $product
                            )
                    )
                    ->values(),

            'meta' => [
                'current_page' =>
                    $products->currentPage(),

                'last_page' =>
                    $products->lastPage(),

                'per_page' =>
                    $products->perPage(),

                'total' =>
                    $products->total(),
            ],
        ]);
    }

    public function showProduct(
        Product $product
    ): JsonResponse {
        $product->load([
            'variants.attributeValues.attribute',
            'variants.inventory',
        ]);

        return response()->json([
            'data' =>
                $this->productInventoryData(
                    $product
                ),
        ]);
    }

    public function index(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:150',
            ],

            'stock_status' => [
                'nullable',
                Rule::in([
                    'in_stock',
                    'low_stock',
                    'out_of_stock',
                ]),
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],

            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ]);

        $search = trim(
            (string) (
                $validated['search']
                ?? ''
            )
        );

        $stockStatus =
            $validated['stock_status']
            ?? null;

        $inventories = Inventory::query()
            ->with([
                'variant.product',
                'variant.attributeValues.attribute',
            ])
            ->when(
                $search !== '',
                function ($query) use ($search) {
                    $query->whereHas(
                        'variant',
                        function ($variantQuery) use ($search) {
                            $variantQuery
                                ->where(
                                    function ($variantSearch) use ($search) {
                                        $variantSearch
                                            ->where(
                                                'sku',
                                                'like',
                                                "%{$search}%"
                                            )
                                            ->orWhereHas(
                                                'product',
                                                function ($productQuery) use ($search) {
                                                    $productQuery
                                                        ->where(
                                                            'slug',
                                                            'like',
                                                            "%{$search}%"
                                                        )
                                                        ->orWhere(
                                                            'brand',
                                                            'like',
                                                            "%{$search}%"
                                                        )
                                                        ->orWhere(
                                                            'name->en',
                                                            'like',
                                                            "%{$search}%"
                                                        )
                                                        ->orWhere(
                                                            'name->fa',
                                                            'like',
                                                            "%{$search}%"
                                                        );
                                                }
                                            );
                                    }
                                );
                        }
                    );
                }
            )
            ->when(
                $stockStatus === 'out_of_stock',
                fn ($query) =>
                    $query->whereRaw(
                        'quantity <= reserved_quantity'
                    )
            )
            ->when(
                $stockStatus === 'low_stock',
                fn ($query) =>
                    $query
                        ->whereRaw(
                            'quantity > reserved_quantity'
                        )
                        ->whereRaw(
                            '(quantity - reserved_quantity) <= low_stock_threshold'
                        )
            )
            ->when(
                $stockStatus === 'in_stock',
                fn ($query) =>
                    $query->whereRaw(
                        '(quantity - reserved_quantity) > low_stock_threshold'
                    )
            )
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(
                (int) (
                    $validated['per_page']
                    ?? 25
                )
            );

        return response()->json([
            'data' =>
                $inventories
                    ->getCollection()
                    ->map(
                        fn (Inventory $inventory) =>
                            $this->inventoryData(
                                $inventory
                            )
                    )
                    ->values(),

            'meta' => [
                'current_page' =>
                    $inventories->currentPage(),

                'last_page' =>
                    $inventories->lastPage(),

                'per_page' =>
                    $inventories->perPage(),

                'total' =>
                    $inventories->total(),
            ],
        ]);
    }

    public function show(
        Inventory $inventory
    ): JsonResponse {
        $inventory->load([
            'variant.product',
            'variant.attributeValues.attribute',

            'movements' => fn ($query) =>
                $query
                    ->with([
                        'order',
                        'orderItem',
                        'creator',
                    ])
                    ->orderByDesc(
                        'created_at'
                    )
                    ->limit(100),
        ]);

        return response()->json([
            'data' =>
                $this->inventoryData(
                    $inventory,
                    true
                ),
        ]);
    }

    public function adjust(
        Request $request,
        Inventory $inventory
    ): JsonResponse {
        $validated = $request->validate([
            'quantity_delta' => [
                'required',
                'integer',
                'not_in:0',
            ],

            'note' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        try {
            $inventory =
                $this->inventoryService
                    ->adjustStock(
                        inventory: $inventory,
                        quantityDelta:
                            (int) $validated[
                                'quantity_delta'
                            ],
                        user: $request->user(),
                        note: trim(
                            $validated['note']
                        )
                    );
        } catch (
            InventoryConflictException $exception
        ) {
            throw ValidationException::withMessages([
                'quantity_delta' =>
                    $exception->getMessage(),
            ]);
        }

        $inventory->load([
            'variant.product',
            'variant.attributeValues.attribute',
        ]);

        return response()->json([
            'message' =>
                'Inventory adjusted successfully.',

            'data' =>
                $this->inventoryData(
                    $inventory
                ),
        ]);
    }

    public function updateThreshold(
        Request $request,
        Inventory $inventory
    ): JsonResponse {
        $validated = $request->validate([
            'low_stock_threshold' => [
                'required',
                'integer',
                'min:0',
            ],
        ]);

        $inventory =
            $this->inventoryService
                ->updateLowStockThreshold(
                    inventory: $inventory,
                    threshold:
                        (int) $validated[
                            'low_stock_threshold'
                        ],
                    user: $request->user()
                );

        $inventory->load([
            'variant.product',
            'variant.attributeValues.attribute',
        ]);

        return response()->json([
            'message' =>
                'Low-stock threshold updated successfully.',

            'data' =>
                $this->inventoryData(
                    $inventory
                ),
        ]);
    }

    private function productInventoryData(
        Product $product
    ): array {
        $variants =
            $product
                ->variants
                ->values();

        $inventoryRequiredVariants =
            $variants
                ->filter(
                    fn ($variant) =>
                        $variant->requiresInventory()
                )
                ->values();

        $inventoryVariants =
            $inventoryRequiredVariants
                ->filter(
                    fn ($variant) =>
                        $variant->inventory !== null
                )
                ->values();

        $unconfiguredVariantsCount =
            $inventoryRequiredVariants->count() -
            $inventoryVariants->count();

        $physical =
            $inventoryVariants->sum(
                fn ($variant) =>
                    $variant->inventory->quantity
            );

        $reserved =
            $inventoryVariants->sum(
                fn ($variant) =>
                    $variant->inventory->reserved_quantity
            );

        $available =
            $inventoryVariants->sum(
                fn ($variant) =>
                    $variant->inventory->availableQuantity()
            );

        $lowStockCount =
            $inventoryVariants->filter(
                function ($variant) {
                    $inventory =
                        $variant->inventory;

                    return
                        $inventory->availableQuantity() > 0 &&
                        $inventory->isLowStock();
                }
            )->count();

        $outOfStockCount =
            $inventoryVariants->filter(
                fn ($variant) =>
                    $variant
                        ->inventory
                        ->availableQuantity() <= 0
            )->count();

        $configured =
            $variants->isNotEmpty() &&
            $unconfiguredVariantsCount === 0;

        $status =
            ! $configured
                ? 'not_configured'
                : (
                    $available <= 0
                        ? 'out_of_stock'
                        : (
                            $lowStockCount > 0
                                ? 'low_stock'
                                : 'in_stock'
                        )
                );

        return [
            'id' =>
                $product->id,

            'name' =>
                $product->name,

            'slug' =>
                $product->slug,

            'brand' =>
                $product->brand,

            'variants_count' =>
                $variants->count(),

            'configured_variants_count' =>
                $inventoryVariants->count(),

            'unconfigured_variants_count' =>
                $unconfiguredVariantsCount,

            'quantity' =>
                $physical,

            'reserved_quantity' =>
                $reserved,

            'available_quantity' =>
                $available,

            'low_stock_variants_count' =>
                $lowStockCount,

            'out_of_stock_variants_count' =>
                $outOfStockCount,

            'stock_status' =>
                $status,

            'variants' =>
                $variants
                    ->map(
                        function ($variant) {
                            $inventory =
                                $variant->inventory;

                            $variantStatus =
                                $variant->status;

                            return [
                                'id' =>
                                    $variant->id,

                                'inventory_configured' =>
                                    $inventory !== null,

                                'inventory_id' =>
                                    $inventory?->id,

                                'sku' =>
                                    $variant->sku,

                                'status' =>
                                    $variantStatus
                                        instanceof BackedEnum
                                            ? $variantStatus->value
                                            : $variantStatus,

                                'quantity' =>
                                    $inventory?->quantity,

                                'reserved_quantity' =>
                                    $inventory?->reserved_quantity,

                                'available_quantity' =>
                                    $inventory
                                        ? $inventory->availableQuantity()
                                        : null,

                                'low_stock_threshold' =>
                                    $inventory?->low_stock_threshold,

                                'low_stock' =>
                                    $inventory
                                        ? $inventory->isLowStock()
                                        : false,

                                'attributes' =>
                                    $variant
                                        ->attributeValues
                                        ->map(
                                            fn ($value) => [
                                                'id' =>
                                                    $value->id,

                                                'value' =>
                                                    $value->value,

                                                'attribute' =>
                                                    $value->attribute
                                                        ? [
                                                            'id' =>
                                                                $value
                                                                    ->attribute
                                                                    ->id,

                                                            'name' =>
                                                                $value
                                                                    ->attribute
                                                                    ->name,

                                                            'slug' =>
                                                                $value
                                                                    ->attribute
                                                                    ->slug,
                                                        ]
                                                        : null,
                                            ]
                                        )
                                        ->values(),
                            ];
                        }
                    )
                    ->values(),

            'updated_at' =>
                $inventoryVariants
                    ->map(
                        fn ($variant) =>
                            $variant->inventory->updated_at
                    )
                    ->filter()
                    ->sortDesc()
                    ->first()
                    ?? $product->updated_at,
        ];
    }

    private function inventoryData(
        Inventory $inventory,
        bool $includeMovements = false
    ): array {
        $variant =
            $inventory->variant;

        $product =
            $variant?->product;

        $variantStatus =
            $variant?->status;

        $data = [
            'id' =>
                $inventory->id,

            'product_variant_id' =>
                $inventory->product_variant_id,

            'quantity' =>
                $inventory->quantity,

            'reserved_quantity' =>
                $inventory->reserved_quantity,

            'available_quantity' =>
                $inventory->availableQuantity(),

            'low_stock_threshold' =>
                $inventory->low_stock_threshold,

            'low_stock' =>
                $inventory->isLowStock(),

            'variant' =>
                $variant
                    ? [
                        'id' =>
                            $variant->id,

                        'sku' =>
                            $variant->sku,

                        'price' =>
                            $variant->price !== null
                                ? (float) $variant->price
                                : null,

                        'status' =>
                            $variantStatus
                                instanceof BackedEnum
                                    ? $variantStatus->value
                                    : $variantStatus,

                        'attributes' =>
                            $variant
                                ->attributeValues
                                ->map(
                                    fn ($value) => [
                                        'id' =>
                                            $value->id,

                                        'value' =>
                                            $value->value,

                                        'attribute' =>
                                            $value->attribute
                                                ? [
                                                    'id' =>
                                                        $value
                                                            ->attribute
                                                            ->id,

                                                    'name' =>
                                                        $value
                                                            ->attribute
                                                            ->name,

                                                    'slug' =>
                                                        $value
                                                            ->attribute
                                                            ->slug,
                                                ]
                                                : null,
                                    ]
                                )
                                ->values(),
                    ]
                    : null,

            'product' =>
                $product
                    ? [
                        'id' =>
                            $product->id,

                        'name' =>
                            $product->name,

                        'slug' =>
                            $product->slug,

                        'brand' =>
                            $product->brand,
                    ]
                    : null,

            'created_at' =>
                $inventory->created_at,

            'updated_at' =>
                $inventory->updated_at,
        ];

        if ($includeMovements) {
            $data['movements'] =
                $inventory
                    ->movements
                    ->map(
                        function ($movement) {
                            $type =
                                $movement->type;

                            return [
                                'id' =>
                                    $movement->id,

                                'type' =>
                                    $type
                                        instanceof BackedEnum
                                            ? $type->value
                                            : $type,

                                'quantity_delta' =>
                                    $movement
                                        ->quantity_delta,

                                'reserved_delta' =>
                                    $movement
                                        ->reserved_delta,

                                'note' =>
                                    $movement->note,

                                'order' =>
                                    $movement->order
                                        ? [
                                            'id' =>
                                                $movement
                                                    ->order
                                                    ->id,

                                            'order_number' =>
                                                $movement
                                                    ->order
                                                    ->order_number,
                                        ]
                                        : null,

                                'order_item_id' =>
                                    $movement
                                        ->order_item_id,

                                'created_by' =>
                                    $movement->creator
                                        ? [
                                            'id' =>
                                                $movement
                                                    ->creator
                                                    ->id,

                                            'email' =>
                                                $movement
                                                    ->creator
                                                    ->email,
                                        ]
                                        : null,

                                'created_at' =>
                                    $movement
                                        ->created_at,
                            ];
                        }
                    )
                    ->values();
        }

        return $data;
    }
}
