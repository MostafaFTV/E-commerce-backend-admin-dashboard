<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Admin\HomepageSectionController as AdminHomepageSectionController;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Collection;
use App\Models\HomepageSection;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HomepageApiTest extends TestCase
{
    use RefreshDatabase;

    private const HOMEPAGE_CACHE_PREFIX = 'tkb:homepage:v2:';

    protected function setUp(): void
    {
        parent::setUp();

        $this->clearHomepageCache();
    }

    protected function tearDown(): void
    {
        $this->clearHomepageCache();

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function clearHomepageCache(): void
    {
        $cache = Cache::store(config('cache.default'));

        $cache->forget(
            $this->homepageCacheKey('en')
        );

        $cache->forget(
            $this->homepageCacheKey('fa')
        );
    }

    private function warmHomepageCaches(): void
    {
        $cache = Cache::store(config('cache.default'));

        $payload = [
            'data' => [
                'banners' => [],
                'sections' => [],
            ],
        ];

        $cache->put(
            $this->homepageCacheKey('en'),
            $payload,
            now()->addMinutes(60)
        );

        $cache->put(
            $this->homepageCacheKey('fa'),
            $payload,
            now()->addMinutes(60)
        );
    }

    private function homepageCacheKey(
        string $locale = 'en'
    ): string {
        return self::HOMEPAGE_CACHE_PREFIX . $locale;
    }

    private function createCategory(
        array $attributes = []
    ): Category {
        $defaults = [
            'name' => [
                'en' => 'Kitchen',
                'fa' => 'آشپزخانه',
            ],

            'slug' => 'kitchen-' . uniqid(),

            'description' => [
                'en' => 'Kitchen category',
                'fa' => 'دسته آشپزخانه',
            ],

            'image' => null,

            'parent_id' => null,

            'sort_order' => 0,

            'is_active' => true,
        ];

        return Category::create(
            array_merge($defaults, $attributes)
        );
    }

    private function categoryAdmin(
        array $permissions = [
            'categories.create',
            'categories.update',
            'categories.delete',
        ]
    ): User {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,

            'email' =>
                'homepage-category-admin-'
                . uniqid()
                . '@tkb.test',
        ]);

        $role = Role::firstOrCreate(
            [
                'slug' => 'admin',
            ],
            [
                'name' => 'Admin',
            ]
        );

        $permissionIds = collect(
            $permissions
        )->map(
            fn (string $slug) =>
                Permission::firstOrCreate(
                    [
                        'slug' => $slug,
                    ],
                    [
                        'name' => Str::headline(
                            str_replace(
                                '.',
                                ' ',
                                $slug
                            )
                        ),
                    ]
                )->id
        );

        $role->permissions()->sync(
            $permissionIds
        );

        $user->roles()->sync([
            $role->id,
        ]);

        return $user;
    }

    private function productAdmin(
        array $permissions = [
            'products.create',
            'products.update',
            'products.delete',
        ]
    ): User {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,

            'email' =>
                'homepage-product-admin-'
                . uniqid()
                . '@tkb.test',
        ]);

        $role = Role::firstOrCreate(
            [
                'slug' => 'admin',
            ],
            [
                'name' => 'Admin',
            ]
        );

        $permissionIds = collect(
            $permissions
        )->map(
            fn (string $slug) =>
                Permission::firstOrCreate(
                    [
                        'slug' => $slug,
                    ],
                    [
                        'name' => Str::headline(
                            str_replace(
                                '.',
                                ' ',
                                $slug
                            )
                        ),
                    ]
                )->id
        );

        $role->permissions()->sync(
            $permissionIds
        );

        $user->roles()->sync([
            $role->id,
        ]);

        return $user;
    }

    private function createProduct(
        Category $category,
        array $attributes = []
    ): Product {
        $defaults = [
            'category_id' => $category->id,

            'name' => [
                'en' => 'Premium Product',
                'fa' => 'محصول پریمیوم',
            ],

            'slug' => 'premium-product-' . uniqid(),

            'price' => 100000,

            'status' => ProductStatus::ACTIVE,

            'is_featured' => false,
        ];

        return Product::create(
            array_merge($defaults, $attributes)
        );
    }

    private function createBanner(
        array $attributes = []
    ): Banner {
        $defaults = [
            'title' => [
                'en' => 'Test Banner',
                'fa' => 'بنر تست',
            ],

            'subtitle' => null,

            'image' => 'banners/test-banner.jpg',

            'mobile_image' => null,

            'link' => null,

            'button_text' => null,

            'sort_order' => 0,

            'is_active' => true,

            'starts_at' => null,

            'ends_at' => null,
        ];

        return Banner::create(
            array_merge($defaults, $attributes)
        );
    }

    private function createHomepageSection(
        array $attributes = []
    ): HomepageSection {
        $defaults = [
            'section_key' => 'section-' . uniqid(),

            'title' => [
                'en' => 'Test Section',
                'fa' => 'بخش تست',
            ],

            'subtitle' => null,

            'content' => null,

            'image' => null,

            'mobile_image' => null,

            'link' => null,

            'button_text' => null,

            'category_id' => null,

            'product_id' => null,

            'collection_id' => null,

            'is_active' => true,

            'sort_order' => 0,
        ];

        return HomepageSection::create(
            array_merge($defaults, $attributes)
        );
    }

    private function createCollection(
        array $attributes = []
    ): Collection {
        $defaults = [
            'name' => [
                'en' => 'Summer Collection',
                'fa' => 'کالکشن تابستانی',
            ],

            'slug' => 'summer-collection-' . uniqid(),

            'description' => [
                'en' => 'Summer collection',
                'fa' => 'کالکشن تابستانی',
            ],

            'image' => null,

            'is_active' => true,

            'sort_order' => 0,
        ];

        return Collection::create(
            array_merge($defaults, $attributes)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Public Endpoint
    |--------------------------------------------------------------------------
    */

    public function test_public_homepage_endpoint_is_accessible_without_authentication(): void
    {
        $response = $this->getJson('/api/homepage');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'banners',
                    'sections',
                ],
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Banner Visibility
    |--------------------------------------------------------------------------
    */

    public function test_homepage_returns_only_currently_active_banners(): void
    {
        $activeBanner = $this->createBanner([
            'title' => [
                'en' => 'Active Banner',
                'fa' => 'بنر فعال',
            ],

            'is_active' => true,

            'starts_at' => now()->subHour(),

            'ends_at' => now()->addHour(),

            'sort_order' => 1,
        ]);

        $inactiveBanner = $this->createBanner([
            'title' => [
                'en' => 'Inactive Banner',
                'fa' => 'بنر غیرفعال',
            ],

            'is_active' => false,

            'starts_at' => now()->subHour(),

            'ends_at' => now()->addHour(),

            'sort_order' => 2,
        ]);

        $futureBanner = $this->createBanner([
            'title' => [
                'en' => 'Future Banner',
                'fa' => 'بنر آینده',
            ],

            'is_active' => true,

            'starts_at' => now()->addHour(),

            'ends_at' => now()->addHours(2),

            'sort_order' => 3,
        ]);

        $expiredBanner = $this->createBanner([
            'title' => [
                'en' => 'Expired Banner',
                'fa' => 'بنر منقضی',
            ],

            'is_active' => true,

            'starts_at' => now()->subHours(2),

            'ends_at' => now()->subHour(),

            'sort_order' => 4,
        ]);

        $response = $this->getJson('/api/homepage');

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.banners'
            )
            ->assertJsonPath(
                'data.banners.0.id',
                $activeBanner->id
            )
            ->assertJsonPath(
                'data.banners.0.title',
                'Active Banner'
            );

        $this->assertDatabaseHas(
            'banners',
            [
                'id' => $inactiveBanner->id,
            ]
        );

        $this->assertDatabaseHas(
            'banners',
            [
                'id' => $futureBanner->id,
            ]
        );

        $this->assertDatabaseHas(
            'banners',
            [
                'id' => $expiredBanner->id,
            ]
        );
    }

    public function test_homepage_returns_banner_without_start_or_end_schedule(): void
    {
        $banner = $this->createBanner([
            'title' => [
                'en' => 'Always Available',
                'fa' => 'همیشه فعال',
            ],

            'is_active' => true,

            'starts_at' => null,

            'ends_at' => null,

            'sort_order' => 0,
        ]);

        $response = $this->getJson('/api/homepage');

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.banners'
            )
            ->assertJsonPath(
                'data.banners.0.id',
                $banner->id
            )
            ->assertJsonPath(
                'data.banners.0.is_currently_active',
                true
            );
    }

    public function test_homepage_orders_banners_by_sort_order_then_id(): void
    {
        $first = $this->createBanner([
            'title' => [
                'en' => 'First',
                'fa' => 'اول',
            ],

            'sort_order' => 1,

            'is_active' => true,
        ]);

        $second = $this->createBanner([
            'title' => [
                'en' => 'Second',
                'fa' => 'دوم',
            ],

            'sort_order' => 1,

            'is_active' => true,
        ]);

        $third = $this->createBanner([
            'title' => [
                'en' => 'Third',
                'fa' => 'سوم',
            ],

            'sort_order' => 0,

            'is_active' => true,
        ]);

        $response = $this->getJson('/api/homepage');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.banners.0.id',
                $third->id
            )
            ->assertJsonPath(
                'data.banners.1.id',
                $first->id
            )
            ->assertJsonPath(
                'data.banners.2.id',
                $second->id
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Homepage Section Visibility
    |--------------------------------------------------------------------------
    */

    public function test_homepage_returns_only_active_sections(): void
    {
        $activeSection = $this->createHomepageSection([
            'section_key' => 'hero',

            'title' => [
                'en' => 'Hero',
                'fa' => 'هیرو',
            ],

            'is_active' => true,

            'sort_order' => 0,
        ]);

        $this->createHomepageSection([
            'section_key' => 'inactive',

            'title' => [
                'en' => 'Inactive',
                'fa' => 'غیرفعال',
            ],

            'is_active' => false,

            'sort_order' => 1,
        ]);

        $response = $this->getJson('/api/homepage');

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.sections'
            )
            ->assertJsonPath(
                'data.sections.0.id',
                $activeSection->id
            )
            ->assertJsonPath(
                'data.sections.0.section_key',
                'hero'
            );
    }

    public function test_homepage_orders_sections_by_sort_order_then_id(): void
    {
        $first = $this->createHomepageSection([
            'section_key' => 'first-section',

            'sort_order' => 1,

            'is_active' => true,
        ]);

        $second = $this->createHomepageSection([
            'section_key' => 'second-section',

            'sort_order' => 1,

            'is_active' => true,
        ]);

        $third = $this->createHomepageSection([
            'section_key' => 'third-section',

            'sort_order' => 0,

            'is_active' => true,
        ]);

        $response = $this->getJson('/api/homepage');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.sections.0.id',
                $third->id
            )
            ->assertJsonPath(
                'data.sections.1.id',
                $first->id
            )
            ->assertJsonPath(
                'data.sections.2.id',
                $second->id
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Category Relation
    |--------------------------------------------------------------------------
    */

    public function test_homepage_returns_active_category_relation(): void
    {
        $category = $this->createCategory();

        $this->createHomepageSection([
            'section_key' => 'categories',

            'category_id' => $category->id,

            'is_active' => true,
        ]);

        $response = $this->getJson('/api/homepage');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.sections.0.category.id',
                $category->id
            )
            ->assertJsonPath(
                'data.sections.0.category.slug',
                $category->slug
            )
            ->assertJsonPath(
                'data.sections.0.category.name',
                'Kitchen'
            );
    }

    public function test_homepage_does_not_return_inactive_category_relation(): void
    {
        $category = $this->createCategory([
            'is_active' => false,
        ]);

        $this->createHomepageSection([
            'section_key' => 'categories',

            'category_id' => $category->id,

            'is_active' => true,
        ]);

        $response = $this->getJson('/api/homepage');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.sections.0.category',
                null
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Product Relation
    |--------------------------------------------------------------------------
    */

    public function test_homepage_returns_purchasable_product_relation(): void
    {
        $category = $this->createCategory();

        $product = $this->createProduct(
            $category
        );

        $this->createHomepageSection([
            'section_key' => 'featured-product',

            'product_id' => $product->id,

            'is_active' => true,
        ]);

        $response = $this->getJson('/api/homepage');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.sections.0.product.id',
                $product->id
            )
            ->assertJsonPath(
                'data.sections.0.product.slug',
                $product->slug
            )
            ->assertJsonPath(
                'data.sections.0.product.status',
                ProductStatus::ACTIVE->value
            );
    }

    public function test_homepage_does_not_return_product_from_inactive_category(): void
    {
        $category = $this->createCategory([
            'is_active' => false,
        ]);

        $product = $this->createProduct(
            $category
        );

        $this->createHomepageSection([
            'section_key' => 'featured-product',

            'product_id' => $product->id,

            'is_active' => true,
        ]);

        $response = $this->getJson('/api/homepage');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.sections.0.product',
                null
            );
    }

    public function test_homepage_does_not_return_unavailable_product_status(): void
    {
        $category = $this->createCategory();

        $product = $this->createProduct(
            $category,
            [
                'status' => ProductStatus::DRAFT,
            ]
        );

        $this->createHomepageSection([
            'section_key' => 'featured-product',

            'product_id' => $product->id,

            'is_active' => true,
        ]);

        $response = $this->getJson('/api/homepage');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.sections.0.product',
                null
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Collection Relation
    |--------------------------------------------------------------------------
    */

    public function test_homepage_returns_active_collection_relation(): void
    {
        $collection = $this->createCollection([
            'name' => [
                'en' => 'Summer Collection',
                'fa' => 'کالکشن تابستانی',
            ],

            'is_active' => true,
        ]);

        $this->createHomepageSection([
            'section_key' => 'featured-collection',

            'collection_id' => $collection->id,

            'is_active' => true,
        ]);

        $response = $this->getJson('/api/homepage');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.sections.0.collection.id',
                $collection->id
            )
            ->assertJsonPath(
                'data.sections.0.collection.slug',
                $collection->slug
            )
            ->assertJsonPath(
                'data.sections.0.collection.name',
                'Summer Collection'
            );
    }

    public function test_homepage_does_not_return_inactive_collection_relation(): void
    {
        $collection = $this->createCollection([
            'name' => [
                'en' => 'Hidden Collection',
                'fa' => 'کالکشن مخفی',
            ],

            'is_active' => false,
        ]);

        $this->createHomepageSection([
            'section_key' => 'featured-collection',

            'collection_id' => $collection->id,

            'is_active' => true,
        ]);

        $response = $this->getJson('/api/homepage');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.sections.0.collection',
                null
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    */

    public function test_homepage_uses_locale_specific_cache_keys(): void
    {
        $this->createHomepageSection([
            'section_key' => 'hero',

            'title' => [
                'en' => 'English Hero',
                'fa' => 'هیرو فارسی',
            ],

            'is_active' => true,
        ]);

        $englishResponse = $this->withHeader(
            'Accept-Language',
            'en'
        )->getJson('/api/homepage');

        $englishResponse
            ->assertOk()
            ->assertJsonPath(
                'data.sections.0.locale',
                'en'
            )
            ->assertJsonPath(
                'data.sections.0.title',
                'English Hero'
            );

        $persianResponse = $this->withHeader(
            'Accept-Language',
            'fa'
        )->getJson('/api/homepage');

        $persianResponse
            ->assertOk()
            ->assertJsonPath(
                'data.sections.0.locale',
                'fa'
            )
            ->assertJsonPath(
                'data.sections.0.title',
                'هیرو فارسی'
            );

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Redis Cache
    |--------------------------------------------------------------------------
    */

    public function test_homepage_stores_final_array_payload_in_redis(): void
    {
        $this->createHomepageSection([
            'section_key' => 'hero',

            'title' => [
                'en' => 'Hero',
                'fa' => 'هیرو',
            ],

            'is_active' => true,
        ]);

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey()
            )
        );

        $response = $this->getJson('/api/homepage');

        $response->assertOk();

        $cachedPayload = Cache::store(config('cache.default'))->get(
            $this->homepageCacheKey()
        );

        $this->assertIsArray(
            $cachedPayload
        );

        $this->assertArrayHasKey(
            'data',
            $cachedPayload
        );

        $this->assertArrayHasKey(
            'sections',
            $cachedPayload['data']
        );

        $this->assertSame(
            'Hero',
            $cachedPayload['data']['sections'][0]['title']
        );
    }

    public function test_second_homepage_request_uses_redis_without_homepage_queries(): void
    {
        $this->createHomepageSection([
            'section_key' => 'hero',

            'title' => [
                'en' => 'Hero',
                'fa' => 'هیرو',
            ],

            'is_active' => true,
        ]);

        $this->getJson('/api/homepage')
            ->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/homepage')
            ->assertOk();

        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertCount(
            0,
            $queries,
            'Expected Redis cache hit to avoid homepage database queries.'
        );
    }

    public function test_homepage_cache_miss_executes_only_required_catalog_queries(): void
    {
        $this->createHomepageSection([
            'section_key' => 'hero',

            'is_active' => true,
        ]);

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey()
            )
        );

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/homepage')
            ->assertOk();

        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertGreaterThan(
            0,
            count($queries)
        );

        $this->assertLessThanOrEqual(
            4,
            count($queries),
            'Homepage cache miss should remain query-efficient.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Banner Cache Invalidation
    |--------------------------------------------------------------------------
    */

    public function test_creating_banner_clears_homepage_cache_for_all_locales(): void
    {
        $this->warmHomepageCaches();

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );

        $controller = app(
            AdminBannerController::class
        );

        $request = Request::create(
            '/api/admin/banners',
            'POST',
            [
                'title' => [
                    'en' => 'New Banner',
                    'fa' => 'بنر جدید',
                ],

                'image' =>
                    '/storage/banners/new-banner.jpg',

                'sort_order' => 1,

                'is_active' => true,
            ]
        );

        $response = $controller->store(
            $request
        );

        $this->assertSame(
            201,
            $response->getStatusCode()
        );

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );
    }

    public function test_updating_banner_clears_homepage_cache_for_all_locales(): void
    {
        $banner = $this->createBanner([
            'title' => [
                'en' => 'Original Banner',
                'fa' => 'بنر اصلی',
            ],
        ]);

        $this->warmHomepageCaches();

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );

        $controller = app(
            AdminBannerController::class
        );

        $request = Request::create(
            "/api/admin/banners/{$banner->id}",
            'PATCH',
            [
                'title' => [
                    'en' => 'Updated Banner',
                ],
            ]
        );

        $controller->update(
            $request,
            $banner
        );

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );
    }

    public function test_deleting_banner_clears_homepage_cache_for_all_locales(): void
    {
        $banner = $this->createBanner([
            'title' => [
                'en' => 'Banner To Delete',
                'fa' => 'بنر برای حذف',
            ],
        ]);

        $this->warmHomepageCaches();

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );

        $controller = app(
            AdminBannerController::class
        );

        $response = $controller->destroy(
            $banner
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertDatabaseMissing(
            'banners',
            [
                'id' => $banner->id,
            ]
        );

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Homepage Section Cache Invalidation
    |--------------------------------------------------------------------------
    */

    public function test_creating_homepage_section_clears_homepage_cache_for_all_locales(): void
    {
        $this->warmHomepageCaches();

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );

        $controller = app(
            AdminHomepageSectionController::class
        );

        $request = Request::create(
            '/api/admin/homepage-sections',
            'POST',
            [
                'section_key' => 'new_section',

                'title' => [
                    'en' => 'New Section',
                    'fa' => 'بخش جدید',
                ],

                'is_active' => true,

                'sort_order' => 1,
            ]
        );

        $response = $controller->store(
            $request
        );

        $this->assertSame(
            201,
            $response->getStatusCode()
        );

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );
    }

    public function test_updating_homepage_section_clears_homepage_cache_for_all_locales(): void
    {
        $section = $this->createHomepageSection([
            'section_key' => 'hero',

            'title' => [
                'en' => 'Original Hero',
                'fa' => 'هیروی اصلی',
            ],

            'is_active' => true,
        ]);

        $this->warmHomepageCaches();

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );

        $controller = app(
            AdminHomepageSectionController::class
        );

        $request = Request::create(
            "/api/admin/homepage-sections/{$section->id}",
            'PATCH',
            [
                'title' => [
                    'en' => 'Updated Hero',
                ],
            ]
        );

        $controller->update(
            $request,
            $section
        );

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );
    }

    public function test_deleting_homepage_section_clears_homepage_cache_for_all_locales(): void
    {
        $section = $this->createHomepageSection([
            'section_key' => 'hero',

            'title' => [
                'en' => 'Hero To Delete',
                'fa' => 'هیرو برای حذف',
            ],

            'is_active' => true,
        ]);

        $this->warmHomepageCaches();

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );

        $controller = app(
            AdminHomepageSectionController::class
        );

        $response = $controller->destroy(
            $section
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertDatabaseMissing(
            'homepage_sections',
            [
                'id' => $section->id,
            ]
        );

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Category Cache Invalidation
    |--------------------------------------------------------------------------
    */

    public function test_creating_category_clears_homepage_cache_for_all_locales(): void
    {
        $user = $this->categoryAdmin();

        $this->warmHomepageCaches();

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );

        Sanctum::actingAs(
            $user
        );

        $response = $this->postJson(
            '/api/admin/categories',
            [
                'name' => [
                    'en' => 'New Kitchen Category',
                    'fa' => 'دسته جدید آشپزخانه',
                ],

                'description' => [
                    'en' => 'New category',
                    'fa' => 'دسته جدید',
                ],

                'sort_order' => 1,

                'is_active' => true,
            ]
        );

        $response->assertSuccessful();

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );
    }

    public function test_updating_category_clears_homepage_cache_for_all_locales(): void
    {
        $user = $this->categoryAdmin();

        $category = $this->createCategory([
            'name' => [
                'en' => 'Original Category',
                'fa' => 'دسته اصلی',
            ],
        ]);

        $this->warmHomepageCaches();

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );

        Sanctum::actingAs(
            $user
        );

        $response = $this->putJson(
            "/api/admin/categories/{$category->id}",
            [
                'name' => [
                    'en' => 'Updated Category',
                ],

                'is_active' => false,
            ]
        );

        $response->assertSuccessful();

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );
    }

    public function test_deleting_category_clears_homepage_cache_for_all_locales(): void
    {
        $user = $this->categoryAdmin();

        $category = $this->createCategory();

        $this->warmHomepageCaches();

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );

        Sanctum::actingAs(
            $user
        );

        $response = $this->deleteJson(
            "/api/admin/categories/{$category->id}"
        );

        $response->assertSuccessful();

        $this->assertDatabaseMissing(
            'categories',
            [
                'id' => $category->id,
            ]
        );

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Product Cache Invalidation
    |--------------------------------------------------------------------------
    */

    public function test_creating_product_clears_homepage_cache_for_all_locales(): void
    {
        $user = $this->productAdmin();

        $category = $this->createCategory();

        $this->warmHomepageCaches();

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );

        Sanctum::actingAs(
            $user
        );

        $response = $this->postJson(
            '/api/admin/products',
            [
                'category_id' => $category->id,

                'name' => [
                    'en' => 'Homepage Product',
                    'fa' => 'محصول صفحه اصلی',
                ],

                'slug' => 'homepage-product-' . uniqid(),

                'price' => 250000,

                'status' => ProductStatus::ACTIVE->value,

                'is_featured' => false,
            ]
        );

        $response->assertSuccessful();

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );
    }

    public function test_updating_product_clears_homepage_cache_for_all_locales(): void
    {
        $user = $this->productAdmin();

        $category = $this->createCategory();

        $product = $this->createProduct(
            $category,
            [
                'name' => [
                    'en' => 'Original Homepage Product',
                    'fa' => 'محصول اصلی صفحه اصلی',
                ],

                'price' => 200000,
            ]
        );

        $this->warmHomepageCaches();

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );

        Sanctum::actingAs(
            $user
        );

        $response = $this->patchJson(
            "/api/admin/products/{$product->id}",
            [
                'price' => 350000,
            ]
        );

        $response->assertSuccessful();

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );
    }

    public function test_deleting_product_clears_homepage_cache_for_all_locales(): void
    {
        $user = $this->productAdmin();

        $category = $this->createCategory();

        $product = $this->createProduct(
            $category
        );

        $this->warmHomepageCaches();

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNotNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );

        Sanctum::actingAs(
            $user
        );

        $response = $this->deleteJson(
            "/api/admin/products/{$product->id}"
        );

        $response->assertNoContent();

        /*
        |--------------------------------------------------------------------------
        | Product uses Soft Deletes
        |--------------------------------------------------------------------------
        */

        $deletedProduct = Product::withTrashed()->find(
            $product->id
        );

        $this->assertNotNull(
            $deletedProduct
        );

        $this->assertNotNull(
            $deletedProduct->deleted_at
        );

        /*
        |--------------------------------------------------------------------------
        | Homepage cache must be invalidated
        |--------------------------------------------------------------------------
        */

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('en')
            )
        );

        $this->assertNull(
            Cache::store(config('cache.default'))->get(
                $this->homepageCacheKey('fa')
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Payload Safety
    |--------------------------------------------------------------------------
    */

    public function test_homepage_product_relation_returns_only_public_fields(): void
    {
        $category = $this->createCategory();

        $product = $this->createProduct(
            $category,
            [
                'price' => 250000,
            ]
        );

        $this->createHomepageSection([
            'section_key' => 'featured-product',

            'product_id' => $product->id,

            'is_active' => true,
        ]);

        $response = $this->getJson('/api/homepage');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'sections' => [
                        '*' => [
                            'product' => [
                                'id',
                                'name',
                                'slug',
                                'price',
                                'status',
                            ],
                        ],
                    ],
                ],
            ]);

        $productPayload = $response->json(
            'data.sections.0.product'
        );

        $this->assertCount(
            5,
            $productPayload
        );

        $this->assertArrayNotHasKey(
            'created_at',
            $productPayload
        );

        $this->assertArrayNotHasKey(
            'updated_at',
            $productPayload
        );

        $this->assertArrayNotHasKey(
            'category_id',
            $productPayload
        );
    }

    public function test_homepage_sections_do_not_expose_unloaded_relations_as_full_models(): void
    {
        $section = $this->createHomepageSection([
            'section_key' => 'hero',

            'is_active' => true,
        ]);

        $response = $this->getJson('/api/homepage');

        $sectionPayload = $response->json(
            'data.sections.0'
        );

        $this->assertSame(
            $section->id,
            $sectionPayload['id']
        );

        $this->assertArrayNotHasKey(
            'category_id',
            $sectionPayload
        );

        $this->assertArrayNotHasKey(
            'product_id',
            $sectionPayload
        );

        $this->assertArrayNotHasKey(
            'collection_id',
            $sectionPayload
        );
    }
}