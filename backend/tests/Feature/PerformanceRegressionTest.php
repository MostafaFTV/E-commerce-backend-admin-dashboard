<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Collection;
use App\Models\HomepageSection;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceRegressionTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Performance Baselines
    |--------------------------------------------------------------------------
    |
    | These limits are based on the current optimized API behavior.
    | They are intentionally explicit so accidental N+1 regressions
    | are caught automatically.
    |
    */

    private const PRODUCT_LIST_MAX_QUERIES = 5;

    private const PRODUCT_SIMPLE_PAGINATION_QUERY_COUNT = 4;

    private const PRODUCT_DETAIL_MAX_QUERIES = 9;

    private const COLLECTION_LIST_MAX_QUERIES = 4;

    private const COLLECTION_DETAIL_MAX_QUERIES = 14;

    private const HOMEPAGE_CACHE_MISS_MAX_QUERIES = 4;

    /*
    |--------------------------------------------------------------------------
    | Product Performance
    |--------------------------------------------------------------------------
    */

    public function test_public_product_listing_query_count_stays_within_baseline(): void
    {
        $category = Category::factory()->create([
            'is_active' => true,
        ]);

        Product::factory()
            ->count(5)
            ->create([
                'category_id' => $category->id,
                'status' => ProductStatus::ACTIVE,
            ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson(
            '/api/products?per_page=5'
        );

        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $response->assertOk();

        $this->assertCount(
            5,
            $response->json('data')
        );

        $this->assertLessThanOrEqual(
            self::PRODUCT_LIST_MAX_QUERIES,
            count($queries),
            sprintf(
                'Product listing executed %d queries; expected <= %d.',
                count($queries),
                self::PRODUCT_LIST_MAX_QUERIES
            )
        );
    }

    public function test_public_product_simple_pagination_keeps_query_count_at_baseline(): void
    {
        $category = Category::factory()->create([
            'is_active' => true,
        ]);

        Product::factory()
            ->count(5)
            ->create([
                'category_id' => $category->id,
                'status' => ProductStatus::ACTIVE,
            ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson(
            '/api/products?per_page=2&page=1'
        );

        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $response->assertOk();

        $this->assertCount(
            2,
            $response->json('data')
        );

        $this->assertCount(
            self::PRODUCT_SIMPLE_PAGINATION_QUERY_COUNT,
            $queries,
            sprintf(
                'Product simple pagination executed %d queries; expected exactly %d.',
                count($queries),
                self::PRODUCT_SIMPLE_PAGINATION_QUERY_COUNT
            )
        );

        foreach ($queries as $query) {
            $this->assertStringNotContainsStringIgnoringCase(
                'count(*)',
                $query['query'],
                'Product simple pagination must not execute a COUNT(*) query.'
            );
        }
    }

    public function test_public_product_detail_query_count_stays_within_baseline(): void
    {
        $category = Category::factory()->create([
            'name' => [
                'en' => 'Tea',
                'fa' => 'Tea FA',
            ],
            'is_active' => true,
        ]);

        $collection = Collection::create([
            'name' => 'Featured',
            'slug' => 'performance-regression-featured',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => ProductStatus::ACTIVE,
            'price' => 100,
        ]);

        $attribute = Attribute::create([
            'name' => 'Color',
            'slug' => 'performance-regression-color',
            'type' => 'select',
        ]);

        $value = AttributeValue::create([
            'attribute_id' => $attribute->id,
            'value' => 'Red',
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
            'price' => null,
            'sort_order' => 1,
        ]);

        $product->collections()->attach(
            $collection->id
        );

        $variant->attributeValues()->attach(
            $value->id
        );

        Inventory::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 8,
            'reserved_quantity' => 3,
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'performance-product-primary.jpg',
            'sort_order' => 1,
            'is_primary' => true,
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'image_path' => 'performance-variant-primary.jpg',
            'sort_order' => 1,
            'is_primary' => true,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson(
            "/api/products/{$product->slug}"
        );

        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $response->assertOk();

        $this->assertCount(
            1,
            $response->json('data.variants')
        );

        $this->assertGreaterThan(
            0,
            count($queries),
            'Product detail should execute database queries on a cache miss.'
        );

        $this->assertLessThanOrEqual(
            self::PRODUCT_DETAIL_MAX_QUERIES,
            count($queries),
            sprintf(
                'Product detail executed %d queries; expected <= %d.',
                count($queries),
                self::PRODUCT_DETAIL_MAX_QUERIES
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Collection Performance
    |--------------------------------------------------------------------------
    */

    public function test_public_collection_listing_query_count_stays_within_baseline(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Collection::create([
                'name' => "Performance Regression Collection {$i}",
                'slug' => "performance-regression-collection-{$i}",
                'description' => "Performance regression collection {$i}",
                'image' => null,
                'is_active' => true,
                'sort_order' => $i,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson(
            '/api/collections?per_page=5'
        );

        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $response->assertOk();

        $this->assertCount(
            5,
            $response->json('data')
        );

        $this->assertLessThanOrEqual(
            self::COLLECTION_LIST_MAX_QUERIES,
            count($queries),
            sprintf(
                'Collection listing executed %d queries; expected <= %d.',
                count($queries),
                self::COLLECTION_LIST_MAX_QUERIES
            )
        );
    }

    public function test_public_collection_detail_query_count_stays_within_baseline(): void
    {
        $collection = Collection::create([
            'name' => 'Performance Collection',
            'slug' => 'performance-regression-detail-collection',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $category = Category::factory()->create([
            'is_active' => true,
        ]);

        $relatedCollection = Collection::create([
            'name' => 'Performance Related Collection',
            'slug' => 'performance-regression-related-collection',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $attribute = Attribute::create([
            'name' => 'Color',
            'slug' => 'performance-regression-color',
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

        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $response->assertOk();

        $this->assertCount(
            5,
            $response->json('data.products')
        );

        $this->assertGreaterThan(
            0,
            count($queries),
            'Collection detail should execute database queries on a cache miss.'
        );

        $this->assertLessThanOrEqual(
            self::COLLECTION_DETAIL_MAX_QUERIES,
            count($queries),
            sprintf(
                'Collection detail executed %d queries; expected <= %d.',
                count($queries),
                self::COLLECTION_DETAIL_MAX_QUERIES
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Homepage Performance
    |--------------------------------------------------------------------------
    */

    public function test_homepage_cache_hit_executes_zero_database_queries(): void
    {
        $cache = Cache::store(config('cache.default'));

        $cacheKey = 'tkb:homepage:v2:en';

        $cache->put(
            $cacheKey,
            [
                'data' => [
                    'banners' => [],
                    'sections' => [],
                ],
            ],
            60
        );

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson(
            '/api/homepage'
        );

        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $response->assertOk();

        $this->assertCount(
            0,
            $queries,
            'Homepage array cache hit must execute zero database queries.'
        );

        $cache->forget($cacheKey);
    }

    public function test_homepage_cache_miss_stays_within_query_budget(): void
    {
        HomepageSection::query()->delete();

        HomepageSection::create([
            'section_key' => 'performance-hero',
            'title' => [
                'en' => 'Performance Hero',
                'fa' => 'Performance Hero',
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $cache = Cache::store(config('cache.default'));

        $cacheKey = 'tkb:homepage:v2:en';

        $cache->forget($cacheKey);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson(
            '/api/homepage'
        );

        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $response->assertOk();

        $queryCount = count($queries);

        $this->assertGreaterThan(
            0,
            $queryCount,
            'Homepage cache miss must execute database queries.'
        );

        $this->assertLessThanOrEqual(
            self::HOMEPAGE_CACHE_MISS_MAX_QUERIES,
            $queryCount,
            sprintf(
                'Homepage cache miss executed %d queries; expected <= %d.',
                $queryCount,
                self::HOMEPAGE_CACHE_MISS_MAX_QUERIES
            )
        );

        $cache->forget($cacheKey);
    }

    /*
    |--------------------------------------------------------------------------
    | Redis Performance Infrastructure
    |--------------------------------------------------------------------------
    */

    public function test_default_cache_is_available_for_performance_regressions(): void
    {
        $cache = Cache::store(config('cache.default'));

        $cacheKey = 'tkb:performance-regression:test';

        $cache->put(
            $cacheKey,
            ['status' => 'ok'],
            60
        );

        $this->assertSame(
            ['status' => 'ok'],
            $cache->get($cacheKey)
        );

        $cache->forget($cacheKey);

        $this->assertNull(
            $cache->get($cacheKey)
        );
    }
}