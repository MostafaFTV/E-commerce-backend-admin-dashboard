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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Public Listing
    |--------------------------------------------------------------------------
    */

    public function test_public_listing_returns_active_products_only(): void
    {
        $active = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
        ]);

        $inactive = Product::factory()
            ->inactive()
            ->create();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonMissing([
                'id' => $inactive->id,
            ]);
    }

    public function test_public_listing_includes_made_to_order_variant_state(): void
    {
        $product = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
            'price' => 100,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::MADE_TO_ORDER,
            'price' => 125,
        ]);

        $response = $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath(
                'data.0.has_made_to_order_variant',
                true
            )
            ->assertJsonPath(
                'data.0.has_purchasable_variant',
                true
            )
            ->assertJsonPath(
                'data.0.can_purchase',
                true
            );

        $productData = $response->json('data.0');

        $this->assertArrayNotHasKey(
            'variants',
            $productData
        );
    }

    public function test_product_discount_calculates_prices_for_purchasable_variants_only(): void
    {
        $product = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
            'price' => 1000,
            'discount_percentage' => 20,
        ]);

        $active = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
            'price' => 1200,
        ]);
        $madeToOrder = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::MADE_TO_ORDER,
            'price' => null,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::SOLD,
            'price' => 250,
        ]);
        ProductVariant::factory()->inactive()->create([
            'product_id' => $product->id,
            'price' => 300,
        ]);

        $listing = $this->getJson('/api/products?sort=price_asc')
            ->assertOk()
            ->json('data');
        $listedProduct = collect($listing)->firstWhere('id', $product->id);

        $this->assertEquals(1000, $listedProduct['price']);
        $this->assertEquals(800, $listedProduct['discounted_price']);
        $this->assertSame(20, $listedProduct['discount_percentage']);
        $this->assertEquals(960, $listedProduct['max_discounted_price']);

        $detail = $this->getJson("/api/products/{$product->slug}")
            ->assertOk()
            ->json('data');

        $this->assertEquals(800, $detail['discounted_price']);
        $variants = collect($detail['variants'])->keyBy('id');
        $this->assertEquals(960, $variants[$active->id]['discounted_price']);
        $this->assertEquals(800, $variants[$madeToOrder->id]['discounted_price']);
        $this->assertCount(2, $variants);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'price' => 1000,
            'discount_percentage' => 20,
        ]);
    }

    public function test_product_discount_is_not_returned_for_zero_price_or_non_purchasable_variants(): void
    {
        $zeroPrice = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
            'price' => 0,
            'discount_percentage' => 50,
        ]);
        $soldOnly = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
            'price' => 1000,
            'discount_percentage' => 50,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $soldOnly->id,
            'status' => VariantStatus::SOLD,
            'price' => 500,
        ]);

        $listing = $this->getJson('/api/products')
            ->assertOk()
            ->json('data');
        $zeroData = collect($listing)->firstWhere('id', $zeroPrice->id);
        $soldData = collect($listing)->firstWhere('id', $soldOnly->id);

        $this->assertNull($zeroData['discounted_price']);
        $this->assertNull($zeroData['discount_percentage']);
        $this->assertNull($soldData['discounted_price']);
        $this->assertNull($soldData['discount_percentage']);
    }

    public function test_product_discount_sorting_uses_the_discounted_listing_price(): void
    {
        $discounted = Product::factory()->create([
            'slug' => 'discounted-sort',
            'status' => ProductStatus::ACTIVE,
            'price' => 1000,
            'discount_percentage' => 50,
        ]);
        Product::factory()->create([
            'slug' => 'regular-sort',
            'status' => ProductStatus::ACTIVE,
            'price' => 600,
        ]);

        $response = $this->getJson('/api/products?sort=price_asc')
            ->assertOk();

        $this->assertSame(
            $discounted->id,
            $response->json('data.0.id')
        );
        $this->assertEquals(500, $response->json('data.0.discounted_price'));
    }

    public function test_public_listing_excludes_sold_and_inactive_variants(): void
    {
        $product = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::SOLD,
            'price' => 50,
        ]);

        ProductVariant::factory()
            ->inactive()
            ->create([
                'product_id' => $product->id,
                'price' => 60,
            ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
            'price' => 100,
        ]);

        $response = $this->getJson(
            "/api/products?category_id={$product->category_id}"
        )->assertOk();

        $productData = collect(
            $response->json('data')
        )->firstWhere(
            'id',
            $product->id
        );

        $this->assertNotNull($productData);

        $this->assertTrue(
            $productData['has_purchasable_variant']
        );

        $this->assertFalse(
            $productData['has_made_to_order_variant']
        );

        $this->assertTrue(
            $productData['can_purchase']
        );

        $this->assertArrayNotHasKey(
            'variants',
            $productData
        );
    }

    public function test_public_search_normalizes_persian_spacing_and_zero_width_non_joiner(): void
    {
        $product = Product::factory()->create([
            'name' => [
                'en' => 'Espresso Maker',
                'fa' => 'اسپرسوساز',
            ],
            'slug' => 'espresso-maker-search-normalization',
            'status' => ProductStatus::ACTIVE,
        ]);

        $queries = [
            'اسپرسوساز',
            'اسپرسو ساز',
            "اسپرسو\u{200C}ساز",
        ];

        foreach ($queries as $search) {
            $response = $this->getJson(
                '/api/products?locale=fa&sort=relevance&search=' .
                rawurlencode($search)
            )->assertOk();

            $ids = collect(
                $response->json('data')
            )->pluck('id');

            $this->assertTrue(
                $ids->contains($product->id),
                "Failed to match Persian search query: {$search}"
            );
        }
    }

    public function test_public_search_tolerates_single_persian_typo(): void
    {
        $product = Product::factory()->create([
            'name' => [
                'en' => 'Espresso Maker',
                'fa' => 'اسپرسوساز',
            ],
            'slug' => 'espresso-maker-search-typo',
            'status' => ProductStatus::ACTIVE,
        ]);

        $response = $this->getJson(
            '/api/products?locale=fa&sort=relevance&search=' .
            rawurlencode('اسپرسوسار')
        )->assertOk();

        $ids = collect(
            $response->json('data')
        )->pluck('id');

        $this->assertTrue(
            $ids->contains($product->id)
        );
    }

    public function test_public_search_does_not_fuzzy_match_unrelated_persian_query(): void
    {
        $product = Product::factory()->create([
            'name' => [
                'en' => 'Espresso Maker',
                'fa' => 'اسپرسوساز',
            ],
            'slug' => 'espresso-maker-search-negative',
            'status' => ProductStatus::ACTIVE,
        ]);

        $response = $this->getJson(
            '/api/products?locale=fa&sort=relevance&search=' .
            rawurlencode('چاقوی آشپزخانه')
        )->assertOk();

        $ids = collect(
            $response->json('data')
        )->pluck('id');

        $this->assertFalse(
            $ids->contains($product->id)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Public Detail
    |--------------------------------------------------------------------------
    */

    public function test_public_search_orders_exact_name_before_prefix_and_contains_matches(): void
    {
        $exact = Product::factory()->create([
            'name' => [
                'en' => 'Coffee Maker',
                'fa' => 'قهوه ساز',
            ],
            'slug' => 'coffee-maker-relevance-exact',
            'status' => ProductStatus::ACTIVE,
        ]);

        $prefix = Product::factory()->create([
            'name' => [
                'en' => 'Coffee Maker Pro',
                'fa' => 'قهوه ساز حرفه‌ای',
            ],
            'slug' => 'coffee-maker-relevance-prefix',
            'status' => ProductStatus::ACTIVE,
        ]);

        $contains = Product::factory()->create([
            'name' => [
                'en' => 'Premium Coffee Maker Bundle',
                'fa' => 'پکیج قهوه ساز پریمیوم',
            ],
            'slug' => 'coffee-maker-relevance-contains',
            'status' => ProductStatus::ACTIVE,
        ]);

        /*
         * Deliberately make weaker matches newer.
         * Relevance must win over created_at.
         */
        \Illuminate\Support\Facades\DB::table('products')
            ->where('id', $exact->id)
            ->update([
                'created_at' => '2026-01-01 00:00:00',
            ]);

        \Illuminate\Support\Facades\DB::table('products')
            ->where('id', $prefix->id)
            ->update([
                'created_at' => '2026-02-01 00:00:00',
            ]);

        \Illuminate\Support\Facades\DB::table('products')
            ->where('id', $contains->id)
            ->update([
                'created_at' => '2026-03-01 00:00:00',
            ]);

        $response = $this->getJson(
            '/api/products?locale=en&sort=relevance&search=' .
            rawurlencode('Coffee Maker')
        )->assertOk();

        $ids = collect(
            $response->json('data')
        )
            ->pluck('id')
            ->take(3)
            ->values()
            ->all();

        $this->assertSame(
            [
                $exact->id,
                $prefix->id,
                $contains->id,
            ],
            $ids
        );
    }
    public function test_public_detail_is_found_by_slug_and_missing_slug_is_404(): void
    {
        $product = Product::factory()->create([
            'slug' => 'classic-teapot',
        ]);

        $this->getJson(
            "/api/products/{$product->slug}"
        )
            ->assertOk()
            ->assertJsonPath('data.id', $product->id);

        $this->getJson(
            '/api/products/missing-product'
        )->assertNotFound();
    }

    public function test_public_detail_returns_localized_product_data(): void
    {
        $product = Product::factory()->create([
            'name' => [
                'en' => 'Classic Teapot',
                'fa' => 'Classic Teapot FA',
            ],
            'short_description' => [
                'en' => 'Classic teapot',
                'fa' => 'Classic teapot FA',
            ],
            'description' => [
                'en' => 'Luxury classic teapot',
                'fa' => 'Luxury classic teapot FA',
            ],
            'status' => ProductStatus::ACTIVE,
        ]);

        $response = $this
            ->withHeader('Accept-Language', 'fa')
            ->getJson(
                "/api/products/{$product->slug}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Classic Teapot FA'
            )
            ->assertJsonPath(
                'data.short_description',
                'Classic teapot FA'
            )
            ->assertJsonPath(
                'data.description',
                'Luxury classic teapot FA'
            );
    }

    public function test_public_detail_includes_category_collection_images_and_variant_details(): void
    {
        $category = Category::factory()->create([
            'name' => [
                'en' => 'Tea',
                'fa' => 'Tea FA',
            ],
        ]);

        $collection = Collection::create([
            'name' => 'Featured',
            'slug' => 'featured',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 100,
        ]);

        $attribute = Attribute::create([
            'name' => 'Color',
            'slug' => 'color',
            'type' => 'select',
        ]);

        $value = AttributeValue::create([
            'attribute_id' => $attribute->id,
            'value' => 'Red',
        ]);

        $activeVariant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
            'price' => null,
            'sort_order' => 1,
        ]);

        ProductVariant::factory()
            ->inactive()
            ->create([
                'product_id' => $product->id,
                'price' => 1,
                'sort_order' => 2,
            ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::SOLD,
            'price' => 70,
            'sort_order' => 3,
        ]);

        $product->collections()->attach($collection);

        $activeVariant->attributeValues()->attach($value);

        Inventory::factory()->create([
            'product_variant_id' => $activeVariant->id,
            'quantity' => 8,
            'reserved_quantity' => 3,
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'product-second.jpg',
            'sort_order' => 2,
            'is_primary' => false,
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'product-primary.jpg',
            'sort_order' => 1,
            'is_primary' => true,
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'product_variant_id' => $activeVariant->id,
            'image_path' => 'variant-primary.jpg',
            'sort_order' => 1,
            'is_primary' => true,
        ]);

        $response = $this->getJson(
            "/api/products/{$product->slug}"
        )->assertOk();

        $response
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.category.slug', $category->slug)
            ->assertJsonPath(
                'data.collections.0.id',
                $collection->id
            )
            ->assertJsonPath(
                'data.images.0.image_path',
                'product-primary.jpg'
            )
            ->assertJsonPath(
                'data.images.0.is_primary',
                true
            )
            ->assertJsonPath(
                'data.has_price_range',
                false
            )
            ->assertJsonPath(
                'data.has_made_to_order_variant',
                false
            )
            ->assertJsonPath(
                'data.total_inventory',
                null
            )
            ->assertJsonPath(
                'data.available_inventory',
                null
            )
            ->assertJsonPath(
                'data.has_available_inventory',
                true
            )
            ->assertJsonPath(
                'data.variants.0.id',
                $activeVariant->id
            )
            ->assertJsonPath(
                'data.variants.0.effective_price',
                100
            )
            ->assertJsonPath(
                'data.variants.0.attribute_values.0.id',
                $value->id
            )
            ->assertJsonPath(
                'data.variants.0.attribute_values.0.attribute.name',
                'Color'
            )
            ->assertJsonPath(
                'data.variants.0.images.0.image_path',
                'variant-primary.jpg'
            )
            ->assertJsonPath(
                'data.variants.0.images.0.is_primary',
                true
            )
            ->assertJsonCount(
                1,
                'data.variants'
            );

        $response
            ->assertJsonMissingPath(
                'data.variants.0.inventory.available_quantity'
            )
            ->assertJsonMissingPath(
                'data.variants.0.inventory.reserved_quantity'
            )
            ->assertJsonMissingPath(
                'data.variants.0.inventory.low_stock_threshold'
            )
            ->assertJsonPath(
                'data.variants.0.inventory.available',
                true
            );
    }

    public function test_public_detail_includes_made_to_order_variant_and_marks_product_correctly(): void
    {
        $product = Product::factory()->create([
            'price' => 100,
        ]);

        $activeVariant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
            'price' => 100,
        ]);

        Inventory::factory()->create([
            'product_variant_id' => $activeVariant->id,
            'quantity' => 5,
            'reserved_quantity' => 0,
        ]);

        $madeToOrderVariant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::MADE_TO_ORDER,
            'price' => 150,
        ]);

        $response = $this->getJson(
            "/api/products/{$product->slug}"
        )->assertOk();

        $response
            ->assertJsonPath(
                'data.has_made_to_order_variant',
                true
            )
            ->assertJsonPath(
                'data.min_price',
                100
            )
            ->assertJsonPath(
                'data.max_price',
                150
            )
            ->assertJsonPath(
                'data.has_price_range',
                true
            )
            ->assertJsonPath(
                'data.has_available_inventory',
                true
            )
            ->assertJsonCount(
                2,
                'data.variants'
            );

        $variants = collect(
            $response->json('data.variants')
        );

        $madeToOrder = $variants->firstWhere(
            'id',
            $madeToOrderVariant->id
        );

        $this->assertNotNull($madeToOrder);

        $this->assertSame(
            VariantStatus::MADE_TO_ORDER->value,
            $madeToOrder['status']
        );

        $this->assertSame(
            true,
            data_get(
                $madeToOrder,
                'inventory.available'
            )
        );

        $this->assertTrue(
            $madeToOrder['is_purchasable']
        );

        $this->assertArrayNotHasKey(
            'available_quantity',
            $madeToOrder['inventory']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | API Contract
    |--------------------------------------------------------------------------
    */

    public function test_public_made_to_order_variant_without_inventory_is_purchasable_and_has_available_inventory_state(): void
    {
        $product = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
            'price' => 100,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::MADE_TO_ORDER,
            'price' => 150,
        ]);

        $this->assertDatabaseMissing(
            'inventories',
            [
                'product_variant_id' => $variant->id,
            ]
        );

        $response = $this
            ->getJson(
                "/api/products/{$product->slug}"
            )
            ->assertOk();

        $variantData = collect(
            $response->json('data.variants')
        )->firstWhere(
            'id',
            $variant->id
        );

        $this->assertNotNull($variantData);

        $this->assertSame(
            VariantStatus::MADE_TO_ORDER->value,
            $variantData['status']
        );

        $this->assertTrue(
            $variantData['is_purchasable']
        );

        $this->assertSame(
            [
                'available' => true,
            ],
            $variantData['inventory']
        );

        $this->assertArrayNotHasKey(
            'available_quantity',
            $variantData['inventory']
        );

        $this->assertArrayNotHasKey(
            'reserved_quantity',
            $variantData['inventory']
        );

        $this->assertArrayNotHasKey(
            'low_stock_threshold',
            $variantData['inventory']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Product Visibility
    |--------------------------------------------------------------------------
    */

    public function test_inactive_draft_and_soft_deleted_products_are_not_publicly_visible(): void
    {
        $inactive = Product::factory()
            ->inactive()
            ->create([
                'slug' => 'inactive-detail',
            ]);

        $draft = Product::factory()
            ->draft()
            ->create([
                'slug' => 'draft-detail',
            ]);

        $deleted = Product::factory()->create([
            'slug' => 'deleted-detail',
        ]);

        $deleted->delete();

        $this->getJson(
            "/api/products/{$inactive->slug}"
        )->assertNotFound();

        $this->getJson(
            "/api/products/{$draft->slug}"
        )->assertNotFound();

        $this->getJson(
            '/api/products/deleted-detail'
        )->assertNotFound();
    }

    public function test_public_product_does_not_expose_inactive_category(): void
    {
        $inactiveCategory = Category::factory()->create([
            'is_active' => false,
        ]);

        $product = Product::factory()->create([
            'category_id' => $inactiveCategory->id,
            'status' => ProductStatus::ACTIVE,
        ]);

        $response = $this
            ->getJson(
                "/api/products/{$product->slug}"
            )
            ->assertOk();

        $response
            ->assertJsonPath(
                'data.id',
                $product->id
            )
            ->assertJsonMissingPath(
                'data.category'
            );
    }

    public function test_public_listing_excludes_products_from_inactive_category_filter(): void
    {
        $inactiveCategory = Category::factory()->create([
            'slug' => 'inactive-category',
            'is_active' => false,
        ]);

        $product = Product::factory()->create([
            'category_id' => $inactiveCategory->id,
            'status' => ProductStatus::ACTIVE,
        ]);

        $this
            ->getJson(
                "/api/products?category_id={$inactiveCategory->id}"
            )
            ->assertOk()
            ->assertJsonMissing([
                'id' => $product->id,
            ]);

        $this
            ->getJson(
                '/api/products?category=inactive-category'
            )
            ->assertOk()
            ->assertJsonMissing([
                'id' => $product->id,
            ]);
    }

    public function test_public_product_does_not_expose_inactive_collection(): void
    {
        $activeCategory = Category::factory()->create([
            'is_active' => true,
        ]);

        $activeCollection = Collection::create([
            'name' => 'Active Collection',
            'slug' => 'active-collection',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $inactiveCollection = Collection::create([
            'name' => 'Secret Collection',
            'slug' => 'secret-collection',
            'is_active' => false,
            'sort_order' => 1,
        ]);

        $product = Product::factory()->create([
            'category_id' => $activeCategory->id,
            'status' => ProductStatus::ACTIVE,
        ]);

        $product->collections()->attach([
            $activeCollection->id,
            $inactiveCollection->id,
        ]);

        $response = $this
            ->getJson(
                "/api/products/{$product->slug}"
            )
            ->assertOk();

        $collectionIds = collect(
            $response->json('data.collections')
        )->pluck('id');

        $this->assertTrue(
            $collectionIds->contains($activeCollection->id)
        );

        $this->assertFalse(
            $collectionIds->contains($inactiveCollection->id)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Guest / Authenticated Visibility
    |--------------------------------------------------------------------------
    */

    public function test_public_product_detail_is_same_for_guest_and_authenticated_customer(): void
    {
        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
        ]);

        Inventory::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 4,
            'reserved_quantity' => 1,
        ]);

        $guest = $this->getJson(
            "/api/products/{$product->slug}"
        )->assertOk();

        $customer = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $authenticated = $this
            ->actingAs($customer, 'sanctum')
            ->getJson(
                "/api/products/{$product->slug}"
            )
            ->assertOk();

        $this->assertSame(
            $guest->json('data.variants.0.inventory'),
            $authenticated->json(
                'data.variants.0.inventory'
            )
        );

        $authenticated
            ->assertJsonMissingPath(
                'data.variants.0.inventory.available_quantity'
            )
            ->assertJsonMissingPath(
                'data.variants.0.inventory.reserved_quantity'
            )
            ->assertJsonMissingPath(
                'data.variants.0.inventory.low_stock_threshold'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    */

    public function test_product_pricing_uses_active_and_made_to_order_variant_effective_prices(): void
    {
        $product = Product::factory()->create([
            'price' => 100,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
            'price' => null,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
            'price' => 80,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::MADE_TO_ORDER,
            'price' => 120,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::SOLD,
            'price' => 10,
        ]);

        ProductVariant::factory()
            ->inactive()
            ->create([
                'product_id' => $product->id,
                'price' => 20,
            ]);

        $this->getJson(
            "/api/products/{$product->slug}"
        )
            ->assertOk()
            ->assertJsonPath('data.price', 80)
            ->assertJsonPath('data.min_price', 80)
            ->assertJsonPath('data.max_price', 120)
            ->assertJsonPath('data.has_price_range', true);
    }

    public function test_product_without_variants_uses_base_price(): void
    {
        $product = Product::factory()->create([
            'price' => 100,
        ]);

        $this->getJson(
            "/api/products/{$product->slug}"
        )
            ->assertOk()
            ->assertJsonPath('data.price', 100)
            ->assertJsonPath('data.min_price', 100)
            ->assertJsonPath('data.max_price', 100)
            ->assertJsonPath('data.has_price_range', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Sorting
    |--------------------------------------------------------------------------
    */

    public function test_public_price_sorting_uses_effective_active_and_made_to_order_variant_price(): void
    {
        Product::factory()->create([
            'name' => [
                'en' => 'Low',
                'fa' => 'Low FA',
            ],
            'slug' => 'low',
            'price' => 100,
        ]);

        $high = Product::factory()->create([
            'name' => [
                'en' => 'High',
                'fa' => 'High FA',
            ],
            'slug' => 'high',
            'price' => 50,
        ]);

        ProductVariant::factory()->create([
            'product_id' => Product::where(
                'slug',
                'low'
            )->firstOrFail()->id,
            'status' => VariantStatus::MADE_TO_ORDER,
            'price' => 20,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $high->id,
            'status' => VariantStatus::ACTIVE,
            'price' => null,
        ]);

        ProductVariant::factory()
            ->inactive()
            ->create([
                'product_id' => $high->id,
                'price' => 1,
            ]);

        $this->getJson(
            '/api/products?sort=price_asc'
        )
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'low')
            ->assertJsonPath('data.1.slug', 'high');
    }

    /*
    |--------------------------------------------------------------------------
    | Filters
    |--------------------------------------------------------------------------
    */

    public function test_public_price_filter_returns_products_in_price_range(): void
    {
        $cheap = Product::factory()->create([
            'slug' => 'cheap',
            'price' => 40,
        ]);

        $medium = Product::factory()->create([
            'slug' => 'medium',
            'price' => 100,
        ]);

        $expensive = Product::factory()->create([
            'slug' => 'expensive',
            'price' => 200,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $cheap->id,
            'status' => VariantStatus::ACTIVE,
            'price' => 40,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $medium->id,
            'status' => VariantStatus::MADE_TO_ORDER,
            'price' => 120,
        ]);

        $response = $this->getJson(
            '/api/products?price_min=80&price_max=150'
        )->assertOk();

        $slugs = collect(
            $response->json('data')
        )->pluck('slug');

        $this->assertTrue(
            $slugs->contains('medium')
        );

        $this->assertFalse(
            $slugs->contains('cheap')
        );

        $this->assertFalse(
            $slugs->contains('expensive')
        );
    }

    public function test_public_color_filter_works(): void
    {
        $color = Attribute::create([
            'name' => 'Color',
            'slug' => 'color',
            'type' => 'select',
        ]);

        $red = AttributeValue::create([
            'attribute_id' => $color->id,
            'value' => 'Red',
        ]);

        $blue = AttributeValue::create([
            'attribute_id' => $color->id,
            'value' => 'Blue',
        ]);

        $redProduct = Product::factory()->create();
        $blueProduct = Product::factory()->create();

        $redVariant = ProductVariant::factory()->create([
            'product_id' => $redProduct->id,
            'status' => VariantStatus::ACTIVE,
        ]);

        $blueVariant = ProductVariant::factory()->create([
            'product_id' => $blueProduct->id,
            'status' => VariantStatus::ACTIVE,
        ]);

        $redVariant->attributeValues()->attach($red);
        $blueVariant->attributeValues()->attach($blue);

        $response = $this->getJson(
            '/api/products?color=Red'
        )->assertOk();

        $ids = collect(
            $response->json('data')
        )->pluck('id');

        $this->assertTrue(
            $ids->contains($redProduct->id)
        );

        $this->assertFalse(
            $ids->contains($blueProduct->id)
        );
    }

    public function test_public_size_filter_works(): void
    {
        $size = Attribute::create([
            'name' => 'Size',
            'slug' => 'size',
            'type' => 'select',
        ]);

        $small = AttributeValue::create([
            'attribute_id' => $size->id,
            'value' => 'Small',
        ]);

        $large = AttributeValue::create([
            'attribute_id' => $size->id,
            'value' => 'Large',
        ]);

        $smallProduct = Product::factory()->create();
        $largeProduct = Product::factory()->create();

        $smallVariant = ProductVariant::factory()->create([
            'product_id' => $smallProduct->id,
            'status' => VariantStatus::ACTIVE,
        ]);

        $largeVariant = ProductVariant::factory()->create([
            'product_id' => $largeProduct->id,
            'status' => VariantStatus::ACTIVE,
        ]);

        $smallVariant->attributeValues()->attach($small);
        $largeVariant->attributeValues()->attach($large);

        $response = $this->getJson(
            '/api/products?size=Large'
        )->assertOk();

        $ids = collect(
            $response->json('data')
        )->pluck('id');

        $this->assertTrue(
            $ids->contains($largeProduct->id)
        );

        $this->assertFalse(
            $ids->contains($smallProduct->id)
        );
    }

    public function test_public_material_filter_works(): void
    {
        $material = Attribute::create([
            'name' => 'Material',
            'slug' => 'material',
            'type' => 'select',
        ]);

        $steel = AttributeValue::create([
            'attribute_id' => $material->id,
            'value' => 'Steel',
        ]);

        $ceramic = AttributeValue::create([
            'attribute_id' => $material->id,
            'value' => 'Ceramic',
        ]);

        $steelProduct = Product::factory()->create();
        $ceramicProduct = Product::factory()->create();

        $steelVariant = ProductVariant::factory()->create([
            'product_id' => $steelProduct->id,
            'status' => VariantStatus::ACTIVE,
        ]);

        $ceramicVariant = ProductVariant::factory()->create([
            'product_id' => $ceramicProduct->id,
            'status' => VariantStatus::ACTIVE,
        ]);

        $steelVariant->attributeValues()->attach($steel);
        $ceramicVariant->attributeValues()->attach($ceramic);

        $response = $this->getJson(
            '/api/products?material=Steel'
        )->assertOk();

        $ids = collect(
            $response->json('data')
        )->pluck('id');

        $this->assertTrue(
            $ids->contains($steelProduct->id)
        );

        $this->assertFalse(
            $ids->contains($ceramicProduct->id)
        );
    }

    public function test_public_multiple_attribute_filters_work_together(): void
    {
        $color = Attribute::create([
            'name' => 'Color',
            'slug' => 'color',
            'type' => 'select',
        ]);

        $red = AttributeValue::create([
            'attribute_id' => $color->id,
            'value' => 'Red',
        ]);

        $blue = AttributeValue::create([
            'attribute_id' => $color->id,
            'value' => 'Blue',
        ]);

        $green = AttributeValue::create([
            'attribute_id' => $color->id,
            'value' => 'Green',
        ]);

        $redProduct = Product::factory()->create();
        $blueProduct = Product::factory()->create();
        $greenProduct = Product::factory()->create();

        $redVariant = ProductVariant::factory()->create([
            'product_id' => $redProduct->id,
            'status' => VariantStatus::ACTIVE,
        ]);

        $blueVariant = ProductVariant::factory()->create([
            'product_id' => $blueProduct->id,
            'status' => VariantStatus::MADE_TO_ORDER,
        ]);

        $greenVariant = ProductVariant::factory()->create([
            'product_id' => $greenProduct->id,
            'status' => VariantStatus::ACTIVE,
        ]);

        $redVariant->attributeValues()->attach($red);
        $blueVariant->attributeValues()->attach($blue);
        $greenVariant->attributeValues()->attach($green);

        $response = $this->getJson(
            '/api/products?color=Red,Blue'
        )->assertOk();

        $ids = collect(
            $response->json('data')
        )->pluck('id');

        $this->assertTrue(
            $ids->contains($redProduct->id)
        );

        $this->assertTrue(
            $ids->contains($blueProduct->id)
        );

        $this->assertFalse(
            $ids->contains($greenProduct->id)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    public function test_public_product_listing_supports_per_page(): void
    {
        $products = Product::factory()
            ->count(5)
            ->create();

        $firstPage = $this
            ->getJson(
                '/api/products?per_page=2&page=1'
            )
            ->assertOk();

        $firstPageData = $firstPage->json('data');

        $this->assertCount(
            2,
            $firstPageData
        );

        $this->assertNotNull(
            $firstPage->json('links.next')
        );

        $secondPage = $this
            ->getJson(
                '/api/products?per_page=2&page=2'
            )
            ->assertOk();

        $secondPageData = $secondPage->json('data');

        $this->assertCount(
            2,
            $secondPageData
        );

        $firstPageIds = collect($firstPageData)
            ->pluck('id');

        $secondPageIds = collect($secondPageData)
            ->pluck('id');

        $this->assertTrue(
            $firstPageIds->intersect(
                $secondPageIds
            )->isEmpty()
        );

        $this->assertCount(
            5,
            collect($products)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    */

    public function test_public_product_listing_avoids_n_plus_one_queries(): void
    {
        Product::factory()->count(5)->create();

        DB::enableQueryLog();

        $this->getJson('/api/products')
            ->assertOk();

        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            12,
            count($queries)
        );
    }

    public function test_public_product_listing_avoids_n_plus_one_queries_with_relations(): void
    {
        $category = Category::factory()->create([
            'is_active' => true,
        ]);

        $collection = Collection::create([
            'name' => 'Performance Collection',
            'slug' => 'performance-collection',
            'is_active' => true,
            'sort_order' => 1,
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
            $product->collections()->attach(
                $collection->id
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

            Inventory::factory()->create([
                'product_variant_id' => $variantOne->id,
                'quantity' => 20,
                'reserved_quantity' => 3,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson(
            '/api/products?per_page=5'
        );

        $response->assertOk();

        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertCount(
            5,
            $response->json('data')
        );

        $this->assertLessThanOrEqual(
            5,
            count($queries)
        );
    }

    public function test_public_product_listing_uses_simple_pagination_without_count_query(): void
    {
        Product::factory()
            ->count(5)
            ->create();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson(
            '/api/products?per_page=2&page=1'
        );

        $response->assertOk();

        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertCount(
            2,
            $response->json('data')
        );

        /*
         * simplePaginate() must execute:
         *
         * 1. Main product query
         * 2. Category query
         * 3. Collection query
         * 4. Product image query
         *
         * It must NOT execute a COUNT(*) query.
         */

        $this->assertCount(
            4,
            $queries
        );

        $countQueries = collect($queries)
            ->filter(
                fn (array $query): bool =>
                    str_contains(
                        strtolower($query['query']),
                        'count('
                    )
            );

        $this->assertCount(
            0,
            $countQueries
        );

        $this->assertNotNull(
            $response->json('links.next')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Inventory
    |--------------------------------------------------------------------------
    */

    public function test_inventory_is_aggregated_only_from_active_variants(): void
    {
        $product = Product::factory()->create();

        $active = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
        ]);

        $inactive = ProductVariant::factory()
            ->inactive()
            ->create([
                'product_id' => $product->id,
            ]);

        $madeToOrder = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::MADE_TO_ORDER,
        ]);

        Inventory::factory()->create([
            'product_variant_id' => $active->id,
            'quantity' => 10,
            'reserved_quantity' => 3,
        ]);

        Inventory::factory()->create([
            'product_variant_id' => $inactive->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        Inventory::factory()->create([
            'product_variant_id' => $madeToOrder->id,
            'quantity' => 1000,
            'reserved_quantity' => 0,
        ]);

        $this->getJson(
            "/api/products/{$product->slug}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.total_inventory',
                null
            )
            ->assertJsonPath(
                'data.available_inventory',
                null
            )
            ->assertJsonPath(
                'data.has_available_inventory',
                true
            );
    }

    public function test_available_inventory_never_becomes_negative(): void
    {
        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
        ]);

        Inventory::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'reserved_quantity' => 5,
        ]);

        $this->getJson(
            "/api/products/{$product->slug}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.available_inventory',
                null
            )
            ->assertJsonPath(
                'data.has_available_inventory',
                false
            );
    }

    public function test_soft_deleted_active_variant_is_not_included_in_public_inventory_state(): void
    {
        $product = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
        ]);

        $deletedVariant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
        ]);

        Inventory::factory()->create([
            'product_variant_id' => $deletedVariant->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        $deletedVariant->delete();

        $response = $this->getJson(
            "/api/products/{$product->slug}"
        )
            ->assertOk();

        $response
            ->assertJsonPath(
                'data.has_available_inventory',
                false
            )
            ->assertJsonPath(
                'data.total_inventory',
                null
            )
            ->assertJsonPath(
                'data.available_inventory',
                null
            )
            ->assertJsonCount(
                0,
                'data.variants'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Public / Admin Inventory Visibility
    |--------------------------------------------------------------------------
    */

    public function test_public_response_hides_admin_inventory_fields_for_customer(): void
    {
        $customer = $this->createActiveUser();

        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
        ]);

        Inventory::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 5,
            'reserved_quantity' => 1,
        ]);

        $this
            ->actingAs($customer)
            ->getJson(
                "/api/products/{$product->slug}"
            )
            ->assertOk()
            ->assertJsonMissingPath(
                'data.variants.0.inventory.available_quantity'
            )
            ->assertJsonMissingPath(
                'data.variants.0.inventory.reserved_quantity'
            )
            ->assertJsonMissingPath(
                'data.variants.0.inventory.low_stock_threshold'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Product Management
    |--------------------------------------------------------------------------
    */

    public function test_authorized_admin_can_create_and_update_product_relationships_and_inventory(): void
    {
        $admin = $this->adminUser([
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
        ]);

        $category = Category::factory()->create();

        $collection = Collection::create([
            'name' => 'Tea',
            'slug' => 'tea',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $attribute = Attribute::create([
            'name' => 'Color',
            'slug' => 'color',
            'type' => 'select',
        ]);

        $attributeValue = AttributeValue::create([
            'attribute_id' => $attribute->id,
            'value' => 'Red',
        ]);

        $payload = [
            'category_id' => $category->id,
            'name' => [
                'en' => 'Classic Teapot',
                'fa' => 'Classic Teapot FA',
            ],
            'slug' => 'classic-teapot-admin',
            'price' => 100,
            'collections' => [
                $collection->id,
            ],
            'variants' => [[
                'sku' => 'TKB-TP-RED',
                'price' => 85,
                'attribute_values' => [
                    $attributeValue->id,
                ],
                'inventory' => [
                    'quantity' => 12,
                    'reserved_quantity' => 2,
                    'low_stock_threshold' => 5,
                ],
            ]],
        ];

        $response = $this
            ->actingAs($admin)
            ->postJson(
                '/api/admin/products',
                $payload
            )
            ->assertCreated();

        $product = Product::where(
            'slug',
            'classic-teapot-admin'
        )->firstOrFail();

        $variant = $product
            ->variants()
            ->firstOrFail();

        $response
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath(
                'data.variants.0.id',
                $variant->id
            )
            ->assertJsonPath(
                'data.variants.0.inventory.quantity',
                12
            );

        $this->assertDatabaseHas(
            'collection_product',
            [
                'product_id' => $product->id,
                'collection_id' => $collection->id,
            ]
        );

        $this->assertDatabaseHas(
            'variant_attribute_values',
            [
                'product_variant_id' => $variant->id,
                'attribute_value_id' => $attributeValue->id,
            ]
        );

        $this->assertDatabaseHas(
            'inventories',
            [
                'product_variant_id' => $variant->id,
                'quantity' => 12,
                'reserved_quantity' => 2,
                'low_stock_threshold' => 5,
            ]
        );

        $response = $this
            ->actingAs($admin)
            ->putJson(
                "/api/admin/products/{$product->id}",
                [
                    'name' => [
                        'en' => 'Updated Teapot',
                        'fa' => 'Updated Teapot FA',
                    ],
                    'slug' => 'updated-teapot',
                    'variants' => [[
                        ...$payload['variants'][0],
                        'id' => $variant->id,
                        'inventory' => [
                            'quantity' => 20,
                            'reserved_quantity' => 4,
                            'low_stock_threshold' => 6,
                        ],
                    ]],
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'products',
            [
                'id' => $product->id,
                'slug' => 'updated-teapot',
            ]
        );

        $this->assertDatabaseHas(
            'inventories',
            [
                'product_variant_id' => $variant->id,
                'quantity' => 20,
                'reserved_quantity' => 4,
                'low_stock_threshold' => 6,
            ]
        );

        $response->assertJsonPath(
            'data.variants.0.inventory.quantity',
            20
        );
    }

    public function test_admin_product_response_exposes_inventory_management_fields_explicitly(): void
    {
        $admin = $this->adminUser([
            'products.view',
        ]);

        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
        ]);

        Inventory::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 8,
            'reserved_quantity' => 2,
        ]);

        $this
            ->actingAs($admin)
            ->getJson(
                "/api/admin/products/{$product->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.variants.0.inventory.quantity',
                8
            )
            ->assertJsonPath(
                'data.variants.0.inventory.reserved_quantity',
                2
            )
            ->assertJsonPath(
                'data.variants.0.inventory.low_stock_threshold',
                5
            );
    }

    public function test_cross_product_variant_id_is_rejected_on_update(): void
    {
        $admin = $this->adminUser([
            'products.update',
        ]);

        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();

        $otherVariant = ProductVariant::factory()->create([
            'product_id' => $otherProduct->id,
            'sku' => 'OTHER-SKU',
        ]);

        $this
            ->actingAs($admin)
            ->putJson(
                "/api/admin/products/{$product->id}",
                [
                    'variants' => [[
                        'id' => $otherVariant->id,
                        'sku' => 'CROSS-PRODUCT',
                    ]],
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'variants.0.id',
            ]);

        $this->assertDatabaseHas(
            'product_variants',
            [
                'id' => $otherVariant->id,
                'product_id' => $otherProduct->id,
                'sku' => $otherVariant->sku,
            ]
        );
    }

    public function test_inventory_reserved_quantity_is_validated_against_existing_quantity_on_partial_update(): void
    {
        $admin = $this->adminUser([
            'products.update',
        ]);

        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
        ]);

        Inventory::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'reserved_quantity' => 2,
        ]);

        $this
            ->actingAs($admin)
            ->putJson(
                "/api/admin/products/{$product->id}",
                [
                    'variants' => [[
                        'id' => $variant->id,
                        'sku' => $variant->sku,
                        'inventory' => [
                            'reserved_quantity' => 15,
                        ],
                    ]],
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'variants.0.inventory.reserved_quantity',
            ]);

        $this
            ->actingAs($admin)
            ->putJson(
                "/api/admin/products/{$product->id}",
                [
                    'variants' => [[
                        'id' => $variant->id,
                        'sku' => $variant->sku,
                        'inventory' => [
                            'reserved_quantity' => 3,
                        ],
                    ]],
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'inventories',
            [
                'product_variant_id' => $variant->id,
                'reserved_quantity' => 3,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Activity Logging
    |--------------------------------------------------------------------------
    */

    public function test_creating_product_creates_activity_log(): void
    {
        $admin = $this->adminUser([
            'products.create',
        ]);

        $category = Category::factory()->create();

        $this
            ->actingAs($admin)
            ->postJson(
                '/api/admin/products',
                [
                    'category_id' => $category->id,
                    'name' => [
                        'en' => 'Classic Teapot',
                        'fa' => 'Classic Teapot',
                    ],
                    'slug' => 'activity-teapot',
                    'price' => 100,
                ]
            )
            ->assertCreated();

        $product = Product::query()
            ->where(
                'slug',
                'activity-teapot'
            )
            ->firstOrFail();

        $log = ActivityLog::query()
            ->where(
                'action',
                'products.created'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $admin->id,
            $log->user_id
        );

        $this->assertSame(
            'products.created',
            $log->action
        );

        $this->assertSame(
            'Product created.',
            $log->description
        );

        $this->assertSame(
            Product::class,
            $log->properties['subject']['type']
        );

        $this->assertSame(
            $product->id,
            $log->properties['subject']['id']
        );

        $this->assertSame(
            $product->id,
            $log->properties['product']['id']
        );

        $this->assertSame(
            'activity-teapot',
            $log->properties['product']['slug']
        );

        $this->assertSame(
            [],
            $log->properties['collection_ids']
        );

        $this->assertSame(
            0,
            $log->properties['variants_count']
        );
    }

    public function test_updating_product_creates_activity_log(): void
    {
        $admin = $this->adminUser([
            'products.update',
        ]);

        $product = Product::factory()->create([
            'slug' => 'activity-update-product',
            'price' => 100,
        ]);

        $this
            ->actingAs($admin)
            ->putJson(
                "/api/admin/products/{$product->id}",
                [
                    'price' => 150,
                    'is_featured' => true,
                ]
            )
            ->assertOk();

        $log = ActivityLog::query()
            ->where(
                'action',
                'products.updated'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $admin->id,
            $log->user_id
        );

        $this->assertSame(
            Product::class,
            $log->properties['subject']['type']
        );

        $this->assertSame(
            $product->id,
            $log->properties['subject']['id']
        );

        $this->assertContains(
            'price',
            $log->properties['product_fields_provided']
        );

        $this->assertContains(
            'is_featured',
            $log->properties['product_fields_provided']
        );

        $this->assertFalse(
            $log->properties['collections_updated']
        );

        $this->assertFalse(
            $log->properties['variants_updated']
        );

        $this->assertSame(
            0,
            $log->properties['variants_count']
        );
    }

    public function test_updating_product_with_variants_records_variant_operation(): void
    {
        $admin = $this->adminUser([
            'products.update',
        ]);

        $product = Product::factory()->create([
            'slug' => 'activity-variant-product',
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'ACTIVITY-VARIANT',
        ]);

        $this
            ->actingAs($admin)
            ->putJson(
                "/api/admin/products/{$product->id}",
                [
                    'variants' => [[
                        'id' => $variant->id,
                        'sku' => 'ACTIVITY-VARIANT-UPDATED',
                        'price' => 150,
                        'status' => VariantStatus::ACTIVE->value,
                    ]],
                ]
            )
            ->assertOk();

        $log = ActivityLog::query()
            ->where(
                'action',
                'products.updated'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $admin->id,
            $log->user_id
        );

        $this->assertTrue(
            $log->properties['variants_updated']
        );

        $this->assertSame(
            1,
            $log->properties['variants_count']
        );

        $this->assertSame(
            $product->id,
            $log->properties['subject']['id']
        );
    }

    public function test_deleting_product_creates_activity_log(): void
    {
        $admin = $this->adminUser([
            'products.delete',
        ]);

        $product = Product::factory()->create([
            'slug' => 'activity-delete-product',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
        ]);

        $this
            ->actingAs($admin)
            ->deleteJson(
                "/api/admin/products/{$product->id}"
            )
            ->assertNoContent();

        $log = ActivityLog::query()
            ->where(
                'action',
                'products.deleted'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $admin->id,
            $log->user_id
        );

        $this->assertSame(
            'products.deleted',
            $log->action
        );

        $this->assertSame(
            Product::class,
            $log->properties['subject']['type']
        );

        $this->assertSame(
            $product->id,
            $log->properties['subject']['id']
        );

        $this->assertSame(
            $product->id,
            $log->properties['product']['id']
        );

        $this->assertSame(
            'activity-delete-product',
            $log->properties['product']['slug']
        );

        $this->assertSame(
            1,
            $log->properties['variants_count']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Authorization
    |--------------------------------------------------------------------------
    */

    public function test_guest_cannot_access_admin_product_endpoints(): void
    {
        $product = Product::factory()->create();

        $this
            ->getJson('/api/admin/products')
            ->assertUnauthorized();

        $this
            ->getJson("/api/admin/products/{$product->id}")
            ->assertUnauthorized();

        $this
            ->postJson('/api/admin/products', [
                'name' => [
                    'en' => 'Unauthorized',
                    'fa' => 'Unauthorized',
                ],
            ])
            ->assertUnauthorized();

        $this
            ->putJson("/api/admin/products/{$product->id}", [
                'name' => [
                    'en' => 'Unauthorized',
                    'fa' => 'Unauthorized',
                ],
            ])
            ->assertUnauthorized();

        $this
            ->deleteJson(
                "/api/admin/products/{$product->id}"
            )
            ->assertUnauthorized();
    }

    public function test_customer_cannot_access_admin_product_endpoints(): void
    {
        $customer = $this->customerUser();

        $product = Product::factory()->create();

        $this
            ->actingAs($customer, 'sanctum')
            ->getJson('/api/admin/products')
            ->assertForbidden();

        $this
            ->actingAs($customer, 'sanctum')
            ->getJson("/api/admin/products/{$product->id}")
            ->assertForbidden();

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson('/api/admin/products', [
                'name' => [
                    'en' => 'Customer Product',
                    'fa' => 'Customer Product',
                ],
            ])
            ->assertForbidden();

        $this
            ->actingAs($customer, 'sanctum')
            ->putJson("/api/admin/products/{$product->id}", [
                'name' => [
                    'en' => 'Customer Product',
                    'fa' => 'Customer Product',
                ],
            ])
            ->assertForbidden();

        $this
            ->actingAs($customer, 'sanctum')
            ->deleteJson(
                "/api/admin/products/{$product->id}"
            )
            ->assertForbidden();
    }

    public function test_accountant_cannot_access_admin_product_endpoints(): void
    {
        $accountant = $this->roleUser(
            'Accountant',
            'accountant'
        );

        $product = Product::factory()->create();

        $this
            ->actingAs($accountant, 'sanctum')
            ->getJson('/api/admin/products')
            ->assertForbidden();

        $this
            ->actingAs($accountant, 'sanctum')
            ->getJson("/api/admin/products/{$product->id}")
            ->assertForbidden();

        $this
            ->actingAs($accountant, 'sanctum')
            ->postJson('/api/admin/products', [
                'name' => [
                    'en' => 'Accountant Product',
                    'fa' => 'Accountant Product',
                ],
            ])
            ->assertForbidden();

        $this
            ->actingAs($accountant, 'sanctum')
            ->putJson("/api/admin/products/{$product->id}", [
                'name' => [
                    'en' => 'Accountant Product',
                    'fa' => 'Accountant Product',
                ],
            ])
            ->assertForbidden();

        $this
            ->actingAs($accountant, 'sanctum')
            ->deleteJson(
                "/api/admin/products/{$product->id}"
            )
            ->assertForbidden();
    }

    public function test_support_cannot_access_admin_product_endpoints(): void
    {
        $support = $this->roleUser(
            'Support',
            'support'
        );

        $product = Product::factory()->create();

        $this
            ->actingAs($support, 'sanctum')
            ->getJson('/api/admin/products')
            ->assertForbidden();

        $this
            ->actingAs($support, 'sanctum')
            ->getJson("/api/admin/products/{$product->id}")
            ->assertForbidden();

        $this
            ->actingAs($support, 'sanctum')
            ->postJson('/api/admin/products', [
                'name' => [
                    'en' => 'Support Product',
                    'fa' => 'Support Product',
                ],
            ])
            ->assertForbidden();

        $this
            ->actingAs($support, 'sanctum')
            ->putJson("/api/admin/products/{$product->id}", [
                'name' => [
                    'en' => 'Support Product',
                    'fa' => 'Support Product',
                ],
            ])
            ->assertForbidden();

        $this
            ->actingAs($support, 'sanctum')
            ->deleteJson(
                "/api/admin/products/{$product->id}"
            )
            ->assertForbidden();
    }

    public function test_admin_without_products_view_permission_cannot_view_products(): void
    {
        $admin = $this->adminUser([]);

        $product = Product::factory()->create();

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/products')
            ->assertForbidden();

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/products/{$product->id}")
            ->assertForbidden();
    }

    public function test_admin_without_products_create_permission_cannot_create_product(): void
    {
        $admin = $this->adminUser([
            'products.view',
        ]);

        $category = Category::factory()->create();

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/products',
                [
                    'category_id' => $category->id,
                    'name' => [
                        'en' => 'No Create Permission',
                        'fa' => 'No Create Permission',
                    ],
                    'slug' => 'no-create-permission',
                    'price' => 100,
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseMissing(
            'products',
            [
                'slug' => 'no-create-permission',
            ]
        );
    }

    public function test_admin_without_products_update_permission_cannot_update_product_or_inventory(): void
    {
        $admin = $this->adminUser([
            'products.view',
        ]);

        $product = Product::factory()->create([
            'slug' => 'protected-product',
            'price' => 100,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 90,
        ]);

        $inventory = Inventory::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'reserved_quantity' => 2,
            'low_stock_threshold' => 5,
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson(
                "/api/admin/products/{$product->id}",
                [
                    'price' => 1,
                    'variants' => [[
                        'id' => $variant->id,
                        'sku' => $variant->sku,
                        'inventory' => [
                            'quantity' => 1,
                            'reserved_quantity' => 1,
                        ],
                    ]],
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseHas(
            'products',
            [
                'id' => $product->id,
                'slug' => 'protected-product',
                'price' => 100,
            ]
        );

        $this->assertDatabaseHas(
            'inventories',
            [
                'id' => $inventory->id,
                'quantity' => 10,
                'reserved_quantity' => 2,
                'low_stock_threshold' => 5,
            ]
        );
    }

    public function test_admin_without_products_delete_permission_cannot_delete_product(): void
    {
        $admin = $this->adminUser([
            'products.view',
        ]);

        $product = Product::factory()->create();

        $this
            ->actingAs($admin, 'sanctum')
            ->deleteJson(
                "/api/admin/products/{$product->id}"
            )
            ->assertForbidden();

        $this->assertDatabaseHas(
            'products',
            [
                'id' => $product->id,
            ]
        );
    }

    public function test_super_admin_can_access_admin_product_endpoints(): void
    {
        $superAdmin = $this->roleUser(
            'Super Admin',
            'super_admin'
        );

        $superAdminRole = $superAdmin
            ->roles()
            ->where(
                'slug',
                'super_admin'
            )
            ->firstOrFail();

        $permissions = collect([
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
        ])->map(
            fn (string $slug) => Permission::firstOrCreate(
                [
                    'slug' => $slug,
                ],
                [
                    'name' => $slug,
                ]
            )
        );

        $superAdminRole->permissions()->sync(
            $permissions->pluck('id')
        );

        $product = Product::factory()->create();

        $this
            ->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/admin/products')
            ->assertOk();

        $this
            ->actingAs($superAdmin, 'sanctum')
            ->getJson(
                "/api/admin/products/{$product->id}"
            )
            ->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Validation / Authorization
    |--------------------------------------------------------------------------
    */

    public function test_admin_allows_shared_attribute_values_across_variants_when_updating_name(): void
    {
        $admin = $this->adminUser([
            'products.update',
        ]);

        $product = Product::factory()->create([
            'slug' => 'shared-variant-attributes',
        ]);

        $color = Attribute::create([
            'name' => 'Color',
            'slug' => 'regression-color',
            'type' => 'select',
        ]);

        $size = Attribute::create([
            'name' => 'Size',
            'slug' => 'regression-size',
            'type' => 'select',
        ]);

        $black = AttributeValue::create([
            'attribute_id' => $color->id,
            'value' => 'Black',
        ]);

        $small = AttributeValue::create([
            'attribute_id' => $size->id,
            'value' => 'Small',
        ]);

        $large = AttributeValue::create([
            'attribute_id' => $size->id,
            'value' => 'Large',
        ]);

        $first = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'REGRESSION-BLACK-S',
        ]);

        $second = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'REGRESSION-BLACK-L',
        ]);

        $first->attributeValues()->sync([
            $black->id,
            $small->id,
        ]);

        $second->attributeValues()->sync([
            $black->id,
            $large->id,
        ]);

        $this
            ->actingAs($admin)
            ->putJson(
                "/api/admin/products/{$product->id}",
                [
                    'name' => [
                        'en' => 'Updated Shared Color Product',
                        'fa' => 'Updated Shared Color Product FA',
                    ],
                    'variants' => [
                        [
                            'id' => $first->id,
                            'sku' => $first->sku,
                            'attribute_values' => [
                                $black->id,
                                $small->id,
                            ],
                        ],
                        [
                            'id' => $second->id,
                            'sku' => $second->sku,
                            'attribute_values' => [
                                $black->id,
                                $large->id,
                            ],
                        ],
                    ],
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'variant_attribute_values',
            [
                'product_variant_id' => $first->id,
                'attribute_value_id' => $black->id,
            ]
        );

        $this->assertDatabaseHas(
            'variant_attribute_values',
            [
                'product_variant_id' => $second->id,
                'attribute_value_id' => $black->id,
            ]
        );
    }

    public function test_admin_rejects_duplicate_attribute_value_within_one_variant(): void
    {
        $admin = $this->adminUser([
            'products.update',
        ]);

        $product = Product::factory()->create();

        $color = Attribute::create([
            'name' => 'Color',
            'slug' => 'duplicate-regression-color',
            'type' => 'select',
        ]);

        $black = AttributeValue::create([
            'attribute_id' => $color->id,
            'value' => 'Black',
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'REGRESSION-DUPLICATE',
        ]);

        $this
            ->actingAs($admin)
            ->putJson(
                "/api/admin/products/{$product->id}",
                [
                    'variants' => [
                        [
                            'id' => $variant->id,
                            'sku' => $variant->sku,
                            'attribute_values' => [
                                $black->id,
                                $black->id,
                            ],
                        ],
                    ],
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'variants.0.attribute_values.1',
            ]);
    }
    public function test_admin_validation_rejects_duplicate_slug_sku_invalid_category_and_bad_inventory(): void
    {
        $admin = $this->adminUser([
            'products.create',
        ]);

        Product::factory()->create([
            'slug' => 'taken-slug',
        ]);

        ProductVariant::factory()->create([
            'sku' => 'TAKEN-SKU',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/products',
                [
                    'category_id' => 999999,
                    'name' => [
                        'en' => 'Invalid Product',
                        'fa' => 'Invalid Product FA',
                    ],
                    'slug' => 'taken-slug',
                    'price' => 10,
                    'variants' => [[
                        'sku' => 'TAKEN-SKU',
                        'inventory' => [
                            'quantity' => -1,
                            'reserved_quantity' => 2,
                        ],
                    ]],
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'category_id',
                'slug',
                'variants.0.sku',
                'variants.0.inventory.quantity',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createActiveUser(): User
    {
        return User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);
    }

    private function customerUser(): User
    {
        return $this->roleUser(
            'Customer',
            'customer'
        );
    }

    private function roleUser(
        string $name,
        string $slug
    ): User {
        $user = User::factory()->create([
            'email' =>
                strtolower($slug) . '-' . uniqid() . '@tkb.test',
            'status' => UserStatus::ACTIVE,
        ]);

        $role = Role::firstOrCreate(
            [
                'slug' => $slug,
            ],
            [
                'name' => $name,
            ]
        );

        $user->roles()->sync([
            $role->id,
        ]);

        return $user;
    }

    private function adminUser(
        array $permissions = []
    ): User {
        $user = User::factory()->create([
            'email' =>
                'product-admin-' . uniqid() . '@tkb.test',
            'status' => UserStatus::ACTIVE,
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
        )
            ->map(
                fn (string $slug) =>
                    Permission::firstOrCreate(
                        [
                            'slug' => $slug,
                        ],
                        [
                            'name' => $slug,
                        ]
                    )->id
            )
            ->all();

        $role->permissions()->sync(
            $permissionIds
        );

        $user->roles()->sync([
            $role->id,
        ]);

        return $user;
    }
}
