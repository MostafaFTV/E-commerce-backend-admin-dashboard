<?php

namespace Tests\Feature;

use App\Enums\CartStatus;
use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuestCartApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'storefront.online_checkout_enabled' => true,
        ]);
    }

    public function test_new_guest_receives_an_opaque_token_and_can_retrieve_the_same_cart(): void
    {
        $response = $this->getJson('/api/cart')->assertOk();
        $cookie = $response->getCookie(CartService::GUEST_COOKIE, false);

        $this->assertNotNull($cookie);
        $this->assertSame(64, strlen($cookie->getValue()));
        $this->assertNotSame('1', $cookie->getValue());

        $this->withHeader(CartService::GUEST_HEADER, $cookie->getValue())
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('data.id', $response->json('data.id'));
    }

    public function test_invalid_guest_token_does_not_access_another_guest_cart(): void
    {
        $first = $this->getJson('/api/cart')->assertOk();
        $firstToken = $first
            ->getCookie(CartService::GUEST_COOKIE, false)
            ->getValue();

        $second = $this
            ->withHeader(
                CartService::GUEST_HEADER,
                str_repeat('x', 64)
            )
            ->getJson('/api/cart')
            ->assertOk();

        $this->assertNotSame(
            $first->json('data.id'),
            $second->json('data.id')
        );

        $this->assertDatabaseHas('carts', [
            'id' => $first->json('data.id'),
            'session_id' => $firstToken,
            'status' => 'active',
        ]);
    }

    public function test_guest_can_add_and_remove_items_with_safe_response_data(): void
    {
        $variant = $this->availableVariant(
            100,
            null,
            5,
            1
        );

        $cart = $this->getJson('/api/cart')
            ->assertOk();

        $token = $cart
            ->getCookie(
                CartService::GUEST_COOKIE,
                false
            )
            ->getValue();

        $this
            ->withHeader(
                CartService::GUEST_HEADER,
                $token
            )
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 2,
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.items.0.price_snapshot',
                100
            )
            ->assertJsonPath(
                'data.items.0.availability.available',
                true
            )
            ->assertJsonMissingPath(
                'data.items.0.inventory.reserved_quantity'
            )
            ->assertJsonMissingPath(
                'data.items.0.inventory.low_stock_threshold'
            );

        $item = CartItem::firstOrFail();

        $this
            ->withHeader(
                CartService::GUEST_HEADER,
                $token
            )
            ->deleteJson(
                "/api/cart/items/{$item->id}"
            )
            ->assertNoContent();
    }

    public function test_guest_add_and_update_respect_available_inventory_without_modifying_inventory(): void
    {
        $variant = $this->availableVariant(
            10,
            80,
            5,
            2
        );

        $cart = $this->getJson('/api/cart')
            ->assertOk();

        $token = $cart
            ->getCookie(
                CartService::GUEST_COOKIE,
                false
            )
            ->getValue();

        $this
            ->withHeader(
                CartService::GUEST_HEADER,
                $token
            )
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 4,
                ]
            )
            ->assertUnprocessable();

        $this
            ->withHeader(
                CartService::GUEST_HEADER,
                $token
            )
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 3,
                ]
            )
            ->assertCreated();

        $item = CartItem::firstOrFail();

        $this
            ->withHeader(
                CartService::GUEST_HEADER,
                $token
            )
            ->putJson(
                "/api/cart/items/{$item->id}",
                [
                    'quantity' => 4,
                ]
            )
            ->assertUnprocessable();

        $this
            ->withHeader(
                CartService::GUEST_HEADER,
                $token
            )
            ->putJson(
                "/api/cart/items/{$item->id}",
                [
                    'quantity' => 3,
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'inventories',
            [
                'product_variant_id' => $variant->id,
                'quantity' => 5,
                'reserved_quantity' => 2,
            ]
        );
    }

    public function test_login_adopts_guest_cart_when_user_has_no_active_cart(): void
    {
        $user = User::factory()->create([
            'email' => 'guest-merge@example.com',
            'password' => Hash::make('password'),
        ]);

        $variant = $this->availableVariant(
            100,
            80,
            5,
            0
        );

        $guest = $this->getJson('/api/cart')
            ->assertOk();

        $guestToken = $guest
            ->getCookie(
                CartService::GUEST_COOKIE,
                false
            )
            ->getValue();

        $guestId = $guest->json('data.id');

        $this
            ->withHeader(
                CartService::GUEST_HEADER,
                $guestToken
            )
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 2,
                ]
            )
            ->assertCreated();

        $this
            ->withHeader(
                CartService::GUEST_HEADER,
                $guestToken
            )
            ->postJson(
                '/api/auth/login',
                [
                    'identifier' => $user->email,
                    'password' => 'password',
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'carts',
            [
                'id' => $guestId,
                'user_id' => $user->id,
                'session_id' => null,
                'status' => 'active',
            ]
        );

        $this->assertDatabaseMissing(
            'carts',
            [
                'session_id' => $guestToken,
            ]
        );
    }

    public function test_login_merges_guest_and_user_carts_and_preserves_destination_snapshot(): void
    {
        $user = User::factory()->create([
            'email' => 'merge@example.com',
            'password' => Hash::make('password'),
        ]);

        $shared = $this->availableVariant(
            100,
            90,
            10,
            0
        );

        $guestOnly = $this->availableVariant(
            100,
            70,
            10,
            0
        );

        $guest = $this->getJson('/api/cart')
            ->assertOk();

        $guestToken = $guest
            ->getCookie(
                CartService::GUEST_COOKIE,
                false
            )
            ->getValue();

        $guestId = $guest->json('data.id');

        $this
            ->withHeader(
                CartService::GUEST_HEADER,
                $guestToken
            )
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $shared->id,
                    'quantity' => 3,
                ]
            )
            ->assertCreated();

        $this
            ->withHeader(
                CartService::GUEST_HEADER,
                $guestToken
            )
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $guestOnly->id,
                    'quantity' => 1,
                ]
            )
            ->assertCreated();

        $this
            ->actingAs($user)
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $shared->id,
                    'quantity' => 2,
                ]
            )
            ->assertCreated();

        $userItem = CartItem::query()
            ->where(
                'product_variant_id',
                $shared->id
            )
            ->firstOrFail();

        $login = $this
            ->withHeader(
                CartService::GUEST_HEADER,
                $guestToken
            )
            ->postJson(
                '/api/auth/login',
                [
                    'identifier' => $user->email,
                    'password' => 'password',
                ]
            )
            ->assertOk();

        $this->actingAs($user);

        $userCart = Cart::query()
            ->where(
                'user_id',
                $user->id
            )
            ->where(
                'status',
                CartStatus::ACTIVE
            )
            ->firstOrFail();

        $this->assertDatabaseHas(
            'cart_items',
            [
                'cart_id' => $userCart->id,
                'product_variant_id' => $shared->id,
                'quantity' => 5,
                'price_snapshot' => $userItem->price_snapshot,
            ]
        );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'cart_id' => $userCart->id,
                'product_variant_id' => $guestOnly->id,
                'quantity' => 1,
                'price_snapshot' => 70,
            ]
        );

        $this->assertDatabaseHas(
            'carts',
            [
                'id' => $guestId,
                'status' => 'converted',
            ]
        );

        $this->assertNotNull(
            $login->json('token')
        );
    }

    public function test_failed_merge_is_atomic_and_guest_cart_stays_active(): void
    {
        $user = User::factory()->create([
            'email' => 'atomic@example.com',
            'password' => Hash::make('password'),
        ]);

        $variant = $this->availableVariant(
            100,
            80,
            3,
            0
        );

        $guest = $this->getJson('/api/cart')
            ->assertOk();

        $token = $guest
            ->getCookie(
                CartService::GUEST_COOKIE,
                false
            )
            ->getValue();

        $guestId = $guest->json('data.id');

        $this
            ->withHeader(
                CartService::GUEST_HEADER,
                $token
            )
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
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

        $userCart = Cart::query()
            ->where(
                'user_id',
                $user->id
            )
            ->where(
                'status',
                CartStatus::ACTIVE
            )
            ->firstOrFail();

        $this
            ->withHeader(
                CartService::GUEST_HEADER,
                $token
            )
            ->postJson(
                '/api/auth/login',
                [
                    'identifier' => $user->email,
                    'password' => 'password',
                ]
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas(
            'carts',
            [
                'id' => $guestId,
                'status' => 'active',
            ]
        );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'cart_id' => $userCart->id,
                'product_variant_id' => $variant->id,
                'quantity' => 3,
            ]
        );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'cart_id' => $guestId,
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ]
        );
    }

    public function test_login_adopts_guest_cart_when_inventory_is_still_sufficient(): void
    {
        $user = User::factory()->create([
            'email' => 'inventory-ok@example.com',
            'password' => Hash::make('password'),
        ]);

        $variant = $this->availableVariant(
            100,
            80,
            5,
            0
        );

        $guest = $this->getJson('/api/cart')
            ->assertOk();

        $guestToken = $guest
            ->getCookie(
                CartService::GUEST_COOKIE,
                false
            )
            ->getValue();

        $guestId = $guest->json('data.id');

        $this
            ->withHeader(
                CartService::GUEST_HEADER,
                $guestToken
            )
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 2,
                ]
            )
            ->assertCreated();

        $this
            ->withHeader(
                CartService::GUEST_HEADER,
                $guestToken
            )
            ->postJson(
                '/api/auth/login',
                [
                    'identifier' => $user->email,
                    'password' => 'password',
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'carts',
            [
                'id' => $guestId,
                'user_id' => $user->id,
                'session_id' => null,
                'status' => 'active',
            ]
        );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'cart_id' => $guestId,
                'product_variant_id' => $variant->id,
                'quantity' => 2,
                'price_snapshot' => 80,
            ]
        );
    }

    public function test_login_rejects_guest_cart_when_inventory_is_no_longer_sufficient(): void
    {
        $user = User::factory()->create([
            'email' => 'inventory-fail@example.com',
            'password' => Hash::make('password'),
        ]);

        $variant = $this->availableVariant(
            100,
            80,
            5,
            0
        );

        $guest = $this->getJson('/api/cart')
            ->assertOk();

        $guestToken = $guest
            ->getCookie(
                CartService::GUEST_COOKIE,
                false
            )
            ->getValue();

        $guestId = $guest->json('data.id');

        $this
            ->withHeader(
                CartService::GUEST_HEADER,
                $guestToken
            )
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 4,
                ]
            )
            ->assertCreated();

        Inventory::query()
            ->where(
                'product_variant_id',
                $variant->id
            )
            ->update([
                'quantity' => 3,
            ]);

        $this
            ->withHeader(
                CartService::GUEST_HEADER,
                $guestToken
            )
            ->postJson(
                '/api/auth/login',
                [
                    'identifier' => $user->email,
                    'password' => 'password',
                ]
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas(
            'carts',
            [
                'id' => $guestId,
                'session_id' => $guestToken,
                'user_id' => null,
                'status' => 'active',
            ]
        );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'cart_id' => $guestId,
                'product_variant_id' => $variant->id,
                'quantity' => 4,
            ]
        );

        $this->assertDatabaseMissing(
            'carts',
            [
                'user_id' => $user->id,
                'status' => 'active',
            ]
        );
    }

    public function test_login_with_multiple_guest_items_rolls_back_entire_merge_when_one_item_is_unavailable(): void
    {
        $user = User::factory()->create([
            'email' => 'multi-item-atomic@example.com',
            'password' => Hash::make('password'),
        ]);

        $available = $this->availableVariant(
            100,
            80,
            10,
            0
        );

        $unavailable = $this->availableVariant(
            200,
            150,
            5,
            0
        );

        $guest = $this->getJson('/api/cart')
            ->assertOk();

        $guestToken = $guest
            ->getCookie(
                CartService::GUEST_COOKIE,
                false
            )
            ->getValue();

        $guestId = $guest->json('data.id');

        $this
            ->withHeader(
                CartService::GUEST_HEADER,
                $guestToken
            )
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $available->id,
                    'quantity' => 2,
                ]
            )
            ->assertCreated();

        $this
            ->withHeader(
                CartService::GUEST_HEADER,
                $guestToken
            )
            ->postJson(
                '/api/cart/items',
                [
                    'product_variant_id' => $unavailable->id,
                    'quantity' => 4,
                ]
            )
            ->assertCreated();

        Inventory::query()
            ->where(
                'product_variant_id',
                $unavailable->id
            )
            ->update([
                'quantity' => 2,
            ]);

        $this
            ->withHeader(
                CartService::GUEST_HEADER,
                $guestToken
            )
            ->postJson(
                '/api/auth/login',
                [
                    'identifier' => $user->email,
                    'password' => 'password',
                ]
            )
            ->assertUnprocessable();

        // Guest cart must remain completely untouched.
        $this->assertDatabaseHas(
            'carts',
            [
                'id' => $guestId,
                'session_id' => $guestToken,
                'user_id' => null,
                'status' => 'active',
            ]
        );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'cart_id' => $guestId,
                'product_variant_id' => $available->id,
                'quantity' => 2,
            ]
        );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'cart_id' => $guestId,
                'product_variant_id' => $unavailable->id,
                'quantity' => 4,
            ]
        );

        // No partial user cart may have been created.
        $this->assertDatabaseMissing(
            'carts',
            [
                'user_id' => $user->id,
                'status' => 'active',
            ]
        );
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