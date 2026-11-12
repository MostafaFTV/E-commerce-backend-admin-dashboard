<?php

namespace Tests\Feature;

use App\Enums\MenuItemType;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMenuControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Menu Index
    |--------------------------------------------------------------------------
    */

    public function test_user_with_menus_permission_can_view_menus(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu([
            'name' => 'Main Menu',
            'slug' => 'main-menu',
            'location' => 'header',
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->getJson(
                '/api/admin/menus'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $menu->id
            )
            ->assertJsonPath(
                'data.0.name',
                'Main Menu'
            )
            ->assertJsonPath(
                'data.0.slug',
                'main-menu'
            )
            ->assertJsonPath(
                'data.0.location',
                'header'
            )
            ->assertJsonPath(
                'data.0.is_active',
                true
            )
            ->assertJsonPath(
                'data.0.items_count',
                0
            );
    }

    public function test_admin_can_search_menus(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $matchingMenu = $this->createMenu([
            'name' => 'Header Menu',
            'slug' => 'header-menu',
            'location' => 'header',
        ]);

        $this->createMenu([
            'name' => 'Footer Menu',
            'slug' => 'footer-menu',
            'location' => 'footer',
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->getJson(
                '/api/admin/menus?search=Header'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $matchingMenu->id
            );
    }

    public function test_admin_can_filter_menus_by_active_status(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $activeMenu = $this->createMenu([
            'name' => 'Active Menu',
            'slug' => 'active-menu',
            'is_active' => true,
        ]);

        $this->createMenu([
            'name' => 'Inactive Menu',
            'slug' => 'inactive-menu',
            'is_active' => false,
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->getJson(
                '/api/admin/menus?is_active=1'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $activeMenu->id
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Menu Show
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_view_menu_with_items(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $item = $menu->items()->create([
            'title' => [
                'en' => 'About Us',
                'fa' => 'درباره ما',
            ],
            'type' =>
                MenuItemType::PAGE,
            'page_id' =>
                $this->createPage()->id,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->getJson(
                "/api/admin/menus/{$menu->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $menu->id
            )
            ->assertJsonCount(
                1,
                'data.items'
            )
            ->assertJsonPath(
                'data.items.0.id',
                $item->id
            )
            ->assertJsonPath(
                'data.items.0.title',
                'About Us'
            )
            ->assertJsonPath(
                'data.items.0.type',
                'page'
            )
            ->assertJsonPath(
                'data.items.0.page_id',
                $item->page_id
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Menu Store
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_menu(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                '/api/admin/menus',
                [
                    'name' => 'Main Menu',
                    'slug' => 'main-menu',
                    'location' => 'header',
                    'is_active' => true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Menu created successfully.'
            )
            ->assertJsonPath(
                'data.name',
                'Main Menu'
            )
            ->assertJsonPath(
                'data.slug',
                'main-menu'
            )
            ->assertJsonPath(
                'data.location',
                'header'
            )
            ->assertJsonPath(
                'data.is_active',
                true
            );

        $this->assertDatabaseHas(
            'menus',
            [
                'name' => 'Main Menu',
                'slug' => 'main-menu',
                'location' => 'header',
                'is_active' => 1,
            ]
        );
    }

    public function test_duplicate_menu_slug_is_rejected(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $this->createMenu([
            'slug' => 'main-menu',
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                '/api/admin/menus',
                [
                    'name' => 'Another Menu',
                    'slug' => 'main-menu',
                    'location' => 'footer',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);
    }

    public function test_invalid_menu_slug_is_rejected(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                '/api/admin/menus',
                [
                    'name' => 'Main Menu',
                    'slug' => 'Main Menu',
                    'location' => 'header',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Menu Mass Assignment
    |--------------------------------------------------------------------------
    */

    public function test_menu_store_ignores_unvalidated_sensitive_fields(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                '/api/admin/menus',
                [
                    'name' => 'Secure Menu',
                    'slug' => 'secure-menu',
                    'location' => 'header',
                    'is_active' => true,
                    'id' => 9999,
                    'created_at' =>
                        '2000-01-01 00:00:00',
                    'updated_at' =>
                        '2000-01-01 00:00:00',
                ]
            );

        $response->assertCreated();

        $menu = Menu::query()
            ->latest('id')
            ->firstOrFail();

        $this->assertNotSame(
            9999,
            $menu->id
        );

        $this->assertNotSame(
            '2000-01-01 00:00:00',
            $menu->created_at
                ?->format(
                    'Y-m-d H:i:s'
                )
        );
    }

    public function test_menu_update_ignores_unvalidated_sensitive_fields(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu([
            'name' => 'Original Menu',
            'slug' => 'original-menu',
            'location' => 'header',
            'is_active' => true,
        ]);

        $originalCreatedAt =
            $menu->created_at
                ?->format(
                    'Y-m-d H:i:s'
                );

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->patchJson(
                "/api/admin/menus/{$menu->id}",
                [
                    'name' => 'Updated Menu',
                    'id' => 9999,
                    'created_at' =>
                        '2000-01-01 00:00:00',
                    'updated_at' =>
                        '2000-01-01 00:00:00',
                ]
            )
            ->assertOk();

        $updated = $menu->fresh();

        $this->assertSame(
            $menu->id,
            $updated->id
        );

        $this->assertSame(
            $originalCreatedAt,
            $updated->created_at
                ?->format(
                    'Y-m-d H:i:s'
                )
        );

        $this->assertSame(
            'Updated Menu',
            $updated->name
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Menu Update
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_update_menu(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu([
            'name' => 'Old Menu',
            'slug' => 'old-menu',
            'location' => 'header',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->patchJson(
                "/api/admin/menus/{$menu->id}",
                [
                    'name' => 'New Menu',
                    'slug' => 'new-menu',
                    'location' => 'footer',
                    'is_active' => false,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Menu updated successfully.'
            )
            ->assertJsonPath(
                'data.name',
                'New Menu'
            )
            ->assertJsonPath(
                'data.slug',
                'new-menu'
            )
            ->assertJsonPath(
                'data.location',
                'footer'
            )
            ->assertJsonPath(
                'data.is_active',
                false
            );

        $this->assertDatabaseHas(
            'menus',
            [
                'id' => $menu->id,
                'name' => 'New Menu',
                'slug' => 'new-menu',
                'location' => 'footer',
                'is_active' => 0,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Menu Delete
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_delete_empty_menu(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->deleteJson(
                "/api/admin/menus/{$menu->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Menu deleted successfully.'
            );

        $this->assertDatabaseMissing(
            'menus',
            [
                'id' => $menu->id,
            ]
        );
    }

    public function test_menu_cannot_be_deleted_while_it_has_items(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $menu->items()->create([
            'title' => [
                'en' => 'Home',
                'fa' => 'خانه',
            ],
            'type' =>
                MenuItemType::URL,
            'url' => '/',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->deleteJson(
                "/api/admin/menus/{$menu->id}"
            );

        $response
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'This menu cannot be deleted while it contains menu items.'
            );

        $this->assertDatabaseHas(
            'menus',
            [
                'id' => $menu->id,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Menu Item - URL
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_url_menu_item(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' => 'Home',
                        'fa' => 'خانه',
                    ],
                    'type' => 'url',
                    'url' => '/',
                    'sort_order' => 1,
                    'is_active' => true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.title',
                'Home'
            )
            ->assertJsonPath(
                'data.type',
                'url'
            )
            ->assertJsonPath(
                'data.url',
                '/'
            )
            ->assertJsonPath(
                'data.locale',
                'en'
            )
            ->assertJsonPath(
                'data.direction',
                'ltr'
            );

        $item = MenuItem::query()
            ->where(
                'menu_id',
                $menu->id
            )
            ->firstOrFail();

        $this->assertSame(
            [
                'en' => 'Home',
                'fa' => 'خانه',
            ],
            json_decode(
                $item->getRawOriginal(
                    'title'
                ),
                true
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Menu Item - Page
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_page_menu_item(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $page = $this->createPage();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' => 'About',
                        'fa' => 'درباره ما',
                    ],
                    'type' => 'page',
                    'page_id' => $page->id,
                    'sort_order' => 2,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.title',
                'About'
            )
            ->assertJsonPath(
                'data.type',
                'page'
            )
            ->assertJsonPath(
                'data.page_id',
                $page->id
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Menu Item - Category
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_category_menu_item(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $category = $this->createCategory();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' => 'Cookware',
                        'fa' => 'ظروف آشپزی',
                    ],
                    'type' => 'category',
                    'category_id' =>
                        $category->id,
                    'sort_order' => 3,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.title',
                'Cookware'
            )
            ->assertJsonPath(
                'data.type',
                'category'
            )
            ->assertJsonPath(
                'data.category_id',
                $category->id
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Menu Item - Collection
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_collection_menu_item(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $collection = $this->createCollection();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' => 'Summer Collection',
                        'fa' => 'کالکشن تابستانی',
                    ],
                    'type' => 'collection',
                    'collection_id' =>
                        $collection->id,
                    'sort_order' => 4,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.title',
                'Summer Collection'
            )
            ->assertJsonPath(
                'data.type',
                'collection'
            )
            ->assertJsonPath(
                'data.collection_id',
                $collection->id
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Menu Item - Product
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_product_menu_item(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $product = $this->createProduct();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' => 'Featured Product',
                        'fa' => 'محصول ویژه',
                    ],
                    'type' => 'product',
                    'product_id' =>
                        $product->id,
                    'sort_order' => 5,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.title',
                'Featured Product'
            )
            ->assertJsonPath(
                'data.type',
                'product'
            )
            ->assertJsonPath(
                'data.product_id',
                $product->id
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Menu Item - Target Validation
    |--------------------------------------------------------------------------
    */

    public function test_url_menu_item_requires_url(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' => 'Home',
                        'fa' => 'خانه',
                    ],
                    'type' => 'url',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'url',
            ]);
    }

    public function test_page_menu_item_requires_page_id(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' => 'About',
                        'fa' => 'درباره ما',
                    ],
                    'type' => 'page',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page_id',
            ]);
    }

    public function test_category_menu_item_requires_category_id(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' => 'Cookware',
                        'fa' => 'ظروف آشپزی',
                    ],
                    'type' => 'category',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'category_id',
            ]);
    }

    public function test_collection_menu_item_requires_collection_id(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' => 'Collection',
                        'fa' => 'کالکشن',
                    ],
                    'type' => 'collection',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'collection_id',
            ]);
    }

    public function test_product_menu_item_requires_product_id(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' => 'Product',
                        'fa' => 'محصول',
                    ],
                    'type' => 'product',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'product_id',
            ]);
    }

    public function test_menu_item_rejects_irrelevant_target_field(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $page = $this->createPage();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' => 'About',
                        'fa' => 'درباره ما',
                    ],
                    'type' => 'page',
                    'page_id' => $page->id,
                    'url' => '/about',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'url',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Parent / Child
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_child_menu_item(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $parent = $menu->items()->create([
            'title' => [
                'en' => 'Kitchen',
                'fa' => 'آشپزخانه',
            ],
            'type' =>
                MenuItemType::URL,
            'url' => '/kitchen',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'parent_id' =>
                        $parent->id,

                    'title' => [
                        'en' => 'Cookware',
                        'fa' => 'ظروف آشپزی',
                    ],

                    'type' => 'url',

                    'url' =>
                        '/kitchen/cookware',

                    'sort_order' => 2,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.parent_id',
                $parent->id
            );

        $this->assertDatabaseHas(
            'menu_items',
            [
                'id' =>
                    $response->json(
                        'data.id'
                    ),

                'menu_id' =>
                    $menu->id,

                'parent_id' =>
                    $parent->id,
            ]
        );
    }

    public function test_parent_item_must_belong_to_same_menu(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menuOne = $this->createMenu([
            'slug' => 'menu-one',
        ]);

        $menuTwo = $this->createMenu([
            'slug' => 'menu-two',
        ]);

        $parent = $menuTwo->items()->create([
            'title' => [
                'en' => 'Other Menu',
                'fa' => 'منوی دیگر',
            ],
            'type' =>
                MenuItemType::URL,
            'url' => '/other',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menuOne->id}/items",
                [
                    'parent_id' =>
                        $parent->id,

                    'title' => [
                        'en' =>
                            'Invalid Child',
                        'fa' =>
                            'فرزند نامعتبر',
                    ],

                    'type' => 'url',

                    'url' =>
                        '/invalid',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'parent_id',
            ]);
    }

    public function test_menu_item_cannot_be_its_own_parent(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $item = $menu->items()->create([
            'title' => [
                'en' => 'Kitchen',
                'fa' => 'آشپزخانه',
            ],
            'type' =>
                MenuItemType::URL,
            'url' => '/kitchen',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->patchJson(
                "/api/admin/menus/{$menu->id}/items/{$item->id}",
                [
                    'parent_id' =>
                        $item->id,
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'parent_id',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Menu Item Show / Update
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_view_menu_item(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $item = $menu->items()->create([
            'title' => [
                'en' => 'Home',
                'fa' => 'خانه',
            ],
            'type' =>
                MenuItemType::URL,
            'url' => '/',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->getJson(
                "/api/admin/menus/{$menu->id}/items/{$item->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $item->id
            )
            ->assertJsonPath(
                'data.menu_id',
                $menu->id
            )
            ->assertJsonPath(
                'data.title',
                'Home'
            )
            ->assertJsonPath(
                'data.type',
                'url'
            )
            ->assertJsonPath(
                'data.locale',
                'en'
            )
            ->assertJsonPath(
                'data.direction',
                'ltr'
            );
    }

    public function test_admin_cannot_access_item_from_another_menu(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menuOne = $this->createMenu([
            'slug' => 'menu-one',
        ]);

        $menuTwo = $this->createMenu([
            'slug' => 'menu-two',
        ]);

        $item = $menuTwo->items()->create([
            'title' => [
                'en' => 'Other',
                'fa' => 'دیگری',
            ],
            'type' =>
                MenuItemType::URL,
            'url' => '/other',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->getJson(
                "/api/admin/menus/{$menuOne->id}/items/{$item->id}"
            );

        $response->assertNotFound();
    }

    public function test_admin_can_update_menu_item(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $item = $menu->items()->create([
            'title' => [
                'en' => 'Old Title',
                'fa' => 'عنوان قدیمی',
            ],
            'type' =>
                MenuItemType::URL,
            'url' => '/old',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->patchJson(
                "/api/admin/menus/{$menu->id}/items/{$item->id}",
                [
                    'title' => [
                        'en' => 'New Title',
                        'fa' => 'عنوان جدید',
                    ],
                    'url' => '/new',
                    'sort_order' => 5,
                    'is_active' => false,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Menu item updated successfully.'
            )
            ->assertJsonPath(
                'data.title',
                'New Title'
            )
            ->assertJsonPath(
                'data.url',
                '/new'
            )
            ->assertJsonPath(
                'data.sort_order',
                5
            )
            ->assertJsonPath(
                'data.is_active',
                false
            );

        $item->refresh();

        $this->assertSame(
            [
                'en' => 'New Title',
                'fa' => 'عنوان جدید',
            ],
            json_decode(
                $item->getRawOriginal(
                    'title'
                ),
                true
            )
        );
    }

    public function test_admin_cannot_update_item_from_another_menu(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menuOne = $this->createMenu([
            'slug' => 'menu-one',
        ]);

        $menuTwo = $this->createMenu([
            'slug' => 'menu-two',
        ]);

        $item = $menuTwo->items()->create([
            'title' => [
                'en' =>
                    'Protected Item',
                'fa' =>
                    'آیتم محافظت‌شده',
            ],
            'type' =>
                MenuItemType::URL,
            'url' => '/protected',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->patchJson(
                "/api/admin/menus/{$menuOne->id}/items/{$item->id}",
                [
                    'title' => [
                        'en' => 'Hacked Item',
                        'fa' => 'آیتم هک‌شده',
                    ],
                    'url' => '/hacked',
                    'sort_order' => 99,
                    'is_active' => false,
                ]
            );

        $response->assertNotFound();

        $item->refresh();

        $this->assertSame(
            [
                'en' =>
                    'Protected Item',

                'fa' =>
                    'آیتم محافظت‌شده',
            ],
            json_decode(
                $item->getRawOriginal(
                    'title'
                ),
                true
            )
        );

        $this->assertSame(
            '/protected',
            $item->url
        );

        $this->assertSame(
            1,
            $item->sort_order
        );

        $this->assertTrue(
            (bool) $item->is_active
        );
    }

    public function test_admin_can_partially_update_only_persian_title(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $item = $menu->items()->create([
            'title' => [
                'en' => 'Kitchen',
                'fa' => 'آشپزخانه',
            ],
            'type' =>
                MenuItemType::URL,
            'url' => '/kitchen',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->patchJson(
                "/api/admin/menus/{$menu->id}/items/{$item->id}",
                [
                    'title' => [
                        'fa' =>
                            'آشپزخانه جدید',
                    ],
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.title',
                'Kitchen'
            );

        $item->refresh();

        $this->assertSame(
            [
                'en' => 'Kitchen',
                'fa' => 'آشپزخانه جدید',
            ],
            json_decode(
                $item->getRawOriginal(
                    'title'
                ),
                true
            )
        );
    }

    public function test_admin_can_update_persian_title(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $item = $menu->items()->create([
            'title' => [
                'en' => 'Home',
                'fa' => 'خانه',
            ],
            'type' =>
                MenuItemType::URL,
            'url' => '/',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $response = $this
            ->withHeader(
                'Accept-Language',
                'fa'
            )
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->patchJson(
                "/api/admin/menus/{$menu->id}/items/{$item->id}",
                [
                    'title' => [
                        'fa' =>
                            'صفحه اصلی',
                    ],
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.title',
                'صفحه اصلی'
            )
            ->assertJsonPath(
                'data.locale',
                'fa'
            )
            ->assertJsonPath(
                'data.direction',
                'rtl'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Menu Item Mass Assignment
    |--------------------------------------------------------------------------
    */

    public function test_menu_item_store_ignores_unvalidated_sensitive_fields(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' => 'Safe Item',
                        'fa' => 'آیتم امن',
                    ],

                    'type' =>
                        'url',

                    'url' =>
                        '/safe',

                    'sort_order' =>
                        1,

                    'is_active' =>
                        true,

                    'id' =>
                        9999,

                    'menu_id' =>
                        9999,

                    'created_at' =>
                        '2000-01-01 00:00:00',

                    'updated_at' =>
                        '2000-01-01 00:00:00',
                ]
            );

        $response->assertCreated();

        $item = MenuItem::query()
            ->latest('id')
            ->firstOrFail();

        $this->assertNotSame(
            9999,
            $item->id
        );

        $this->assertSame(
            $menu->id,
            $item->menu_id
        );

        $this->assertNotSame(
            '2000-01-01 00:00:00',
            $item->created_at
                ?->format(
                    'Y-m-d H:i:s'
                )
        );
    }

    public function test_menu_item_update_ignores_unvalidated_sensitive_fields(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $item = $menu->items()->create([
            'title' => [
                'en' => 'Original',
                'fa' => 'اصلی',
            ],
            'type' =>
                MenuItemType::URL,
            'url' => '/original',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $originalCreatedAt =
            $item->created_at
                ?->format(
                    'Y-m-d H:i:s'
                );

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->patchJson(
                "/api/admin/menus/{$menu->id}/items/{$item->id}",
                [
                    'title' => [
                        'en' => 'Updated',
                        'fa' => 'به‌روزشده',
                    ],

                    'url' =>
                        '/updated',

                    'menu_id' =>
                        9999,

                    'id' =>
                        9999,

                    'created_at' =>
                        '2000-01-01 00:00:00',

                    'updated_at' =>
                        '2000-01-01 00:00:00',
                ]
            )
            ->assertOk();

        $updated = $item->fresh();

        $this->assertSame(
            $item->id,
            $updated->id
        );

        $this->assertSame(
            $menu->id,
            $updated->menu_id
        );

        $this->assertSame(
            $originalCreatedAt,
            $updated->created_at
                ?->format(
                    'Y-m-d H:i:s'
                )
        );

        $this->assertSame(
            '/updated',
            $updated->url
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Menu Item XSS
    |--------------------------------------------------------------------------
    */

    public function test_menu_item_title_removes_script_tags(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' =>
                            '<script>alert("xss")</script>Home',

                        'fa' =>
                            'خانه',
                    ],

                    'type' =>
                        'url',

                    'url' =>
                        '/',

                    'is_active' =>
                        true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.title',
                'Home'
            );

        $item = MenuItem::query()
            ->firstOrFail();

        $translations = json_decode(
            $item->getRawOriginal(
                'title'
            ),
            true
        );

        $this->assertSame(
            'Home',
            $translations['en']
        );
    }

    public function test_menu_item_title_removes_event_handler_markup(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' =>
                            '<img src="x" onerror="alert(1)">Home',

                        'fa' =>
                            'خانه',
                    ],

                    'type' =>
                        'url',

                    'url' =>
                        '/',

                    'is_active' =>
                        true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.title',
                'Home'
            );
    }

    public function test_menu_item_update_sanitizes_title(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $item = $menu->items()->create([
            'title' => [
                'en' => 'Original',
                'fa' => 'اصلی',
            ],

            'type' =>
                MenuItemType::URL,

            'url' =>
                '/',

            'sort_order' =>
                1,

            'is_active' =>
                true,
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->patchJson(
                "/api/admin/menus/{$menu->id}/items/{$item->id}",
                [
                    'title' => [
                        'en' =>
                            '<script>alert(1)</script>Updated',
                    ],
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.title',
                'Updated'
            );

        $updated = $item->fresh();

        $translations = json_decode(
            $updated->getRawOriginal(
                'title'
            ),
            true
        );

        $this->assertSame(
            'Updated',
            $translations['en']
        );

        $this->assertSame(
            'اصلی',
            $translations['fa']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Menu Item URL Security
    |--------------------------------------------------------------------------
    */

    public function test_url_menu_item_rejects_javascript_url(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' =>
                            'Unsafe URL',

                        'fa' =>
                            'آدرس ناامن',
                    ],

                    'type' =>
                        'url',

                    'url' =>
                        'javascript:alert(1)',

                    'is_active' =>
                        true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'url',
            ]);

        $this->assertDatabaseCount(
            'menu_items',
            0
        );
    }

    public function test_url_menu_item_rejects_case_insensitive_javascript_url(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' =>
                            'Unsafe URL',

                        'fa' =>
                            'آدرس ناامن',
                    ],

                    'type' =>
                        'url',

                    'url' =>
                        'JaVaScRiPt:alert(1)',

                    'is_active' =>
                        true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'url',
            ]);
    }

    public function test_url_menu_item_rejects_data_url(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' =>
                            'Unsafe URL',

                        'fa' =>
                            'آدرس ناامن',
                    ],

                    'type' =>
                        'url',

                    'url' =>
                        'data:text/html,<script>alert(1)</script>',

                    'is_active' =>
                        true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'url',
            ]);
    }

    public function test_url_menu_item_rejects_protocol_relative_url(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' =>
                            'Unsafe URL',

                        'fa' =>
                            'آدرس ناامن',
                    ],

                    'type' =>
                        'url',

                    'url' =>
                        '//evil.example.com/phishing',

                    'is_active' =>
                        true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'url',
            ]);
    }

    public function test_url_menu_item_rejects_url_with_dangerous_characters(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' =>
                            'Unsafe URL',

                        'fa' =>
                            'آدرس ناامن',
                    ],

                    'type' =>
                        'url',

                    'url' =>
                        '/products/<script>',

                    'is_active' =>
                        true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'url',
            ]);
    }

    public function test_url_menu_item_accepts_internal_url(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' =>
                            'Internal',

                        'fa' =>
                            'داخلی',
                    ],

                    'type' =>
                        'url',

                    'url' =>
                        '/collections/summer',

                    'is_active' =>
                        true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.url',
                '/collections/summer'
            );
    }

    public function test_url_menu_item_accepts_internal_url_with_query_string(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' =>
                            'Internal',

                        'fa' =>
                            'داخلی',
                    ],

                    'type' =>
                        'url',

                    'url' =>
                        '/collections/summer?sort=popular',

                    'is_active' =>
                        true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.url',
                '/collections/summer?sort=popular'
            );
    }

    public function test_url_menu_item_accepts_https_url(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' =>
                            'External',

                        'fa' =>
                            'خارجی',
                    ],

                    'type' =>
                        'url',

                    'url' =>
                        'https://example.com/products',

                    'is_active' =>
                        true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.url',
                'https://example.com/products'
            );
    }

    public function test_url_menu_item_accepts_http_url(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' =>
                            'External',

                        'fa' =>
                            'خارجی',
                    ],

                    'type' =>
                        'url',

                    'url' =>
                        'http://example.com/products',

                    'is_active' =>
                        true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.url',
                'http://example.com/products'
            );
    }

    public function test_url_menu_item_rejects_unsupported_scheme(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' =>
                            'Unsafe',

                        'fa' =>
                            'ناامن',
                    ],

                    'type' =>
                        'url',

                    'url' =>
                        'ftp://example.com/file',

                    'is_active' =>
                        true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'url',
            ]);
    }

    public function test_url_menu_item_rejects_relative_non_path_value(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' =>
                            'Unsafe',

                        'fa' =>
                            'ناامن',
                    ],

                    'type' =>
                        'url',

                    'url' =>
                        'javascript-test',

                    'is_active' =>
                        true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'url',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update URL Security
    |--------------------------------------------------------------------------
    */

    public function test_menu_item_update_rejects_javascript_url(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $item = $menu->items()->create([
            'title' => [
                'en' =>
                    'Original',

                'fa' =>
                    'اصلی',
            ],

            'type' =>
                MenuItemType::URL,

            'url' =>
                '/original',

            'sort_order' =>
                1,

            'is_active' =>
                true,
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->patchJson(
                "/api/admin/menus/{$menu->id}/items/{$item->id}",
                [
                    'url' =>
                        'javascript:alert(1)',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'url',
            ]);

        $item->refresh();

        $this->assertSame(
            '/original',
            $item->url
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Menu Item Delete
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_delete_menu_item_without_children(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $item = $menu->items()->create([
            'title' => [
                'en' => 'Home',
                'fa' => 'خانه',
            ],
            'type' =>
                MenuItemType::URL,
            'url' => '/',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->deleteJson(
                "/api/admin/menus/{$menu->id}/items/{$item->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Menu item deleted successfully.'
            );

        $this->assertDatabaseMissing(
            'menu_items',
            [
                'id' =>
                    $item->id,
            ]
        );
    }

    public function test_admin_cannot_delete_item_from_another_menu(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menuOne = $this->createMenu([
            'slug' =>
                'menu-one',
        ]);

        $menuTwo = $this->createMenu([
            'slug' =>
                'menu-two',
        ]);

        $item = $menuTwo->items()->create([
            'title' => [
                'en' =>
                    'Protected Item',

                'fa' =>
                    'آیتم محافظت‌شده',
            ],

            'type' =>
                MenuItemType::URL,

            'url' =>
                '/protected',

            'sort_order' =>
                1,

            'is_active' =>
                true,
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->deleteJson(
                "/api/admin/menus/{$menuOne->id}/items/{$item->id}"
            );

        $response->assertNotFound();

        $this->assertDatabaseHas(
            'menu_items',
            [
                'id' =>
                    $item->id,

                'menu_id' =>
                    $menuTwo->id,

                'url' =>
                    '/protected',
            ]
        );
    }

    public function test_menu_item_with_children_cannot_be_deleted(): void
    {
        $admin = $this->createUserWithPermission(
            'menus.manage'
        );

        $menu = $this->createMenu();

        $parent = $menu->items()->create([
            'title' => [
                'en' =>
                    'Kitchen',

                'fa' =>
                    'آشپزخانه',
            ],

            'type' =>
                MenuItemType::URL,

            'url' =>
                '/kitchen',

            'sort_order' =>
                0,

            'is_active' =>
                true,
        ]);

        $menu->items()->create([
            'parent_id' =>
                $parent->id,

            'title' => [
                'en' =>
                    'Cookware',

                'fa' =>
                    'ظروف آشپزی',
            ],

            'type' =>
                MenuItemType::URL,

            'url' =>
                '/kitchen/cookware',

            'sort_order' =>
                1,

            'is_active' =>
                true,
        ]);

        $response = $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->deleteJson(
                "/api/admin/menus/{$menu->id}/items/{$parent->id}"
            );

        $response
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'This menu item cannot be deleted while it contains child items.'
            );

        $this->assertDatabaseHas(
            'menu_items',
            [
                'id' =>
                    $parent->id,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_user_without_menus_permission_cannot_view_menus(): void
    {
        $user = User::factory()->create([
            'status' =>
                UserStatus::ACTIVE,
        ]);

        $response = $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->getJson(
                '/api/admin/menus'
            );

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Forbidden: Missing permission'
            );
    }

    public function test_user_without_menus_permission_cannot_create_menu(): void
    {
        $user = User::factory()->create([
            'status' =>
                UserStatus::ACTIVE,
        ]);

        $response = $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->postJson(
                '/api/admin/menus',
                [
                    'name' =>
                        'Unauthorized',

                    'slug' =>
                        'unauthorized',

                    'location' =>
                        'header',
                ]
            );

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Forbidden: Missing permission'
            );

        $this->assertDatabaseMissing(
            'menus',
            [
                'slug' =>
                    'unauthorized',
            ]
        );
    }

    public function test_user_without_menus_permission_cannot_create_menu_item(): void
    {
        $user = User::factory()->create([
            'status' =>
                UserStatus::ACTIVE,
        ]);

        $menu = $this->createMenu();

        $response = $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->postJson(
                "/api/admin/menus/{$menu->id}/items",
                [
                    'title' => [
                        'en' =>
                            'Unauthorized',

                        'fa' =>
                            'غیرمجاز',
                    ],

                    'type' =>
                        'url',

                    'url' =>
                        '/',
                ]
            );

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Forbidden: Missing permission'
            );

        $this->assertDatabaseCount(
            'menu_items',
            0
        );
    }

    public function test_guest_cannot_access_menu_management(): void
    {
        $response = $this->getJson(
            '/api/admin/menus'
        );

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createUserWithPermission(
        string $permissionSlug
    ): User {
        $user = User::factory()->create([
            'email' =>
                'menu-admin-' .
                uniqid() .
                '@tkb.test',

            'status' =>
                UserStatus::ACTIVE,
        ]);

        $role = Role::create([
            'name' =>
                'Menu Admin',

            'slug' =>
                'menu-admin-' .
                uniqid(),

            'description' =>
                'Test menu admin role',
        ]);

        $permission = Permission::firstOrCreate(
            [
                'slug' =>
                    $permissionSlug,
            ],
            [
                'name' =>
                    'Menus Manage',

                'description' =>
                    'Test menu management permission',
            ]
        );

        $role->permissions()->sync([
            $permission->id,
        ]);

        $user->roles()->attach(
            $role->id
        );

        return $user;
    }

    private function createMenu(
        array $attributes = []
    ): Menu {
        return Menu::create(
            array_merge(
                [
                    'name' =>
                        'Test Menu',

                    'slug' =>
                        'test-menu-' .
                        uniqid(),

                    'location' =>
                        'header',

                    'is_active' =>
                        true,
                ],
                $attributes
            )
        );
    }

    private function createPage(
        array $attributes = []
    ): Page {
        return Page::create(
            array_merge(
                [
                    'title' => [
                        'en' =>
                            'Test Page',

                        'fa' =>
                            'صفحه تست',
                    ],

                    'slug' =>
                        'test-page-' .
                        uniqid(),

                    'content' => [
                        'en' =>
                            'Test page content.',

                        'fa' =>
                            'محتوای صفحه تست.',
                    ],

                    'status' =>
                        \App\Enums\PageStatus::PUBLISHED,
                ],
                $attributes
            )
        );
    }

    private function createCategory(
        array $attributes = []
    ): Category {
        return Category::create(
            array_merge(
                [
                    'name' => [
                        'en' =>
                            'Test Category',

                        'fa' =>
                            'دسته تست',
                    ],

                    'slug' =>
                        'test-category-' .
                        uniqid(),

                    'description' => [
                        'en' =>
                            'Test category',

                        'fa' =>
                            'دسته تست',
                    ],

                    'is_active' =>
                        true,
                ],
                $attributes
            )
        );
    }

    private function createCollection(
        array $attributes = []
    ): Collection {
        return Collection::create(
            array_merge(
                [
                    'name' => [
                        'en' =>
                            'Test Collection',

                        'fa' =>
                            'کالکشن تست',
                    ],

                    'slug' =>
                        'test-collection-' .
                        uniqid(),

                    'description' => [
                        'en' =>
                            'Test collection',

                        'fa' =>
                            'کالکشن تست',
                    ],

                    'is_active' =>
                        true,
                ],
                $attributes
            )
        );
    }

    private function createProduct(
        array $attributes = []
    ): Product {
        return Product::create(
            array_merge(
                [
                    'category_id' =>
                        $this
                            ->createCategory()
                            ->id,

                    'name' => [
                        'en' =>
                            'Test Product',

                        'fa' =>
                            'محصول تست',
                    ],

                    'slug' =>
                        'test-product-' .
                        uniqid(),

                    'description' => [
                        'en' =>
                            'Test product',

                        'fa' =>
                            'محصول تست',
                    ],

                    'status' =>
                        \App\Enums\ProductStatus::ACTIVE,
                ],
                $attributes
            )
        );
    }
}
