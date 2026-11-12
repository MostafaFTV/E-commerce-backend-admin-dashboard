<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInventoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_with_view_permission_can_list_inventory(): void
    {
        $admin = $this->createStaff(
            'admin',
            [
                'inventory.view',
            ]
        );

        $inventory = $this->createInventory(
            quantity: 10,
            reservedQuantity: 3,
            threshold: 2
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/inventory');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $inventory->id
            )
            ->assertJsonPath(
                'data.0.quantity',
                10
            )
            ->assertJsonPath(
                'data.0.reserved_quantity',
                3
            )
            ->assertJsonPath(
                'data.0.available_quantity',
                7
            )
            ->assertJsonPath(
                'data.0.low_stock_threshold',
                2
            )
            ->assertJsonPath(
                'data.0.low_stock',
                false
            );
    }

    public function test_accountant_with_view_permission_can_view_inventory(): void
    {
        $accountant = $this->createStaff(
            'accountant',
            [
                'inventory.view',
            ]
        );

        $this->createInventory();

        $this
            ->actingAs(
                $accountant,
                'sanctum'
            )
            ->getJson('/api/admin/inventory')
            ->assertOk();
    }

    public function test_inventory_list_can_search_by_sku(): void
    {
        $admin = $this->createStaff(
            'admin',
            [
                'inventory.view',
            ]
        );

        $matching =
            $this->createInventory(
                sku: 'TKB-SEARCH-001'
            );

        $this->createInventory(
            sku: 'TKB-OTHER-001'
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/inventory?search=TKB-SEARCH-001'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $matching->id
            );
    }

    public function test_admin_can_view_inventory_movement_history(): void
    {
        $admin = $this->createStaff(
            'admin',
            [
                'inventory.view',
            ]
        );

        $inventory =
            $this->createInventory();

        InventoryMovement::create([
            'inventory_id' =>
                $inventory->id,

            'type' =>
                'adjustment',

            'quantity_delta' =>
                2,

            'reserved_delta' =>
                0,

            'note' =>
                'Test adjustment',

            'created_by' =>
                $admin->id,

            'created_at' =>
                now(),
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/admin/inventory/{$inventory->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $inventory->id
            )
            ->assertJsonPath(
                'data.movements.0.type',
                'adjustment'
            )
            ->assertJsonPath(
                'data.movements.0.quantity_delta',
                2
            )
            ->assertJsonPath(
                'data.movements.0.note',
                'Test adjustment'
            );
    }

    public function test_admin_with_manage_permission_can_adjust_stock(): void
    {
        $admin = $this->createStaff(
            'admin',
            [
                'inventory.view',
                'inventory.manage',
            ]
        );

        $inventory =
            $this->createInventory(
                quantity: 10,
                reservedQuantity: 3
            );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                "/api/admin/inventory/{$inventory->id}/adjust",
                [
                    'quantity_delta' => 5,
                    'note' =>
                        'Warehouse stock correction',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.quantity',
                15
            )
            ->assertJsonPath(
                'data.reserved_quantity',
                3
            )
            ->assertJsonPath(
                'data.available_quantity',
                12
            );

        $this->assertDatabaseHas(
            'inventories',
            [
                'id' =>
                    $inventory->id,

                'quantity' =>
                    15,

                'reserved_quantity' =>
                    3,
            ]
        );

        $this->assertDatabaseHas(
            'inventory_movements',
            [
                'inventory_id' =>
                    $inventory->id,

                'type' =>
                    'adjustment',

                'quantity_delta' =>
                    5,

                'reserved_delta' =>
                    0,

                'note' =>
                    'Warehouse stock correction',

                'created_by' =>
                    $admin->id,
            ]
        );
    }

    public function test_manual_adjustment_cannot_reduce_stock_below_reserved_quantity(): void
    {
        $admin = $this->createStaff(
            'admin',
            [
                'inventory.manage',
            ]
        );

        $inventory =
            $this->createInventory(
                quantity: 10,
                reservedQuantity: 4
            );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                "/api/admin/inventory/{$inventory->id}/adjust",
                [
                    'quantity_delta' => -7,
                    'note' =>
                        'Invalid correction',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'quantity_delta',
            ]);

        $this->assertSame(
            10,
            $inventory
                ->fresh()
                ->quantity
        );

        $this->assertSame(
            4,
            $inventory
                ->fresh()
                ->reserved_quantity
        );

        $this->assertDatabaseCount(
            'inventory_movements',
            0
        );
    }

    public function test_admin_can_update_low_stock_threshold_without_creating_movement(): void
    {
        $admin = $this->createStaff(
            'admin',
            [
                'inventory.manage',
            ]
        );

        $inventory =
            $this->createInventory(
                threshold: 5
            );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/inventory/{$inventory->id}/threshold",
                [
                    'low_stock_threshold' =>
                        8,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.low_stock_threshold',
                8
            );

        $this->assertDatabaseHas(
            'inventories',
            [
                'id' =>
                    $inventory->id,

                'low_stock_threshold' =>
                    8,
            ]
        );

        $this->assertDatabaseCount(
            'inventory_movements',
            0
        );
    }

    public function test_view_only_user_cannot_manage_inventory(): void
    {
        $admin = $this->createStaff(
            'admin',
            [
                'inventory.view',
            ]
        );

        $inventory =
            $this->createInventory();

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                "/api/admin/inventory/{$inventory->id}/adjust",
                [
                    'quantity_delta' =>
                        1,

                    'note' =>
                        'Unauthorized change',
                ]
            )
            ->assertForbidden();

        $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/inventory/{$inventory->id}/threshold",
                [
                    'low_stock_threshold' =>
                        10,
                ]
            )
            ->assertForbidden();
    }

    public function test_user_without_view_permission_cannot_view_inventory(): void
    {
        $admin = $this->createStaff(
            'admin',
            []
        );

        $this->createInventory();

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/inventory')
            ->assertForbidden();
    }

    public function test_guest_cannot_access_inventory(): void
    {
        $this
            ->getJson('/api/admin/inventory')
            ->assertUnauthorized();
    }

    public function test_inventory_products_list_includes_unconfigured_products(): void
    {
        $admin = $this->createStaff(
            'admin',
            [
                'inventory.view',
            ]
        );

        $configured =
            $this->createInventory();

        $unconfiguredProduct =
            Product::factory()->create([
                'name' => [
                    'en' =>
                        'Unconfigured Product',

                    'fa' =>
                        'محصول بدون موجودی',
                ],

                'status' =>
                    ProductStatus::ACTIVE,
            ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/inventory/products'
            );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' =>
                    $configured
                        ->variant
                        ->product_id,

                'stock_status' =>
                    'in_stock',
            ])
            ->assertJsonFragment([
                'id' =>
                    $unconfiguredProduct->id,

                'stock_status' =>
                    'not_configured',
            ]);
    }

    public function test_inventory_product_detail_exposes_unconfigured_variant(): void
    {
        $admin = $this->createStaff(
            'admin',
            [
                'inventory.view',
            ]
        );

        $product =
            Product::factory()->create([
                'name' => [
                    'en' =>
                        'Product With Unconfigured Variant',

                    'fa' =>
                        'محصول با واریانت بدون موجودی',
                ],

                'status' =>
                    ProductStatus::ACTIVE,
            ]);

        $variant =
            ProductVariant::factory()->create([
                'product_id' =>
                    $product->id,

                'sku' =>
                    'TKB-NO-INV-001',

                'status' =>
                    VariantStatus::ACTIVE,
            ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/admin/inventory/products/{$product->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $product->id
            )
            ->assertJsonPath(
                'data.stock_status',
                'not_configured'
            )
            ->assertJsonPath(
                'data.variants.0.id',
                $variant->id
            )
            ->assertJsonPath(
                'data.variants.0.inventory_configured',
                false
            )
            ->assertJsonPath(
                'data.variants.0.inventory_id',
                null
            );
    }

    public function test_non_inventory_variants_do_not_make_product_not_configured(): void
    {
        $admin = $this->createStaff(
            'admin',
            [
                'inventory.view',
            ]
        );

        $product =
            Product::factory()->create([
                'name' => [
                    'en' =>
                        'Non Inventory Variant Product',

                    'fa' =>
                        'محصول با واریانت بدون نیاز به موجودی',
                ],

                'status' =>
                    ProductStatus::ACTIVE,
            ]);

        $madeToOrder =
            ProductVariant::factory()->create([
                'product_id' =>
                    $product->id,

                'sku' =>
                    'TKB-MTO-NO-INV-001',

                'status' =>
                    VariantStatus::MADE_TO_ORDER,
            ]);

        $sold =
            ProductVariant::factory()->create([
                'product_id' =>
                    $product->id,

                'sku' =>
                    'TKB-SOLD-NO-INV-001',

                'status' =>
                    VariantStatus::SOLD,
            ]);

        $inactive =
            ProductVariant::factory()->create([
                'product_id' =>
                    $product->id,

                'sku' =>
                    'TKB-INACTIVE-NO-INV-001',

                'status' =>
                    VariantStatus::INACTIVE,
            ]);

        $detailResponse = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/admin/inventory/products/{$product->id}"
            );

        $detailResponse
            ->assertOk()
            ->assertJsonPath(
                'data.stock_status',
                'out_of_stock'
            )
            ->assertJsonPath(
                'data.variants_count',
                3
            )
            ->assertJsonPath(
                'data.configured_variants_count',
                0
            )
            ->assertJsonPath(
                'data.unconfigured_variants_count',
                0
            );

        $variants =
            collect(
                $detailResponse->json(
                    'data.variants'
                )
            )->keyBy('id');

        foreach (
            [
                $madeToOrder,
                $sold,
                $inactive,
            ] as $variant
        ) {
            $this->assertFalse(
                $variants[$variant->id][
                    'inventory_configured'
                ]
            );

            $this->assertNull(
                $variants[$variant->id][
                    'inventory_id'
                ]
            );
        }

        $notConfiguredResponse = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/inventory/products?stock_status=not_configured'
            );

        $notConfiguredResponse
            ->assertOk()
            ->assertJsonCount(
                0,
                'data'
            );
    }
    public function test_active_inventory_stock_statuses_follow_available_quantity(): void
    {
        $admin = $this->createStaff(
            'admin',
            [
                'inventory.view',
            ]
        );

        $outOfStock =
            $this->createInventory(
                quantity: 0,
                reservedQuantity: 0,
                threshold: 5,
                sku: 'TKB-STATUS-OOS-001'
            );

        $lowStock =
            $this->createInventory(
                quantity: 5,
                reservedQuantity: 2,
                threshold: 5,
                sku: 'TKB-STATUS-LOW-001'
            );

        $inStock =
            $this->createInventory(
                quantity: 10,
                reservedQuantity: 2,
                threshold: 5,
                sku: 'TKB-STATUS-IN-001'
            );

        $outOfStockProductId =
            $outOfStock
                ->variant
                ->product_id;

        $lowStockProductId =
            $lowStock
                ->variant
                ->product_id;

        $inStockProductId =
            $inStock
                ->variant
                ->product_id;

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/admin/inventory/products/{$outOfStockProductId}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.stock_status',
                'out_of_stock'
            )
            ->assertJsonPath(
                'data.available_quantity',
                0
            )
            ->assertJsonPath(
                'data.configured_variants_count',
                1
            )
            ->assertJsonPath(
                'data.unconfigured_variants_count',
                0
            );

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/admin/inventory/products/{$lowStockProductId}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.stock_status',
                'low_stock'
            )
            ->assertJsonPath(
                'data.available_quantity',
                3
            );

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/admin/inventory/products/{$inStockProductId}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.stock_status',
                'in_stock'
            )
            ->assertJsonPath(
                'data.available_quantity',
                8
            );

        $outOfStockResponse = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/inventory/products?stock_status=out_of_stock'
            );

        $outOfStockResponse
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $outOfStockProductId
            );

        $lowStockResponse = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/inventory/products?stock_status=low_stock'
            );

        $lowStockResponse
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $lowStockProductId
            );

        $inStockResponse = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/inventory/products?stock_status=in_stock'
            );

        $inStockResponse
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $inStockProductId
            );
    }
    private function createInventory(
        int $quantity = 10,
        int $reservedQuantity = 0,
        int $threshold = 5,
        ?string $sku = null
    ): Inventory {
        $product =
            Product::factory()->create([
                'name' => [
                    'en' =>
                        'Inventory Test Product',

                    'fa' =>
                        'محصول تست موجودی',
                ],

                'brand' =>
                    'Test Brand',

                'status' =>
                    ProductStatus::ACTIVE,
            ]);

        $variant =
            ProductVariant::factory()->create([
                'product_id' =>
                    $product->id,

                'sku' =>
                    $sku
                    ?? 'TKB-INV-' .
                        fake()->unique()->numerify(
                            '#####'
                        ),

                'status' =>
                    VariantStatus::ACTIVE,
            ]);

        return Inventory::create([
            'product_variant_id' =>
                $variant->id,

            'quantity' =>
                $quantity,

            'reserved_quantity' =>
                $reservedQuantity,

            'low_stock_threshold' =>
                $threshold,
        ]);
    }

    private function createStaff(
        string $roleSlug,
        array $permissionSlugs
    ): User {
        $role = Role::firstOrCreate(
            [
                'slug' =>
                    $roleSlug,
            ],
            [
                'name' =>
                    ucwords(
                        str_replace(
                            '_',
                            ' ',
                            $roleSlug
                        )
                    ),

                'description' =>
                    'Test inventory role',
            ]
        );

        $permissions =
            collect(
                $permissionSlugs
            )->map(
                function (
                    string $permissionSlug
                ) {
                    return Permission::firstOrCreate(
                        [
                            'slug' =>
                                $permissionSlug,
                        ],
                        [
                            'name' =>
                                $permissionSlug,

                            'description' =>
                                'Test inventory permission',
                        ]
                    );
                }
            );

        $role
            ->permissions()
            ->sync(
                $permissions
                    ->pluck('id')
                    ->all()
            );

        $user =
            User::factory()->create([
                'email' =>
                    $roleSlug .
                    '-' .
                    fake()
                        ->unique()
                        ->numerify(
                            '#####'
                        ) .
                    '@example.com',

                'status' =>
                    'active',
            ]);

        $user
            ->roles()
            ->sync([
                $role->id,
            ]);

        return $user->fresh([
            'roles.permissions',
        ]);
    }
}