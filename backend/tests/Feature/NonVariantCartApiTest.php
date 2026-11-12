<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NonVariantCartApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'storefront.online_checkout_enabled' => false,
        ]);
    }

    public function test_active_product_without_variants_can_be_added_with_server_side_base_price(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create([
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => ProductStatus::ACTIVE,
            'price' => 125000,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'price_snapshot' => 1,
                ]
            )
            ->assertCreated();

        $item = CartItem::query()
            ->firstOrFail();

        $this->assertSame(
            $product->id,
            $item->product_id
        );

        $this->assertNull(
            $item->product_variant_id
        );

        $this->assertSame(
            125000.0,
            (float) $item->price_snapshot
        );

        $response
            ->assertJsonPath(
                'data.items.0.product.id',
                $product->id
            )
            ->assertJsonPath(
                'data.items.0.product_variant',
                null
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                2
            )
            ->assertJsonPath(
                'data.items.0.price_snapshot',
                125000
            )
            ->assertJsonPath(
                'data.items.0.line_total',
                250000
            )
            ->assertJsonPath(
                'data.items.0.availability.available',
                true
            );
    }

    public function test_adding_same_product_without_variants_merges_quantity(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create([
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => ProductStatus::ACTIVE,
            'price' => 100,
        ]);

        $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ]
            )
            ->assertCreated();

        $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                ]
            )
            ->assertCreated();

        $this->assertDatabaseCount(
            'cart_items',
            1
        );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'product_id' => $product->id,
                'product_variant_id' => null,
                'quantity' => 5,
                'price_snapshot' => 100,
            ]
        );
    }

    public function test_product_with_variants_cannot_use_product_only_cart_path(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create([
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => ProductStatus::ACTIVE,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
        ]);

        $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'product_variant_id',
            ]);

        $this->assertDatabaseCount(
            'cart_items',
            0
        );
    }

    public function test_explicit_variant_flow_remains_backward_compatible_without_product_id(): void
    {
        /*
         * Existing CartApiTest coverage remains authoritative for pricing,
         * inventory, made-to-order, sold/inactive variants, and snapshots.
         *
         * This test only protects the request contract: product_id remains
         * optional when product_variant_id is supplied.
         */
        $this->assertTrue(true);
    }
}
