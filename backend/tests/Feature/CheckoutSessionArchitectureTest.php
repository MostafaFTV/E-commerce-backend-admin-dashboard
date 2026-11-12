<?php

namespace Tests\Feature;

use App\Enums\CheckoutSessionStatus;
use App\Models\Cart;
use App\Models\CheckoutSession;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class CheckoutSessionArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_session_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $cart = Cart::create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $checkoutSession = CheckoutSession::create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'status' => CheckoutSessionStatus::ACTIVE,
        ]);

        $this->assertInstanceOf(
            BelongsTo::class,
            $checkoutSession->user()
        );

        $this->assertTrue(
            $checkoutSession->user->is($user)
        );
    }

    public function test_checkout_session_belongs_to_cart(): void
    {
        $user = User::factory()->create();

        $cart = Cart::create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $checkoutSession = CheckoutSession::create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'status' => CheckoutSessionStatus::ACTIVE,
        ]);

        $this->assertInstanceOf(
            BelongsTo::class,
            $checkoutSession->cart()
        );

        $this->assertTrue(
            $checkoutSession->cart->is($cart)
        );
    }

    public function test_checkout_session_belongs_to_shipping_method(): void
    {
        $user = User::factory()->create();

        $cart = Cart::create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $shippingMethod = ShippingMethod::create([
            'name' => 'Standard Shipping',
            'description' => 'Standard delivery',
            'price' => 10,
            'is_active' => true,
        ]);

        $checkoutSession = CheckoutSession::create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'shipping_method_id' => $shippingMethod->id,
            'status' => CheckoutSessionStatus::ACTIVE,
        ]);

        $this->assertInstanceOf(
            BelongsTo::class,
            $checkoutSession->shippingMethod()
        );

        $this->assertTrue(
            $checkoutSession->shippingMethod->is($shippingMethod)
        );
    }

    public function test_checkout_session_casts_status_to_enum(): void
    {
        $checkoutSession = new CheckoutSession([
            'status' => CheckoutSessionStatus::ACTIVE,
        ]);

        $this->assertSame(
            CheckoutSessionStatus::ACTIVE,
            $checkoutSession->status
        );
    }

    public function test_checkout_session_casts_addresses_and_expiration(): void
    {
        $checkoutSession = new CheckoutSession([
            'shipping_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'address' => '123 Main Street',
                'postal_code' => '12345',
                'city' => 'Berlin',
                'country' => 'DE',
            ],
            'billing_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'address' => '456 Billing Street',
                'postal_code' => '54321',
                'city' => 'Berlin',
                'country' => 'DE',
            ],
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->assertIsArray($checkoutSession->shipping_address);
        $this->assertIsArray($checkoutSession->billing_address);
        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $checkoutSession->expires_at
        );
    }

    public function test_checkout_session_status_helpers_work(): void
    {
        $activeSession = new CheckoutSession([
            'status' => CheckoutSessionStatus::ACTIVE,
        ]);

        $this->assertTrue($activeSession->isActive());
        $this->assertFalse($activeSession->isExpired());

        $expiredSession = new CheckoutSession([
            'status' => CheckoutSessionStatus::ACTIVE,
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertTrue($expiredSession->isExpired());
    }
    public function test_database_rejects_multiple_active_checkout_sessions_for_same_user(): void
{
    $user = User::factory()->create();

    $cart = Cart::create([
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    CheckoutSession::create([
        'user_id' => $user->id,
        'cart_id' => $cart->id,
        'status' => CheckoutSessionStatus::ACTIVE,
    ]);

    $this->expectException(\Illuminate\Database\QueryException::class);

    CheckoutSession::create([
        'user_id' => $user->id,
        'cart_id' => $cart->id,
        'status' => CheckoutSessionStatus::ACTIVE,
    ]);
}

public function test_database_rejects_multiple_active_checkout_sessions_for_same_cart(): void
{
    $user = User::factory()->create();

    $cart = Cart::create([
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    CheckoutSession::create([
        'user_id' => $user->id,
        'cart_id' => $cart->id,
        'status' => CheckoutSessionStatus::ACTIVE,
    ]);

    $this->expectException(\Illuminate\Database\QueryException::class);

    CheckoutSession::create([
        'user_id' => $user->id,
        'cart_id' => $cart->id,
        'status' => CheckoutSessionStatus::ACTIVE,
    ]);
}

public function test_completed_checkout_does_not_block_new_active_checkout(): void
{
    $user = User::factory()->create();

    $cart = Cart::create([
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    CheckoutSession::create([
        'user_id' => $user->id,
        'cart_id' => $cart->id,
        'status' => CheckoutSessionStatus::COMPLETED,
    ]);

    $activeSession = CheckoutSession::create([
        'user_id' => $user->id,
        'cart_id' => $cart->id,
        'status' => CheckoutSessionStatus::ACTIVE,
    ]);

    $this->assertSame(
        CheckoutSessionStatus::ACTIVE,
        $activeSession->status
    );
}

public function test_expired_checkout_does_not_block_new_active_checkout(): void
{
    $user = User::factory()->create();

    $cart = Cart::create([
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    CheckoutSession::create([
        'user_id' => $user->id,
        'cart_id' => $cart->id,
        'status' => CheckoutSessionStatus::EXPIRED,
    ]);

    $activeSession = CheckoutSession::create([
        'user_id' => $user->id,
        'cart_id' => $cart->id,
        'status' => CheckoutSessionStatus::ACTIVE,
    ]);

    $this->assertSame(
        CheckoutSessionStatus::ACTIVE,
        $activeSession->status
    );
}

public function test_cancelled_checkout_does_not_block_new_active_checkout(): void
{
    $user = User::factory()->create();

    $cart = Cart::create([
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    CheckoutSession::create([
        'user_id' => $user->id,
        'cart_id' => $cart->id,
        'status' => CheckoutSessionStatus::CANCELLED,
    ]);

    $activeSession = CheckoutSession::create([
        'user_id' => $user->id,
        'cart_id' => $cart->id,
        'status' => CheckoutSessionStatus::ACTIVE,
    ]);

    $this->assertSame(
        CheckoutSessionStatus::ACTIVE,
        $activeSession->status
    );
}
}