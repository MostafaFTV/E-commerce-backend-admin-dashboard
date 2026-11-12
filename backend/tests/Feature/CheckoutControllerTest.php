<?php

namespace Tests\Feature;

use App\Enums\CartStatus;
use App\Enums\CheckoutSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use App\Enums\VariantStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CheckoutSession;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'storefront.online_checkout_enabled' => true,
        ]);
    }

    public function test_guest_cannot_view_checkout(): void
    {
        $this
            ->getJson('/api/checkout')
            ->assertUnauthorized();
    }

    public function test_authenticated_customer_receives_checkout_contract(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $cart = Cart::create([
            'user_id' => $user->id,
            'status' => CartStatus::ACTIVE,
        ]);

        $product = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
            'price' => 100,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
            'price' => 80,
        ]);

        Inventory::create([
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'reserved_quantity' => 2,
            'low_stock_threshold' => 2,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'price_snapshot' => 80,
        ]);

        $shippingMethod = ShippingMethod::create([
            'name' => 'Express Shipping',
            'description' => 'Fast delivery',
            'price' => 25,
            'estimated_days' => 2,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $checkout = CheckoutSession::create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'status' => CheckoutSessionStatus::ACTIVE,
            'shipping_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'phone' => '+33123456789',
                'country' => 'France',
                'state' => 'Ile-de-France',
                'city' => 'Paris',
                'address_line_1' => '10 Rue de Paris',
                'address_line_2' => null,
                'postal_code' => '75001',
            ],
            'billing_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'phone' => '+33123456789',
                'country' => 'France',
                'state' => 'Ile-de-France',
                'city' => 'Paris',
                'address_line_1' => '20 Rue de Paris',
                'address_line_2' => null,
                'postal_code' => '75002',
            ],
            'shipping_method_id' => $shippingMethod->id,
            'expires_at' => now()->addMinutes(30),
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/checkout');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'shipping_address',
                    'billing_address',
                    'shipping_method_id',
                    'expires_at',
                    'cart' => [
                        'id',
                        'items',
                        'subtotal',
                    ],
                ],
            ])
            ->assertJsonPath(
                'data.id',
                $checkout->id
            )
            ->assertJsonPath(
                'data.status',
                CheckoutSessionStatus::ACTIVE->value
            )
            ->assertJsonPath(
                'data.shipping_method_id',
                $shippingMethod->id
            )
            ->assertJsonPath(
                'data.shipping_address.city',
                'Paris'
            )
            ->assertJsonPath(
                'data.billing_address.city',
                'Paris'
            )
            ->assertJsonPath(
                'data.cart.id',
                $cart->id
            )
            ->assertJsonPath(
                'data.cart.items.0.product_variant.id',
                $variant->id
            )
            ->assertJsonPath(
                'data.cart.items.0.quantity',
                2
            )
            ->assertJsonPath(
                'data.cart.items.0.price_snapshot',
                80
            )
            ->assertJsonPath(
                'data.cart.items.0.line_total',
                160
            )
            ->assertJsonPath(
                'data.cart.subtotal',
                160
            );

        $this->assertNotNull(
            $response->json('data.expires_at')
        );
    }

    public function test_checkout_response_keeps_made_to_order_item_available_without_inventory_quantity(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $cart = Cart::create([
            'user_id' => $user->id,
            'status' => CartStatus::ACTIVE,
        ]);

        $product = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
            'price' => 150,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::MADE_TO_ORDER,
            'price' => 175,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'price_snapshot' => 175,
        ]);

        CheckoutSession::create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'status' => CheckoutSessionStatus::ACTIVE,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/checkout');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.cart.items.0.product_variant.status',
                VariantStatus::MADE_TO_ORDER->value
            )
            ->assertJsonPath(
                'data.cart.items.0.availability.available',
                true
            )
            ->assertJsonMissingPath(
                'data.cart.items.0.availability.available_quantity'
            );
    }

    public function test_checkout_uses_the_authenticated_users_active_checkout_session(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $cart = Cart::create([
            'user_id' => $user->id,
            'status' => CartStatus::ACTIVE,
        ]);

        $product = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
        ]);

        Inventory::create([
            'product_variant_id' => $variant->id,
            'quantity' => 5,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 1,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'price_snapshot' => 100,
        ]);

        $checkout = CheckoutSession::create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'status' => CheckoutSessionStatus::ACTIVE,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/checkout');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $checkout->id
            )
            ->assertJsonPath(
                'data.cart.id',
                $cart->id
            );
    }

    public function test_checkout_rejects_product_from_inactive_category(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $category = Category::factory()->create([
            'is_active' => false,
        ]);

        $cart = Cart::create([
            'user_id' => $user->id,
            'status' => CartStatus::ACTIVE,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => ProductStatus::ACTIVE,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
            'price' => 100,
        ]);

        Inventory::create([
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 2,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'price_snapshot' => 100,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/checkout');

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'cart',
            ]);

        $this->assertDatabaseCount(
            'checkout_sessions',
            0
        );
    }

    public function test_place_order_rejects_product_when_category_is_deactivated_after_checkout(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $category = Category::factory()->create([
            'is_active' => true,
        ]);

        $cart = Cart::create([
            'user_id' => $user->id,
            'status' => CartStatus::ACTIVE,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => ProductStatus::ACTIVE,
            'price' => 100,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
            'price' => 100,
        ]);

        Inventory::create([
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 2,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'price_snapshot' => 100,
        ]);

        $shippingMethod = ShippingMethod::create([
            'name' => 'Secure Shipping',
            'description' => 'Category deactivation test shipping',
            'price' => 20,
            'estimated_days' => 3,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/checkout')
            ->assertCreated();

        $checkout = CheckoutSession::query()
            ->where(
                'user_id',
                $user->id
            )
            ->firstOrFail();

        $checkout->update([
            'shipping_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'phone' => '+33123456789',
                'country' => 'France',
                'state' => 'Ile-de-France',
                'city' => 'Paris',
                'address_line_1' => '10 Rue de Paris',
                'address_line_2' => null,
                'postal_code' => '75001',
            ],
            'shipping_method_id' => $shippingMethod->id,
        ]);

        $category->update([
            'is_active' => false,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                '/api/checkout/place-order'
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'cart',
            ]);

        $this->assertDatabaseCount(
            'orders',
            0
        );
    }

    public function test_client_cannot_override_order_totals_when_placing_order(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $cart = Cart::create([
            'user_id' => $user->id,
            'status' => CartStatus::ACTIVE,
        ]);

        $product = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
            'price' => 100,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
            'price' => 80,
        ]);

        Inventory::create([
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 2,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'price_snapshot' => 80,
        ]);

        $shippingMethod = ShippingMethod::create([
            'name' => 'Secure Express Shipping',
            'description' => 'Checkout tampering test shipping',
            'price' => 25,
            'estimated_days' => 2,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $checkout = CheckoutSession::create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'status' => CheckoutSessionStatus::ACTIVE,
            'shipping_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'phone' => '+33123456789',
                'country' => 'France',
                'state' => 'Ile-de-France',
                'city' => 'Paris',
                'address_line_1' => '10 Rue de Paris',
                'address_line_2' => null,
                'postal_code' => '75001',
            ],
            'shipping_method_id' => $shippingMethod->id,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                '/api/checkout/place-order',
                [
                    // Malicious client-controlled values.
                    'subtotal' => 1,
                    'discount' => 999,
                    'shipping_cost' => 1,
                    'tax' => 999,
                    'total' => 1,
                    'price' => 1,
                    'amount' => 1,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                OrderStatus::PENDING_PAYMENT->value
            )
            ->assertJsonPath(
                'data.subtotal',
                160
            )
            ->assertJsonPath(
                'data.discount',
                0
            )
            ->assertJsonPath(
                'data.shipping_cost',
                25
            )
            ->assertJsonPath(
                'data.tax',
                0
            )
            ->assertJsonPath(
                'data.total',
                185
            )
            ->assertJsonPath(
                'data.items.0.unit_price',
                80
            )
            ->assertJsonPath(
                'data.items.0.total',
                160
            );

        $order = Order::query()->firstOrFail();

        $this->assertSame(
            160.0,
            (float) $order->subtotal
        );

        $this->assertSame(
            25.0,
            (float) $order->shipping_cost
        );

        $this->assertSame(
            185.0,
            (float) $order->total
        );

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'unit_price' => 80,
            'total' => 160,
        ]);
    }
}
