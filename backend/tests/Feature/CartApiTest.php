<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use App\Enums\VariantStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'storefront.online_checkout_enabled' => true,
        ]);
    }

    public function test_cart_api_supports_guest_requests_without_authentication(): void
    {
        $this->getJson('/api/cart')
            ->assertOk();

        $this->postJson('/api/cart/items', [])
            ->assertUnprocessable();
    }

    public function test_authenticated_user_gets_an_empty_active_cart(): void
    {
        $user = $this->createActiveUser();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/cart')
            ->assertOk();

        $response
            ->assertJsonPath(
                'data.items',
                []
            )
            ->assertJsonPath(
                'data.item_count',
                0
            )
            ->assertJsonPath(
                'data.subtotal',
                0
            );

        $this->assertDatabaseHas(
            'carts',
            [
                'user_id' => $user->id,
                'status' => 'active',
            ]
        );
    }

    public function test_valid_variant_is_added_with_server_side_price_snapshot(): void
    {
        $user = $this->createActiveUser();

        $variant = $this->availableVariant(
            100,
            80,
            10,
            2
        );

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 3,
                ]
            )
            ->assertCreated();

        $item = CartItem::firstOrFail();

        $response
            ->assertJsonPath(
                'data.items.0.product_variant.id',
                $variant->id
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                3
            )
            ->assertJsonPath(
                'data.items.0.price_snapshot',
                80
            )
            ->assertJsonPath(
                'data.items.0.line_total',
                240
            )
            ->assertJsonPath(
                'data.subtotal',
                240
            );

        $this->assertSame(
            80.0,
            (float) $item->price_snapshot
        );
    }

    public function test_client_cannot_override_cart_price_snapshot(): void
    {
        $user = $this->createActiveUser();

        $variant = $this->availableVariant(
            100,
            80,
            10,
            0
        );

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,

                    // Malicious client-controlled value.
                    'price_snapshot' => 1,
                ]
            )
            ->assertCreated();

        $item = CartItem::firstOrFail();

        // Server must use the actual effective variant price.
        $this->assertSame(
            80.0,
            (float) $item->price_snapshot
        );

        $response
            ->assertJsonPath(
                'data.items.0.price_snapshot',
                80
            )
            ->assertJsonPath(
                'data.items.0.line_total',
                80
            );

        $this->assertNotSame(
            1.0,
            (float) $item->price_snapshot
        );
    }

    public function test_adding_the_same_variant_merges_quantity_without_creating_duplicate_item(): void
    {
        $user = $this->createActiveUser();

        $variant = $this->availableVariant(
            100,
            80,
            10,
            0
        );

        $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 2,
                ]
            )
            ->assertCreated();

        $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
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
                'product_variant_id' => $variant->id,
                'quantity' => 5,
            ]
        );
    }

    public function test_merge_is_rejected_when_total_quantity_exceeds_available_inventory(): void
    {
        $user = $this->createActiveUser();

        $variant = $this->availableVariant(
            5,
            80,
            10,
            6
        );

        $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 4,
                ]
            )
            ->assertCreated();

        $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 2,
                ]
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas(
            'cart_items',
            [
                'product_variant_id' => $variant->id,
                'quantity' => 4,
            ]
        );
    }

    public function test_inactive_sold_or_unavailable_variants_cannot_be_added(): void
    {
        $user = $this->createActiveUser();

        $inactiveProduct = Product::factory()->create([
            'status' => ProductStatus::INACTIVE,
        ]);

        $inactiveProductVariant = ProductVariant::factory()->create([
            'product_id' => $inactiveProduct->id,
            'status' => VariantStatus::ACTIVE,
        ]);

        $inactiveVariantProduct = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
        ]);

        $inactiveVariant = ProductVariant::factory()->create([
            'product_id' => $inactiveVariantProduct->id,
            'status' => VariantStatus::INACTIVE,
        ]);

        $soldVariantProduct = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
        ]);

        $soldVariant = ProductVariant::factory()->create([
            'product_id' => $soldVariantProduct->id,
            'status' => VariantStatus::SOLD,
        ]);

        $unavailable = $this->availableVariant(
            5,
            80,
            5,
            5
        );

        foreach (
            [
                $inactiveProductVariant,
                $inactiveVariant,
                $soldVariant,
                $unavailable,
            ] as $variant
        ) {
            $this
                ->actingAs($user)
                ->postJson(
                    '/api/cart/items',
                    [
                        'product_variant_id' => $variant->id,
                        'quantity' => 1,
                    ]
                )
                ->assertUnprocessable();
        }

        $this->assertDatabaseCount(
            'cart_items',
            0
        );
    }

    public function test_active_variant_without_inventory_cannot_be_added(): void
    {
        $user = $this->createActiveUser();

        $product = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
        ]);

        $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                ]
            )
            ->assertUnprocessable();

        $this->assertDatabaseCount(
            'cart_items',
            0
        );
    }

    public function test_made_to_order_variant_can_be_added_without_inventory(): void
    {
        $user = $this->createActiveUser();

        $product = Product::factory()->create([
            'price' => 120,
            'status' => ProductStatus::ACTIVE,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 135,
            'status' => VariantStatus::MADE_TO_ORDER,
        ]);

        $this->assertDatabaseMissing(
            'inventories',
            [
                'product_variant_id' => $variant->id,
            ]
        );

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 3,
                ]
            )
            ->assertCreated();

        $response
            ->assertJsonPath(
                'data.items.0.product_variant.id',
                $variant->id
            )
            ->assertJsonPath(
                'data.items.0.product_variant.status',
                'made_to_order'
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                3
            )
            ->assertJsonPath(
                'data.items.0.price_snapshot',
                135
            )
            ->assertJsonPath(
                'data.subtotal',
                405
            );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'product_variant_id' => $variant->id,
                'quantity' => 3,
                'price_snapshot' => 135,
            ]
        );
    }

    public function test_made_to_order_cart_item_is_reported_as_available_without_inventory_quantity(): void
    {
        $user = $this->createActiveUser();

        $product = Product::factory()->create([
            'price' => 120,
            'status' => ProductStatus::ACTIVE,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 135,
            'status' => VariantStatus::MADE_TO_ORDER,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 2,
                ]
            )
            ->assertCreated();

        $response
            ->assertJsonPath(
                'data.items.0.product_variant.status',
                'made_to_order'
            )
            ->assertJsonPath(
                'data.items.0.availability.available',
                true
            )
            ->assertJsonMissingPath(
                'data.items.0.availability.available_quantity'
            );
    }

    public function test_active_cart_item_does_not_expose_numeric_inventory_quantity(): void
    {
        $user = $this->createActiveUser();

        $variant = $this->availableVariant(
            100,
            80,
            10,
            2
        );

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 3,
                ]
            )
            ->assertCreated();

        $response
            ->assertJsonPath(
                'data.items.0.availability.available',
                true
            )
            ->assertJsonMissingPath(
                'data.items.0.availability.available_quantity'
            );
    }

    public function test_invalid_variant_and_quantity_are_rejected(): void
    {
        $user = $this->createActiveUser();

        $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => 999999,
                    'quantity' => 0,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'product_variant_id',
                'quantity',
            ]);
    }

    public function test_quantity_update_preserves_snapshot_and_respects_inventory(): void
    {
        $user = $this->createActiveUser();

        $variant = $this->availableVariant(
            10,
            80,
            10,
            0
        );

        $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 2,
                ]
            )
            ->assertCreated();

        $item = CartItem::firstOrFail();

        $variant->update([
            'price' => 100,
        ]);

        $this
            ->actingAs($user)
            ->putJson(
                "/api/cart/items/{$item->id}",
                [
                    'quantity' => 4,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.quantity',
                4
            )
            ->assertJsonPath(
                'data.items.0.price_snapshot',
                80
            );

        $this
            ->actingAs($user)
            ->putJson(
                "/api/cart/items/{$item->id}",
                [
                    'quantity' => 11,
                ]
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas(
            'cart_items',
            [
                'id' => $item->id,
                'quantity' => 4,
                'price_snapshot' => 80,
            ]
        );
    }

    public function test_inventory_is_not_modified_by_cart_operations(): void
    {
        $user = $this->createActiveUser();

        $variant = $this->availableVariant(
            10,
            80,
            10,
            3
        );

        $inventory = $variant->inventory;

        $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 2,
                ]
            )
            ->assertCreated();

        $item = CartItem::firstOrFail();

        $this
            ->actingAs($user)
            ->putJson(
                "/api/cart/items/{$item->id}",
                [
                    'quantity' => 4,
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'inventories',
            [
                'id' => $inventory->id,
                'quantity' => 10,
                'reserved_quantity' => 3,
            ]
        );
    }

    public function test_user_cannot_modify_or_delete_another_users_cart_item(): void
    {
        $owner = $this->createActiveUser();
        $otherUser = $this->createActiveUser();

        $variant = $this->availableVariant(
            10,
            80,
            10,
            0
        );

        $cart = Cart::create([
            'user_id' => $owner->id,
            'status' => 'active',
        ]);

        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'price_snapshot' => 80,
        ]);

        $this
            ->actingAs($otherUser)
            ->putJson(
                "/api/cart/items/{$item->id}",
                [
                    'quantity' => 2,
                ]
            )
            ->assertNotFound();

        $this
            ->actingAs($otherUser)
            ->deleteJson(
                "/api/cart/items/{$item->id}"
            )
            ->assertNotFound();

        $this->assertDatabaseHas(
            'cart_items',
            [
                'id' => $item->id,
                'quantity' => 1,
            ]
        );
    }

    public function test_user_can_delete_an_item_from_their_active_cart(): void
    {
        $user = $this->createActiveUser();

        $variant = $this->availableVariant(
            10,
            80,
            10,
            0
        );

        $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                ]
            )
            ->assertCreated();

        $item = CartItem::firstOrFail();

        $this
            ->actingAs($user)
            ->deleteJson(
                "/api/cart/items/{$item->id}"
            )
            ->assertNoContent();

        $this->assertDatabaseMissing(
            'cart_items',
            [
                'id' => $item->id,
            ]
        );
    }

    public function test_clear_cart_removes_only_the_authenticated_users_items(): void
    {
        $user = $this->createActiveUser();
        $otherUser = $this->createActiveUser();

        $variant = $this->availableVariant(
            10,
            80,
            10,
            0
        );

        $cart = Cart::create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $otherCart = Cart::create([
            'user_id' => $otherUser->id,
            'status' => 'active',
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'price_snapshot' => 80,
        ]);

        $otherVariant = $this->availableVariant(
            10,
            90,
            10,
            0
        );

        CartItem::create([
            'cart_id' => $otherCart->id,
            'product_variant_id' => $otherVariant->id,
            'quantity' => 1,
            'price_snapshot' => 90,
        ]);

        $this
            ->actingAs($user)
            ->deleteJson('/api/cart')
            ->assertNoContent();

        $this->assertDatabaseMissing(
            'cart_items',
            [
                'cart_id' => $cart->id,
            ]
        );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'cart_id' => $otherCart->id,
            ]
        );

        $this->assertDatabaseHas(
            'carts',
            [
                'id' => $cart->id,
                'status' => 'active',
            ]
        );
    }

    public function test_inactive_authenticated_user_cannot_access_cart(): void
    {
        $user = $this->createUserWithStatus(
            UserStatus::INACTIVE
        );

        $variant = $this->availableVariant(
            100,
            80,
            10,
            0
        );

        $this
            ->actingAs($user)
            ->getJson('/api/cart')
            ->assertForbidden();

        $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                ]
            )
            ->assertForbidden();
    }

    public function test_suspended_authenticated_user_cannot_access_cart(): void
    {
        $user = $this->createUserWithStatus(
            UserStatus::SUSPENDED
        );

        $variant = $this->availableVariant(
            100,
            80,
            10,
            0
        );

        $this
            ->actingAs($user)
            ->getJson('/api/cart')
            ->assertForbidden();

        $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                ]
            )
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */

    public function test_guest_cart_write_requests_are_rate_limited(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '10.254.250.10',
        ]);

        for ($i = 1; $i <= 30; $i++) {
            $this
                ->postJson(
                    '/api/cart/items',
                    []
                )
                ->assertUnprocessable();
        }

        $this
            ->postJson(
                '/api/cart/items',
                []
            )
            ->assertStatus(429);
    }

    public function test_authenticated_cart_write_requests_are_rate_limited(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '10.254.250.11',
        ]);

        $user = $this->createActiveUser();

        for ($i = 1; $i <= 30; $i++) {
            $this
                ->actingAs($user)
                ->postJson(
                    '/api/cart/items',
                    []
                )
                ->assertUnprocessable();
        }

        $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                []
            )
            ->assertStatus(429);
    }

    public function test_guest_cart_read_requests_are_rate_limited(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '10.254.250.12',
        ]);

        for ($i = 1; $i <= 60; $i++) {
            $this
                ->getJson('/api/cart')
                ->assertOk();
        }

        $this
            ->getJson('/api/cart')
            ->assertStatus(429);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createActiveUser(): User
    {
        return $this->createUserWithStatus(
            UserStatus::ACTIVE
        );
    }

    private function createUserWithStatus(UserStatus $status): User
    {
        return User::factory()->create([
            'status' => $status,
        ]);
    }

    private function availableVariant(
        float $productPrice,
        ?float $variantPrice,
        int $quantity,
        int $reserved
    ): ProductVariant {
        $product = Product::factory()->create([
            'price' => $productPrice,
            'status' => ProductStatus::ACTIVE,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => $variantPrice,
            'status' => VariantStatus::ACTIVE,
        ]);

        Inventory::factory()->create([
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'reserved_quantity' => $reserved,
        ]);

        return $variant->load(
            'inventory'
        );
    }
}