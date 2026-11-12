<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use App\Enums\VariantStatus;
use App\Models\ActivityLog;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CollectionApiTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */

    private const INDEX_CACHE_VERSION_KEY =
        'tkb:collections:index:version';

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    protected function setUp(): void
    {
        parent::setUp();

        $this->bumpCollectionIndexCacheVersion();
    }

    /*
    |--------------------------------------------------------------------------
    | Cache Helpers
    |--------------------------------------------------------------------------
    */

    private function currentCollectionIndexCacheVersion(): int
    {
        return (int) Cache::store(config('cache.default'))->get(
            self::INDEX_CACHE_VERSION_KEY,
            1
        );
    }

    private function bumpCollectionIndexCacheVersion(): void
    {
        $currentVersion = $this->currentCollectionIndexCacheVersion();

        Cache::store(config('cache.default'))->put(
            self::INDEX_CACHE_VERSION_KEY,
            $currentVersion + 1,
            now()->addDays(1)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Public API
    |--------------------------------------------------------------------------
    */

    public function test_public_listing_only_returns_active_collections(): void
    {
        $active = Collection::create([
            'name' => 'Featured',
            'slug' => 'featured',
            'description' => 'Featured products',
            'image' => 'featured.jpg',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $inactive = Collection::create([
            'name' => 'Hidden',
            'slug' => 'hidden',
            'description' => 'Hidden collection',
            'image' => 'hidden.jpg',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $response = $this->getJson('/api/collections')
            ->assertOk();

        $slugs = collect(
            $response->json('data')
        )->pluck('slug');

        $this->assertTrue(
            $slugs->contains($active->slug)
        );

        $this->assertFalse(
            $slugs->contains($inactive->slug)
        );
    }

    public function test_public_collection_listing_is_ordered_by_sort_order_then_id(): void
    {
        $first = Collection::create([
            'name' => 'Second',
            'slug' => 'second',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $sameOrderLowerId = Collection::create([
            'name' => 'First',
            'slug' => 'first',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $sameOrderHigherId = Collection::create([
            'name' => 'First Again',
            'slug' => 'first-again',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->getJson('/api/collections')
            ->assertOk();

        $slugs = collect(
            $response->json('data')
        )->pluck('slug')
            ->values();

        $this->assertSame(
            [
                $sameOrderLowerId->slug,
                $sameOrderHigherId->slug,
                $first->slug,
            ],
            $slugs->all()
        );
    }

    public function test_public_collection_listing_returns_active_product_count_only(): void
    {
        $collection = Collection::create([
            'name' => 'Featured',
            'slug' => 'featured',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $activeProduct = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
        ]);

        $inactiveProduct = Product::factory()
            ->inactive()
            ->create();

        $draftProduct = Product::factory()
            ->draft()
            ->create();

        $deletedProduct = Product::factory()->create();

        $deletedProduct->delete();

        $collection->products()->attach([
            $activeProduct->id,
            $inactiveProduct->id,
            $draftProduct->id,
            $deletedProduct->id,
        ]);

        $this->getJson('/api/collections')
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $collection->id
            )
            ->assertJsonPath(
                'data.0.products_count',
                1
            );
    }

    public function test_public_collection_can_be_found_by_slug(): void
    {
        $collection = Collection::create([
            'name' => 'Featured',
            'slug' => 'featured',
            'description' => 'Featured collection',
            'image' => 'featured.jpg',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->getJson(
            "/api/collections/{$collection->slug}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $collection->id
            )
            ->assertJsonPath(
                'data.slug',
                $collection->slug
            )
            ->assertJsonPath(
                'data.name',
                'Featured'
            )
            ->assertJsonPath(
                'data.description',
                'Featured collection'
            )
            ->assertJsonPath(
                'data.image',
                'featured.jpg'
            )
            ->assertJsonPath(
                'data.is_active',
                true
            );
    }

    public function test_inactive_collection_is_not_publicly_visible_by_slug(): void
    {
        $collection = Collection::create([
            'name' => 'Hidden',
            'slug' => 'hidden',
            'is_active' => false,
            'sort_order' => 1,
        ]);

        $this->getJson(
            "/api/collections/{$collection->slug}"
        )->assertNotFound();
    }

    public function test_missing_collection_slug_returns_404(): void
    {
        $this->getJson(
            '/api/collections/missing-collection'
        )->assertNotFound();
    }

    public function test_collection_detail_returns_active_products_only(): void
    {
        $collection = Collection::create([
            'name' => 'Featured',
            'slug' => 'featured',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $activeProduct = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
            'slug' => 'active-product',
        ]);

        $inactiveProduct = Product::factory()
            ->inactive()
            ->create([
                'slug' => 'inactive-product',
            ]);

        $draftProduct = Product::factory()
            ->draft()
            ->create([
                'slug' => 'draft-product',
            ]);

        $collection->products()->attach([
            $activeProduct->id,
            $inactiveProduct->id,
            $draftProduct->id,
        ]);

        $response = $this->getJson(
            "/api/collections/{$collection->slug}"
        )->assertOk();

        $products = collect(
            $response->json('data.products')
        );

        $productIds = $products->pluck('id');

        $this->assertTrue(
            $productIds->contains($activeProduct->id)
        );

        $this->assertFalse(
            $productIds->contains($inactiveProduct->id)
        );

        $this->assertFalse(
            $productIds->contains($draftProduct->id)
        );

        $this->assertCount(
            1,
            $products
        );

        $this->assertSame(
            1,
            $response->json('data.products_count')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Security Hardening
    |--------------------------------------------------------------------------
    */

    public function test_collection_detail_does_not_expose_product_with_inactive_category(): void
    {
        $collection = Collection::create([
            'name' => 'Featured',
            'slug' => 'featured',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $activeCategory = Category::create([
            'parent_id' => null,
            'name' => [
                'en' => 'Cookware',
                'fa' => 'Cookware FA',
            ],
            'slug' => 'cookware',
            'description' => [
                'en' => 'Cookware',
                'fa' => 'Cookware FA',
            ],
            'image' => null,
            'sort_order' => 1,
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
        ]);

        $inactiveCategory = Category::create([
            'parent_id' => null,
            'name' => [
                'en' => 'Hidden Category',
                'fa' => 'Hidden Category FA',
            ],
            'slug' => 'hidden-category',
            'description' => [
                'en' => 'Hidden category',
                'fa' => 'Hidden category FA',
            ],
            'image' => null,
            'sort_order' => 2,
            'is_active' => false,
            'created_by' => null,
            'updated_by' => null,
        ]);

        $visibleProduct = Product::factory()->create([
            'category_id' => $activeCategory->id,
            'status' => ProductStatus::ACTIVE,
            'slug' => 'visible-product',
        ]);

        $hiddenProduct = Product::factory()->create([
            'category_id' => $inactiveCategory->id,
            'status' => ProductStatus::ACTIVE,
            'slug' => 'hidden-product',
        ]);

        $collection->products()->attach([
            $visibleProduct->id,
            $hiddenProduct->id,
        ]);

        $response = $this->getJson(
            "/api/collections/{$collection->slug}"
        )->assertOk();

        $products = collect(
            $response->json('data.products')
        );

        $productIds = $products->pluck('id');

        $this->assertTrue(
            $productIds->contains($visibleProduct->id)
        );

        $this->assertFalse(
            $productIds->contains($hiddenProduct->id)
        );

        $this->assertSame(
            1,
            $response->json('data.products_count')
        );
    }

    public function test_collection_detail_does_not_expose_inactive_related_collection(): void
    {
        $collection = Collection::create([
            'name' => 'Featured',
            'slug' => 'featured',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $inactiveRelatedCollection = Collection::create([
            'name' => 'Hidden Collection',
            'slug' => 'hidden-collection',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $product = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
        ]);

        $collection->products()->attach(
            $product->id
        );

        $inactiveRelatedCollection->products()->attach(
            $product->id
        );

        $response = $this->getJson(
            "/api/collections/{$collection->slug}"
        )->assertOk();

        $productData = collect(
            $response->json('data.products')
        )->firstWhere(
            'id',
            $product->id
        );

        $this->assertNotNull(
            $productData
        );

        $relatedCollections = collect(
            $productData['collections'] ?? []
        );

        $relatedCollectionIds = $relatedCollections->pluck(
            'id'
        );

        $relatedCollectionSlugs = $relatedCollections->pluck(
            'slug'
        );

        $this->assertTrue(
            $relatedCollectionIds->contains(
                $collection->id
            )
        );

        $this->assertFalse(
            $relatedCollectionIds->contains(
                $inactiveRelatedCollection->id
            )
        );

        $this->assertTrue(
            $relatedCollectionSlugs->contains(
                $collection->slug
            )
        );

        $this->assertFalse(
            $relatedCollectionSlugs->contains(
                $inactiveRelatedCollection->slug
            )
        );
    }

    public function test_collection_detail_does_not_expose_sold_or_inactive_variants(): void
    {
        $collection = Collection::create([
            'name' => 'Featured',
            'slug' => 'featured',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $product = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
        ]);

        $activeVariant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
            'price' => 100,
        ]);

        $madeToOrderVariant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::MADE_TO_ORDER,
            'price' => 150,
        ]);

        $soldVariant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::SOLD,
            'price' => 200,
        ]);

        $inactiveVariant = ProductVariant::factory()
            ->inactive()
            ->create([
                'product_id' => $product->id,
                'price' => 250,
            ]);

        Inventory::factory()->create([
            'product_variant_id' => $activeVariant->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
        ]);

        $collection->products()->attach(
            $product->id
        );

        $response = $this->getJson(
            "/api/collections/{$collection->slug}"
        )->assertOk();

        $productData = collect(
            $response->json('data.products')
        )->firstWhere(
            'id',
            $product->id
        );

        $this->assertNotNull(
            $productData
        );

        $variantIds = collect(
            $productData['variants'] ?? []
        )->pluck('id');

        $this->assertTrue(
            $variantIds->contains($activeVariant->id)
        );

        $this->assertTrue(
            $variantIds->contains($madeToOrderVariant->id)
        );

        $this->assertFalse(
            $variantIds->contains($soldVariant->id)
        );

        $this->assertFalse(
            $variantIds->contains($inactiveVariant->id)
        );
    }

    public function test_collection_product_count_ignores_soft_deleted_products(): void
    {
        $collection = Collection::create([
            'name' => 'Featured',
            'slug' => 'featured',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $product = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
        ]);

        $collection->products()->attach(
            $product->id
        );

        $product->delete();

        $this->getJson('/api/collections')
            ->assertOk()
            ->assertJsonPath(
                'data.0.products_count',
                0
            );
    }

    public function test_public_collection_pagination_respects_per_page(): void
    {
        Collection::create([
            'name' => 'First',
            'slug' => 'first',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Collection::create([
            'name' => 'Second',
            'slug' => 'second',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        Collection::create([
            'name' => 'Third',
            'slug' => 'third',
            'is_active' => true,
            'sort_order' => 3,
        ]);

        $this->getJson(
            '/api/collections?per_page=2'
        )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data'
            )
            ->assertJsonPath(
                'meta.per_page',
                2
            );
    }

    public function test_public_collection_localized_fields_use_current_locale(): void
    {
        $collection = Collection::create([
            'name' => [
                'en' => 'Featured',
                'fa' => 'Featured FA',
            ],

            'description' => [
                'en' => 'Featured collection',
                'fa' => 'Featured collection FA',
            ],

            'slug' => 'featured',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->getJson(
            "/api/collections/{$collection->slug}?locale=fa"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Featured FA'
            )
            ->assertJsonPath(
                'data.description',
                'Featured collection FA'
            );
    }

    public function test_collection_detail_returns_products_count_and_products_relationship(): void
    {
        $collection = Collection::create([
            'name' => 'Featured',
            'slug' => 'featured',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $productOne = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
        ]);

        $productTwo = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
        ]);

        $collection->products()->attach([
            $productOne->id,
            $productTwo->id,
        ]);

        $response = $this->getJson(
            "/api/collections/{$collection->slug}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.products_count',
                2
            )
            ->assertJsonCount(
                2,
                'data.products'
            );

        $returnedProductIds = collect(
            $response->json('data.products')
        )->pluck('id');

        $this->assertTrue(
            $returnedProductIds->contains($productOne->id)
        );

        $this->assertTrue(
            $returnedProductIds->contains($productTwo->id)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    */

    public function test_public_collection_listing_avoids_n_plus_one_queries(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Collection::create([
                'name' => "Performance Collection {$i}",
                'slug' => "performance-collection-{$i}",
                'description' => "Performance collection {$i}",
                'image' => null,
                'is_active' => true,
                'sort_order' => $i,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson(
            '/api/collections?per_page=5'
        )->assertOk();

        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        dump(
            'COLLECTION LIST QUERY COUNT:',
            count($queries)
        );

        foreach ($queries as $index => $query) {
            dump([
                'query_number' => $index + 1,
                'query' => $query['query'],
                'bindings' => $query['bindings'],
                'time_ms' => $query['time'],
            ]);
        }

        $this->assertCount(
            5,
            $response->json('data')
        );

        /*
         * Cache may make the endpoint execute zero SQL queries
         * after the first request. The upper bound remains useful
         * for an uncached execution.
         */
        $this->assertLessThanOrEqual(
            4,
            count($queries)
        );
    }

    public function test_public_collection_detail_avoids_n_plus_one_queries_with_relations(): void
    {
        $collection = Collection::create([
            'name' => 'Performance Collection',
            'slug' => 'performance-collection',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $category = Category::factory()->create([
            'is_active' => true,
        ]);

        $relatedCollection = Collection::create([
            'name' => 'Performance Related Collection',
            'slug' => 'performance-related-collection',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $attribute = Attribute::create([
            'name' => 'Color',
            'slug' => 'color',
            'type' => 'select',
        ]);

        $red = AttributeValue::create([
            'attribute_id' => $attribute->id,
            'value' => 'Red',
        ]);

        $blue = AttributeValue::create([
            'attribute_id' => $attribute->id,
            'value' => 'Blue',
        ]);

        $products = Product::factory()
            ->count(5)
            ->create([
                'category_id' => $category->id,
                'status' => ProductStatus::ACTIVE,
            ]);

        foreach ($products as $product) {
            $collection->products()->attach(
                $product->id
            );

            $product->collections()->attach(
                $relatedCollection->id
            );

            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => "products/{$product->id}-primary.jpg",
                'sort_order' => 1,
                'is_primary' => true,
            ]);

            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => "products/{$product->id}-second.jpg",
                'sort_order' => 2,
                'is_primary' => false,
            ]);

            $variantOne = ProductVariant::factory()->create([
                'product_id' => $product->id,
                'status' => VariantStatus::ACTIVE,
                'price' => 90,
            ]);

            $variantTwo = ProductVariant::factory()->create([
                'product_id' => $product->id,
                'status' => VariantStatus::MADE_TO_ORDER,
                'price' => 110,
            ]);

            $variantOne->attributeValues()->attach(
                $red->id
            );

            $variantTwo->attributeValues()->attach(
                $blue->id
            );

            ProductImage::create([
                'product_id' => $product->id,
                'product_variant_id' => $variantOne->id,
                'image_path' => "variants/{$variantOne->id}-primary.jpg",
                'sort_order' => 1,
                'is_primary' => true,
            ]);

            Inventory::factory()->create([
                'product_variant_id' => $variantOne->id,
                'quantity' => 20,
                'reserved_quantity' => 3,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson(
            "/api/collections/{$collection->slug}"
        );

        $response->assertOk();

        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        dump(
            'COLLECTION DETAIL QUERY COUNT:',
            count($queries)
        );

        foreach ($queries as $index => $query) {
            dump([
                'query_number' => $index + 1,
                'query' => $query['query'],
                'bindings' => $query['bindings'],
                'time_ms' => $query['time'],
            ]);
        }

        $this->assertCount(
            5,
            $response->json('data.products')
        );

        $this->assertLessThanOrEqual(
            14,
            count($queries)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Activity Logging Helpers
    |--------------------------------------------------------------------------
    */

    private function collectionAdmin(): User
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
            'email' =>
                'collection-admin-'
                . uniqid()
                . '@tkb.test',
        ]);

        $role = Role::create([
            'name' => 'Collection Admin',
            'slug' =>
                'collection-admin-'
                . uniqid(),
        ]);

        $permissions = collect([
            'collections.create',
            'collections.update',
            'collections.delete',
        ])->map(
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
            $permissions
        );

        $user->roles()->sync([
            $role->id,
        ]);

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | Activity Logging
    |--------------------------------------------------------------------------
    */

    public function test_creating_collection_creates_activity_log(): void
    {
        $admin = $this->collectionAdmin();

        /*
         * Warm the public index cache.
         */
        $this->getJson(
            '/api/collections'
        )->assertOk();

        $versionBefore =
            $this->currentCollectionIndexCacheVersion();

        Sanctum::actingAs($admin);

        $response = $this->postJson(
            '/api/admin/collections',
            [
                'name' => [
                    'en' => 'New Collection',
                    'fa' => 'New Collection',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ]
        )->assertCreated();

        /*
         * Collection creation must invalidate
         * the public collection index cache.
         */
        $this->assertSame(
            $versionBefore + 1,
            $this->currentCollectionIndexCacheVersion()
        );

        $collectionId = $response->json(
            'data.id'
        );

        $log = ActivityLog::query()
            ->where(
                'action',
                'collections.created'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $admin->id,
            $log->user_id
        );

        $this->assertSame(
            'collections.created',
            $log->action
        );

        $this->assertSame(
            'Collection created.',
            $log->description
        );

        $this->assertSame(
            Collection::class,
            $log->properties['subject']['type']
        );

        $this->assertSame(
            $collectionId,
            $log->properties['subject']['id']
        );

        $this->assertSame(
            $collectionId,
            $log->properties['collection']['id']
        );

        $this->assertSame(
            'new-collection',
            $log->properties['collection']['slug']
        );

        $this->assertSame(
            [],
            $log->properties['product_ids']
        );
    }

    public function test_updating_collection_creates_activity_log(): void
    {
        $admin = $this->collectionAdmin();

        $collection = Collection::create([
            'name' => [
                'en' => 'Old Collection',
                'fa' => 'Old Collection',
            ],
            'slug' => 'old-collection',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        /*
         * Warm the public index cache.
         */
        $this->getJson(
            '/api/collections'
        )->assertOk();

        $versionBefore =
            $this->currentCollectionIndexCacheVersion();

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/admin/collections/{$collection->id}",
            [
                'is_active' => false,
            ]
        )->assertOk();

        /*
         * Collection update must invalidate
         * the public collection index cache.
         */
        $this->assertSame(
            $versionBefore + 1,
            $this->currentCollectionIndexCacheVersion()
        );

        $log = ActivityLog::query()
            ->where(
                'action',
                'collections.updated'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $admin->id,
            $log->user_id
        );

        $this->assertSame(
            'collections.updated',
            $log->action
        );

        $this->assertSame(
            Collection::class,
            $log->properties['subject']['type']
        );

        $this->assertSame(
            $collection->id,
            $log->properties['subject']['id']
        );

        $this->assertContains(
            'is_active',
            $log->properties['changed_fields']
        );

        $this->assertFalse(
            $log->properties['changes']['is_active']
        );

        $this->assertFalse(
            $log->properties['products_updated']
        );

        $this->assertNull(
            $log->properties['product_ids']
        );
    }

    public function test_updating_collection_products_creates_activity_log(): void
    {
        $admin = $this->collectionAdmin();

        $collection = Collection::create([
            'name' => 'Products Collection',
            'slug' => 'products-collection',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $product = Product::factory()->create();

        /*
         * Warm the public index cache before relation update.
         */
        $this->getJson(
            '/api/collections'
        )->assertOk();

        $versionBefore =
            $this->currentCollectionIndexCacheVersion();

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/admin/collections/{$collection->id}",
            [
                'product_ids' => [
                    $product->id,
                ],
            ]
        )->assertOk();

        /*
         * Changing collection products changes products_count,
         * so it must invalidate the public index cache.
         */
        $this->assertSame(
            $versionBefore + 1,
            $this->currentCollectionIndexCacheVersion()
        );

        $log = ActivityLog::query()
            ->where(
                'action',
                'collections.updated'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $admin->id,
            $log->user_id
        );

        $this->assertSame(
            $collection->id,
            $log->properties['subject']['id']
        );

        $this->assertTrue(
            $log->properties['products_updated']
        );

        $this->assertSame(
            [
                $product->id,
            ],
            $log->properties['product_ids']
        );

        $this->assertIsArray(
            $log->properties['changed_fields']
        );
    }

    public function test_updating_collection_with_product_ids_only_does_not_fake_model_changes(): void
    {
        $admin = $this->collectionAdmin();

        $collection = Collection::create([
            'name' => 'Products Only Collection',
            'slug' => 'products-only-collection',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $product = Product::factory()->create();

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/admin/collections/{$collection->id}",
            [
                'product_ids' => [
                    $product->id,
                ],
            ]
        )->assertOk();

        $log = ActivityLog::query()
            ->where(
                'action',
                'collections.updated'
            )
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            [],
            $log->properties['changed_fields']
        );

        $this->assertTrue(
            $log->properties['products_updated']
        );
    }

    public function test_deleting_collection_creates_activity_log(): void
    {
        $admin = $this->collectionAdmin();

        $collection = Collection::create([
            'name' => 'Delete Collection',
            'slug' => 'delete-collection',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        /*
         * Warm the public index cache before deletion.
         */
        $this->getJson(
            '/api/collections'
        )->assertOk();

        $versionBefore =
            $this->currentCollectionIndexCacheVersion();

        Sanctum::actingAs($admin);

        $this->deleteJson(
            "/api/admin/collections/{$collection->id}"
        )->assertOk();

        /*
         * Collection deletion must invalidate
         * the public collection index cache.
         */
        $this->assertSame(
            $versionBefore + 1,
            $this->currentCollectionIndexCacheVersion()
        );

        $log = ActivityLog::query()
            ->where(
                'action',
                'collections.deleted'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $admin->id,
            $log->user_id
        );

        $this->assertSame(
            'collections.deleted',
            $log->action
        );

        $this->assertSame(
            Collection::class,
            $log->properties['subject']['type']
        );

        $this->assertSame(
            $collection->id,
            $log->properties['subject']['id']
        );

        $this->assertSame(
            'delete-collection',
            $log->properties['collection']['slug']
        );
    }
}