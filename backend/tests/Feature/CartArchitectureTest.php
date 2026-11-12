<?php

namespace Tests\Feature;

use App\Enums\CartStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CartArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_belongs_to_user_and_active_cart_can_be_resolved(): void
    {
        $user = User::factory()->create();
        $cart = Cart::create([
            'user_id' => $user->id,
            'status' => CartStatus::ACTIVE,
        ]);

        $this->assertTrue($cart->user->is($user));
        $this->assertTrue(Cart::activeForUser($user)->is($cart));
        $this->assertTrue($cart->isActive());
    }

    public function test_guest_session_cart_is_supported_without_an_api(): void
    {
        $cart = Cart::create([
            'session_id' => 'session-123',
            'status' => CartStatus::ACTIVE,
        ]);

        $this->assertNull($cart->user_id);
        $this->assertSame('session-123', $cart->session_id);
    }

    public function test_cart_requires_a_user_or_session_owner(): void
    {
        $this->expectException(ValidationException::class);

        Cart::create([
            'status' => CartStatus::ACTIVE,
        ]);
    }

    public function test_cart_cannot_have_both_user_and_session_owners(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);

        Cart::create([
            'user_id' => $user->id,
            'session_id' => 'guest-token',
            'status' => CartStatus::ACTIVE,
        ]);
    }

    public function test_database_rejects_multiple_active_carts_for_the_same_user(): void
    {
        $user = User::factory()->create();

        Cart::create([
            'user_id' => $user->id,
            'status' => CartStatus::ACTIVE,
        ]);

        $this->expectException(QueryException::class);

        Cart::create([
            'user_id' => $user->id,
            'status' => CartStatus::ACTIVE,
        ]);
    }

    public function test_database_rejects_multiple_active_carts_for_the_same_guest_session(): void
    {
        $sessionId = 'guest-session-123';

        Cart::create([
            'session_id' => $sessionId,
            'status' => CartStatus::ACTIVE,
        ]);

        $this->expectException(QueryException::class);

        Cart::create([
            'session_id' => $sessionId,
            'status' => CartStatus::ACTIVE,
        ]);
    }

    public function test_cart_items_use_variant_identity_and_allow_different_variants_of_one_product(): void
    {
        $user = User::factory()->create();

        $cart = Cart::create([
            'user_id' => $user->id,
            'status' => CartStatus::ACTIVE,
        ]);

        $product = Product::factory()->create();

        $firstVariant = ProductVariant::factory()->create([
            'product_id' => $product->id,
        ]);

        $secondVariant = ProductVariant::factory()->create([
            'product_id' => $product->id,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $firstVariant->id,
            'quantity' => 1,
            'price_snapshot' => 80,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $secondVariant->id,
            'quantity' => 2,
            'price_snapshot' => 100,
        ]);

        $cart->load('items.variant.product');

        $this->assertCount(2, $cart->items);
        $this->assertTrue(
            $cart->items->first()->variant->product->is($product)
        );

        $this->assertTrue(
            $firstVariant->fresh()->cartItems->first()->cart->is($cart)
        );
    }

    public function test_same_variant_cannot_be_added_twice_to_one_cart(): void
    {
        $user = User::factory()->create();

        $cart = Cart::create([
            'user_id' => $user->id,
            'status' => CartStatus::ACTIVE,
        ]);

        $variant = ProductVariant::factory()->create();

        $attributes = [
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'price_snapshot' => 80,
        ];

        CartItem::create($attributes);

        $this->expectException(QueryException::class);

        CartItem::create($attributes);
    }

    public function test_quantity_and_price_snapshot_must_be_valid(): void
    {
        $user = User::factory()->create();

        $cart = Cart::create([
            'user_id' => $user->id,
            'status' => CartStatus::ACTIVE,
        ]);

        $variant = ProductVariant::factory()->create();

        try {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_variant_id' => $variant->id,
                'quantity' => 0,
                'price_snapshot' => 80,
            ]);

            $this->fail('Expected quantity validation exception.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(ValidationException::class);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'price_snapshot' => -1,
        ]);
    }

    public function test_price_snapshot_is_independent_from_current_catalog_price_and_subtotal_is_derived(): void
    {
        $user = User::factory()->create();

        $cart = Cart::create([
            'user_id' => $user->id,
            'status' => CartStatus::ACTIVE,
        ]);

        $product = Product::factory()->create([
            'price' => 100,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 80,
        ]);

        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'price_snapshot' => 80,
        ]);

        $variant->update([
            'price' => 100,
        ]);

        $cart->load('items');

        $this->assertSame(
            80.0,
            (float) $item->fresh()->price_snapshot
        );

        $this->assertSame(
            160.0,
            $cart->calculateSubtotal()
        );

        $this->assertFalse(
            Schema::hasColumn('cart_items', 'inventory_quantity')
        );
    }

    public function test_cart_item_schema_supports_product_and_optional_variant_identity(): void
    {
        $columns = Schema::getColumnListing('cart_items');

        $this->assertContains('cart_id', $columns);
        $this->assertContains('product_id', $columns);
        $this->assertContains('product_variant_id', $columns);
        $this->assertContains('price_snapshot', $columns);
        $this->assertTrue(Schema::hasColumn('cart_items', 'quantity'));
    }
}
