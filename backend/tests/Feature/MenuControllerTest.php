<?php

namespace Tests\Feature;

use App\Enums\MenuItemType;
use App\Enums\PageStatus;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Active Menu
    |--------------------------------------------------------------------------
    */

    public function test_public_can_view_active_menu_by_slug(): void
    {
        $menu = $this->createMenu([
            'name' => 'Main Menu',
            'slug' => 'main-menu',
            'location' => 'header',
            'is_active' => true,
        ]);

        $menu->items()->create([
            'title' => [
                'en' => 'Home',
                'fa' => 'خانه',
            ],
            'type' => MenuItemType::URL,
            'url' => '/',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $response = $this->getJson(
            '/api/menus/main-menu'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $menu->id
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
                'data.locale',
                'en'
            )
            ->assertJsonPath(
                'data.direction',
                'ltr'
            )
            ->assertJsonCount(
                1,
                'data.items'
            )
            ->assertJsonPath(
                'data.items.0.title',
                'Home'
            )
            ->assertJsonPath(
                'data.items.0.type',
                'url'
            )
            ->assertJsonPath(
                'data.items.0.url',
                '/'
            );
    }

    public function test_public_menu_does_not_require_authentication(): void
    {
        $this->createMenu([
            'slug' => 'main-menu',
            'is_active' => true,
        ]);

        $response = $this->getJson(
            '/api/menus/main-menu'
        );

        $response->assertOk();
    }

    public function test_inactive_menu_is_not_publicly_accessible(): void
    {
        $this->createMenu([
            'slug' => 'hidden-menu',
            'is_active' => false,
        ]);

        $response = $this->getJson(
            '/api/menus/hidden-menu'
        );

        $response->assertNotFound();
    }

    public function test_non_existing_menu_returns_not_found(): void
    {
        $response = $this->getJson(
            '/api/menus/does-not-exist'
        );

        $response->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    */

    public function test_public_menu_returns_english_menu_item_title(): void
    {
        $menu = $this->createMenu([
            'slug' => 'main-menu',
        ]);

        $this->createMenuItem($menu, [
            'title' => [
                'en' => 'Cookware',
                'fa' => 'ظروف آشپزی',
            ],
        ]);

        $response = $this
            ->withHeader(
                'Accept-Language',
                'en'
            )
            ->getJson(
                '/api/menus/main-menu'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.locale',
                'en'
            )
            ->assertJsonPath(
                'data.direction',
                'ltr'
            )
            ->assertJsonPath(
                'data.items.0.title',
                'Cookware'
            );
    }

    public function test_public_menu_returns_persian_menu_item_title(): void
    {
        $menu = $this->createMenu([
            'slug' => 'main-menu',
        ]);

        $this->createMenuItem($menu, [
            'title' => [
                'en' => 'Cookware',
                'fa' => 'ظروف آشپزی',
            ],
        ]);

        $response = $this
            ->withHeader(
                'Accept-Language',
                'fa'
            )
            ->getJson(
                '/api/menus/main-menu'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.locale',
                'fa'
            )
            ->assertJsonPath(
                'data.direction',
                'rtl'
            )
            ->assertJsonPath(
                'data.items.0.title',
                'ظروف آشپزی'
            );
    }

    public function test_public_menu_falls_back_to_english_when_persian_title_is_missing(): void
    {
        $menu = $this->createMenu([
            'slug' => 'main-menu',
        ]);

        $this->createMenuItem($menu, [
            'title' => [
                'en' => 'Cookware',
            ],
        ]);

        $response = $this
            ->withHeader(
                'Accept-Language',
                'fa'
            )
            ->getJson(
                '/api/menus/main-menu'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.locale',
                'fa'
            )
            ->assertJsonPath(
                'data.direction',
                'rtl'
            )
            ->assertJsonPath(
                'data.items.0.title',
                'Cookware'
            );
    }

    public function test_public_menu_localizes_nested_children(): void
    {
        $menu = $this->createMenu([
            'slug' => 'main-menu',
        ]);

        $parent = $this->createMenuItem($menu, [
            'title' => [
                'en' => 'Kitchen',
                'fa' => 'آشپزخانه',
            ],
        ]);

        $child = $this->createMenuItem($menu, [
            'parent_id' => $parent->id,
            'title' => [
                'en' => 'Cookware',
                'fa' => 'ظروف آشپزی',
            ],
        ]);

        $grandchild = $this->createMenuItem($menu, [
            'parent_id' => $child->id,
            'title' => [
                'en' => 'Pans',
                'fa' => 'تابه‌ها',
            ],
        ]);

        $response = $this
            ->withHeader(
                'Accept-Language',
                'fa'
            )
            ->getJson(
                '/api/menus/main-menu'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.locale',
                'fa'
            )
            ->assertJsonPath(
                'data.direction',
                'rtl'
            )
            ->assertJsonPath(
                'data.items.0.id',
                $parent->id
            )
            ->assertJsonPath(
                'data.items.0.title',
                'آشپزخانه'
            )
            ->assertJsonPath(
                'data.items.0.children.0.id',
                $child->id
            )
            ->assertJsonPath(
                'data.items.0.children.0.title',
                'ظروف آشپزی'
            )
            ->assertJsonPath(
                'data.items.0.children.0.children.0.id',
                $grandchild->id
            )
            ->assertJsonPath(
                'data.items.0.children.0.children.0.title',
                'تابه‌ها'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Active Items
    |--------------------------------------------------------------------------
    */

    public function test_public_menu_returns_only_active_root_items(): void
    {
        $menu = $this->createMenu([
            'slug' => 'main-menu',
        ]);

        $active = $this->createMenuItem($menu, [
            'title' => [
                'en' => 'Active Item',
                'fa' => 'آیتم فعال',
            ],
            'url' => '/active',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->createMenuItem($menu, [
            'title' => [
                'en' => 'Inactive Item',
                'fa' => 'آیتم غیرفعال',
            ],
            'url' => '/inactive',
            'sort_order' => 2,
            'is_active' => false,
        ]);

        $response = $this->getJson(
            '/api/menus/main-menu'
        );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.items'
            )
            ->assertJsonPath(
                'data.items.0.id',
                $active->id
            )
            ->assertJsonMissing([
                'url' => '/inactive',
            ]);
    }

    public function test_public_menu_returns_items_sorted_by_sort_order(): void
    {
        $menu = $this->createMenu([
            'slug' => 'main-menu',
        ]);

        $third = $this->createMenuItem($menu, [
            'title' => [
                'en' => 'Third',
                'fa' => 'سوم',
            ],
            'url' => '/third',
            'sort_order' => 3,
        ]);

        $first = $this->createMenuItem($menu, [
            'title' => [
                'en' => 'First',
                'fa' => 'اول',
            ],
            'url' => '/first',
            'sort_order' => 1,
        ]);

        $second = $this->createMenuItem($menu, [
            'title' => [
                'en' => 'Second',
                'fa' => 'دوم',
            ],
            'url' => '/second',
            'sort_order' => 2,
        ]);

        $response = $this->getJson(
            '/api/menus/main-menu'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.id',
                $first->id
            )
            ->assertJsonPath(
                'data.items.1.id',
                $second->id
            )
            ->assertJsonPath(
                'data.items.2.id',
                $third->id
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Parent / Child Tree
    |--------------------------------------------------------------------------
    */

    public function test_public_menu_returns_nested_children(): void
    {
        $menu = $this->createMenu([
            'slug' => 'main-menu',
        ]);

        $parent = $this->createMenuItem($menu, [
            'title' => [
                'en' => 'Kitchen',
                'fa' => 'آشپزخانه',
            ],
            'url' => '/kitchen',
            'sort_order' => 1,
        ]);

        $child = $this->createMenuItem($menu, [
            'parent_id' => $parent->id,
            'title' => [
                'en' => 'Cookware',
                'fa' => 'ظروف آشپزی',
            ],
            'url' => '/kitchen/cookware',
            'sort_order' => 1,
        ]);

        $grandchild = $this->createMenuItem($menu, [
            'parent_id' => $child->id,
            'title' => [
                'en' => 'Pans',
                'fa' => 'تابه‌ها',
            ],
            'url' => '/kitchen/cookware/pans',
            'sort_order' => 1,
        ]);

        $response = $this->getJson(
            '/api/menus/main-menu'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.id',
                $parent->id
            )
            ->assertJsonPath(
                'data.items.0.title',
                'Kitchen'
            )
            ->assertJsonCount(
                1,
                'data.items.0.children'
            )
            ->assertJsonPath(
                'data.items.0.children.0.id',
                $child->id
            )
            ->assertJsonPath(
                'data.items.0.children.0.title',
                'Cookware'
            )
            ->assertJsonCount(
                1,
                'data.items.0.children.0.children'
            )
            ->assertJsonPath(
                'data.items.0.children.0.children.0.id',
                $grandchild->id
            )
            ->assertJsonPath(
                'data.items.0.children.0.children.0.title',
                'Pans'
            );
    }

    public function test_public_menu_excludes_inactive_children(): void
    {
        $menu = $this->createMenu([
            'slug' => 'main-menu',
        ]);

        $parent = $this->createMenuItem($menu, [
            'title' => [
                'en' => 'Kitchen',
                'fa' => 'آشپزخانه',
            ],
            'url' => '/kitchen',
            'sort_order' => 1,
        ]);

        $this->createMenuItem($menu, [
            'parent_id' => $parent->id,
            'title' => [
                'en' => 'Active Child',
                'fa' => 'فرزند فعال',
            ],
            'url' => '/active-child',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->createMenuItem($menu, [
            'parent_id' => $parent->id,
            'title' => [
                'en' => 'Hidden Child',
                'fa' => 'فرزند مخفی',
            ],
            'url' => '/hidden-child',
            'sort_order' => 2,
            'is_active' => false,
        ]);

        $response = $this->getJson(
            '/api/menus/main-menu'
        );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.items.0.children'
            )
            ->assertJsonPath(
                'data.items.0.children.0.title',
                'Active Child'
            )
            ->assertJsonMissing([
                'title' => 'Hidden Child',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Target Types
    |--------------------------------------------------------------------------
    */

    public function test_public_url_menu_item_returns_url(): void
    {
        $menu = $this->createMenu([
            'slug' => 'main-menu',
        ]);

        $this->createMenuItem($menu, [
            'title' => [
                'en' => 'Home',
                'fa' => 'خانه',
            ],
            'type' => MenuItemType::URL,
            'url' => '/',
        ]);

        $response = $this->getJson(
            '/api/menus/main-menu'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.type',
                'url'
            )
            ->assertJsonPath(
                'data.items.0.url',
                '/'
            )
            ->assertJsonPath(
                'data.items.0.target_id',
                null
            );
    }

    public function test_public_page_menu_item_returns_page_target_id(): void
    {
        $menu = $this->createMenu([
            'slug' => 'main-menu',
        ]);

        $page = $this->createPage();

        $this->createMenuItem($menu, [
            'title' => [
                'en' => 'About',
                'fa' => 'درباره ما',
            ],
            'type' => MenuItemType::PAGE,
            'page_id' => $page->id,
        ]);

        $response = $this->getJson(
            '/api/menus/main-menu'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.type',
                'page'
            )
            ->assertJsonPath(
                'data.items.0.target_id',
                $page->id
            );
    }

    public function test_public_category_menu_item_returns_category_target_id(): void
    {
        $menu = $this->createMenu([
            'slug' => 'main-menu',
        ]);

        $category = $this->createCategory();

        $this->createMenuItem($menu, [
            'title' => [
                'en' => 'Cookware',
                'fa' => 'ظروف آشپزی',
            ],
            'type' => MenuItemType::CATEGORY,
            'category_id' => $category->id,
        ]);

        $response = $this->getJson(
            '/api/menus/main-menu'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.type',
                'category'
            )
            ->assertJsonPath(
                'data.items.0.target_id',
                $category->id
            );
    }

    public function test_public_collection_menu_item_returns_collection_target_id(): void
    {
        $menu = $this->createMenu([
            'slug' => 'main-menu',
        ]);

        $collection = $this->createCollection();

        $this->createMenuItem($menu, [
            'title' => [
                'en' => 'Summer Collection',
                'fa' => 'کالکشن تابستانی',
            ],
            'type' => MenuItemType::COLLECTION,
            'collection_id' => $collection->id,
        ]);

        $response = $this->getJson(
            '/api/menus/main-menu'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.type',
                'collection'
            )
            ->assertJsonPath(
                'data.items.0.target_id',
                $collection->id
            );
    }

    public function test_public_product_menu_item_returns_product_target_id(): void
    {
        $menu = $this->createMenu([
            'slug' => 'main-menu',
        ]);

        $product = $this->createProduct();

        $this->createMenuItem($menu, [
            'title' => [
                'en' => 'Featured Product',
                'fa' => 'محصول ویژه',
            ],
            'type' => MenuItemType::PRODUCT,
            'product_id' => $product->id,
        ]);

        $response = $this->getJson(
            '/api/menus/main-menu'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.type',
                'product'
            )
            ->assertJsonPath(
                'data.items.0.target_id',
                $product->id
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Public Response Shape
    |--------------------------------------------------------------------------
    */

    public function test_public_menu_response_contains_only_public_fields(): void
    {
        $menu = $this->createMenu([
            'slug' => 'main-menu',
        ]);

        $this->createMenuItem($menu, [
            'title' => [
                'en' => 'Home',
                'fa' => 'خانه',
            ],
            'type' => MenuItemType::URL,
            'url' => '/',
        ]);

        $response = $this->getJson(
            '/api/menus/main-menu'
        );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'location',
                    'locale',
                    'direction',
                    'items' => [
                        '*' => [
                            'id',
                            'parent_id',
                            'title',
                            'type',
                            'url',
                            'target_id',
                            'sort_order',
                            'children',
                        ],
                    ],
                ],
            ]);
    }

    public function test_public_menu_does_not_expose_menu_item_internal_target_ids_except_target_id(): void
    {
        $menu = $this->createMenu([
            'slug' => 'main-menu',
        ]);

        $page = $this->createPage();

        $this->createMenuItem($menu, [
            'title' => [
                'en' => 'About',
                'fa' => 'درباره ما',
            ],
            'type' => MenuItemType::PAGE,
            'page_id' => $page->id,
        ]);

        $response = $this->getJson(
            '/api/menus/main-menu'
        );

        $response
            ->assertOk()
            ->assertJsonMissing([
                'page_id' => $page->id,
            ])
            ->assertJsonPath(
                'data.items.0.target_id',
                $page->id
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createMenu(
        array $attributes = []
    ): Menu {
        return Menu::create(
            array_merge(
                [
                    'name' => 'Test Menu',
                    'slug' => 'test-menu-' . uniqid(),
                    'location' => 'header',
                    'is_active' => true,
                ],
                $attributes
            )
        );
    }

    private function createMenuItem(
        Menu $menu,
        array $attributes = []
    ): \App\Models\MenuItem {
        return $menu->items()->create(
            array_merge(
                [
                    'title' => [
                        'en' => 'Test Item',
                        'fa' => 'آیتم تست',
                    ],
                    'type' => MenuItemType::URL,
                    'url' => '/test',
                    'sort_order' => 0,
                    'is_active' => true,
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
                        'en' => 'Test Page',
                        'fa' => 'صفحه تست',
                    ],
                    'slug' => 'test-page-' . uniqid(),
                    'content' => [
                        'en' => 'Test page content.',
                        'fa' => 'محتوای صفحه تست.',
                    ],
                    'status' => PageStatus::PUBLISHED,
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
                        'en' => 'Test Category',
                        'fa' => 'دسته تست',
                    ],
                    'slug' => 'test-category-' . uniqid(),
                    'description' => [
                        'en' => 'Test category',
                        'fa' => 'دسته تست',
                    ],
                    'is_active' => true,
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
                        'en' => 'Test Collection',
                        'fa' => 'کالکشن تست',
                    ],
                    'slug' => 'test-collection-' . uniqid(),
                    'description' => [
                        'en' => 'Test collection',
                        'fa' => 'کالکشن تست',
                    ],
                    'is_active' => true,
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
                        $this->createCategory()->id,

                    'name' => [
                        'en' => 'Test Product',
                        'fa' => 'محصول تست',
                    ],

                    'slug' => 'test-product-' .
                        uniqid(),

                    'description' => [
                        'en' => 'Test product',
                        'fa' => 'محصول تست',
                    ],

                    'status' =>
                        \App\Enums\ProductStatus::ACTIVE,
                ],
                $attributes
            )
        );
    }
}
