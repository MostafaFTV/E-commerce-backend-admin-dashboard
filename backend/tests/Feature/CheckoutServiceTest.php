<?php

namespace Tests\Feature;

use App\Enums\CartStatus;
use App\Enums\CheckoutSessionStatus;
use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\ActivityLog;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CheckoutSession;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_resolve_checkout(): void
    {
        $user = User::factory()->create();

        $cartData = $this->createCartWithItem($user);
        $cart = $cartData['cart'];

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $this->assertInstanceOf(
            CheckoutSession::class,
            $checkout
        );

        $this->assertSame(
            $user->id,
            $checkout->user_id
        );

        $this->assertSame(
            $cart->id,
            $checkout->cart_id
        );

        $this->assertSame(
            CheckoutSessionStatus::ACTIVE,
            $checkout->status
        );
    }

    public function test_checkout_uses_users_active_cart(): void
    {
        $user = User::factory()->create();

        $cartData = $this->createCartWithItem($user);
        $cart = $cartData['cart'];

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $this->assertTrue(
            $checkout->cart->is($cart)
        );
    }

    public function test_user_cannot_use_another_users_cart(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $this->createCartWithItem($anotherUser);

        $service = app(CheckoutService::class);

        $this->expectException(
            ValidationException::class
        );

        $service->resolveCheckout($user);
    }

    public function test_empty_cart_cannot_enter_checkout(): void
    {
        $user = User::factory()->create();

        Cart::create([
            'user_id' => $user->id,
            'status' => CartStatus::ACTIVE,
        ]);

        $service = app(CheckoutService::class);

        $this->expectException(
            ValidationException::class
        );

        $service->resolveCheckout($user);
    }

    public function test_inactive_variant_invalidates_checkout(): void
    {
        $user = User::factory()->create();

        $cart = Cart::create([
            'user_id' => $user->id,
            'status' => CartStatus::ACTIVE,
        ]);

        $product = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::INACTIVE,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'price_snapshot' => 100,
        ]);

        $service = app(CheckoutService::class);

        $this->expectException(
            ValidationException::class
        );

        $service->resolveCheckout($user);
    }

    public function test_sold_variant_invalidates_checkout(): void
    {
        $user = User::factory()->create();

        $cart = Cart::create([
            'user_id' => $user->id,
            'status' => CartStatus::ACTIVE,
        ]);

        $product = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::SOLD,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'price_snapshot' => 100,
        ]);

        $service = app(CheckoutService::class);

        $this->expectException(
            ValidationException::class
        );

        $service->resolveCheckout($user);
    }

    public function test_variant_without_inventory_invalidates_checkout(): void
    {
        $user = User::factory()->create();

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

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'price_snapshot' => 100,
        ]);

        $service = app(CheckoutService::class);

        $this->expectException(
            ValidationException::class
        );

        $service->resolveCheckout($user);
    }

    public function test_made_to_order_variant_can_enter_checkout_without_inventory(): void
    {
        $user = User::factory()->create();

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
            'price' => 150,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'price_snapshot' => 150,
        ]);

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $this->assertInstanceOf(
            CheckoutSession::class,
            $checkout
        );

        $this->assertSame(
            CheckoutSessionStatus::ACTIVE,
            $checkout->status
        );

        $this->assertSame(
            $cart->id,
            $checkout->cart_id
        );
    }

    public function test_checkout_rejects_product_from_inactive_category(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create([
            'is_active' => false,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => ProductStatus::ACTIVE,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
        ]);

        $cart = Cart::create([
            'user_id' => $user->id,
            'status' => CartStatus::ACTIVE,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'price_snapshot' => 100,
        ]);

        $service = app(CheckoutService::class);

        $this->expectException(
            ValidationException::class
        );

        $service->resolveCheckout($user);
    }

    public function test_active_checkout_session_is_reused(): void
    {
        $user = User::factory()->create();

        $cartData = $this->createCartWithItem($user);
        $cart = $cartData['cart'];

        $existingCheckout = CheckoutSession::create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'status' => CheckoutSessionStatus::ACTIVE,
        ]);

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $this->assertTrue(
            $checkout->is($existingCheckout)
        );

        $this->assertSame(
            1,
            CheckoutSession::query()->count()
        );
    }

    public function test_checkout_session_is_created_when_none_exists(): void
    {
        $user = User::factory()->create();

        $cartData = $this->createCartWithItem($user);
        $cart = $cartData['cart'];

        $this->assertDatabaseCount(
            'checkout_sessions',
            0
        );

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $this->assertDatabaseCount(
            'checkout_sessions',
            1
        );

        $this->assertSame(
            $user->id,
            $checkout->user_id
        );

        $this->assertSame(
            $cart->id,
            $checkout->cart_id
        );
    }

    public function test_database_prevents_multiple_active_checkout_sessions_for_the_same_user(): void
    {
        $user = User::factory()->create();

        $cartData = $this->createCartWithItem(
            $user
        );

        CheckoutSession::create([
            'user_id' => $user->id,
            'cart_id' => $cartData['cart']->id,
            'status' => CheckoutSessionStatus::ACTIVE,
        ]);

        try {
            CheckoutSession::create([
                'user_id' => $user->id,
                'cart_id' => $cartData['cart']->id,
                'status' => CheckoutSessionStatus::ACTIVE,
            ]);

            $this->fail(
                'The database should prevent multiple active checkout sessions for the same user.'
            );
        } catch (QueryException $exception) {
            $this->assertSame(
                1,
                CheckoutSession::query()
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->where(
                        'status',
                        CheckoutSessionStatus::ACTIVE
                    )
                    ->count()
            );
        }
    }

    public function test_database_prevents_multiple_active_checkout_sessions_for_the_same_cart(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $cartData = $this->createCartWithItem(
            $user
        );

        CheckoutSession::create([
            'user_id' => $user->id,
            'cart_id' => $cartData['cart']->id,
            'status' => CheckoutSessionStatus::ACTIVE,
        ]);

        try {
            CheckoutSession::create([
                'user_id' => $anotherUser->id,
                'cart_id' => $cartData['cart']->id,
                'status' => CheckoutSessionStatus::ACTIVE,
            ]);

            $this->fail(
                'The database should prevent multiple active checkout sessions for the same cart.'
            );
        } catch (QueryException $exception) {
            $this->assertSame(
                1,
                CheckoutSession::query()
                    ->where(
                        'cart_id',
                        $cartData['cart']->id
                    )
                    ->where(
                        'status',
                        CheckoutSessionStatus::ACTIVE
                    )
                    ->count()
            );
        }
    }

    public function test_shipping_address_can_be_set(): void
    {
        $user = User::factory()->create();

        $this->createCartWithItem($user);

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $address = $this->shippingAddress();

        $updatedCheckout = $service->setShippingAddress(
            $checkout,
            $address
        );

        $this->assertSame(
            $address,
            $updatedCheckout->shipping_address
        );

        $this->assertDatabaseHas(
            'checkout_sessions',
            [
                'id' => $checkout->id,
            ]
        );

        $this->assertSame(
            $address,
            CheckoutSession::find(
                $checkout->id
            )->shipping_address
        );
    }

    public function test_billing_address_can_be_set(): void
    {
        $user = User::factory()->create();

        $this->createCartWithItem($user);

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $address = [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone' => '+49123456789',
            'country' => 'Germany',
            'state' => 'Hesse',
            'city' => 'Wiesbaden',
            'address_line_1' => 'Billing Street 20',
            'address_line_2' => 'Apartment 3',
            'postal_code' => '65185',
        ];

        $updatedCheckout = $service->setBillingAddress(
            $checkout,
            $address
        );

        $this->assertSame(
            $address,
            $updatedCheckout->billing_address
        );
    }

    public function test_checkout_address_cannot_be_set_on_inactive_checkout(): void
    {
        $user = User::factory()->create();

        $this->createCartWithItem($user);

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $checkout->update([
            'status' => CheckoutSessionStatus::COMPLETED,
        ]);

        $address = $this->shippingAddress();

        $this->expectException(
            ValidationException::class
        );

        $service->setShippingAddress(
            $checkout,
            $address
        );
    }

    public function test_shipping_method_can_be_set(): void
    {
        $user = User::factory()->create();

        $this->createCartWithItem($user);

        $shippingMethod = $this->createShippingMethod([
            'price' => 20,
        ]);

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $updatedCheckout = $service->setShippingMethod(
            $checkout,
            $shippingMethod->id
        );

        $this->assertSame(
            $shippingMethod->id,
            $updatedCheckout->shipping_method_id
        );

        $this->assertTrue(
            $updatedCheckout->shippingMethod->is(
                $shippingMethod
            )
        );
    }

    public function test_inactive_shipping_method_cannot_be_selected(): void
    {
        $user = User::factory()->create();

        $this->createCartWithItem($user);

        $shippingMethod = $this->createShippingMethod([
            'is_active' => false,
        ]);

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $this->expectException(
            ValidationException::class
        );

        $service->setShippingMethod(
            $checkout,
            $shippingMethod->id
        );
    }

    public function test_place_order_creates_order_with_pending_payment_status(): void
    {
        $user = User::factory()->create();

        $cartData = $this->createCartWithItem(
            $user,
            quantity: 2,
            priceSnapshot: 100
        );

        $shippingMethod = $this->createShippingMethod([
            'price' => 20,
        ]);

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $checkout = $service->setShippingAddress(
            $checkout,
            $this->shippingAddress()
        );

        $checkout = $service->setShippingMethod(
            $checkout,
            $shippingMethod->id
        );

        $order = $service->placeOrder(
            $user,
            $checkout
        );

        $this->assertInstanceOf(
            Order::class,
            $order
        );

        $this->assertSame(
            $user->id,
            $order->user_id
        );

        $this->assertSame(
            OrderStatus::PENDING_PAYMENT,
            $order->status
        );

        $this->assertSame(
            200.0,
            (float) $order->subtotal
        );

        $this->assertSame(
            20.0,
            (float) $order->shipping_cost
        );

        $this->assertSame(
            220.0,
            (float) $order->total
        );

        $this->assertSame(
            $shippingMethod->id,
            $order->shipping_method_id
        );

        $this->assertSame(
            $this->shippingAddress(),
            $order->shipping_address
        );

        $this->assertSame(
            1,
            Order::query()->count()
        );

        $this->assertNotEmpty(
            $order->order_number
        );

        $this->assertStringStartsWith(
            'TKB-',
            $order->order_number
        );

        $this->assertNotNull(
            $cartData['cart']->fresh()->id
        );
    }

    public function test_place_order_creates_order_activity_log(): void
    {
        $user = User::factory()->create();

        $cartData = $this->createCartWithItem(
            $user,
            quantity: 2,
            priceSnapshot: 100
        );

        $shippingMethod = $this->createShippingMethod([
            'price' => 20,
        ]);

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $checkout = $service->setShippingAddress(
            $checkout,
            $this->shippingAddress()
        );

        $checkout = $service->setShippingMethod(
            $checkout,
            $shippingMethod->id
        );

        $order = $service->placeOrder(
            $user,
            $checkout
        );

        $log = ActivityLog::query()
            ->where(
                'action',
                'orders.created'
            )
            ->where(
                'properties->order->id',
                $order->id
            )
            ->latest('id')
            ->first();

        $this->assertNotNull(
            $log
        );

        $this->assertSame(
            'orders.created',
            $log->action
        );

        $this->assertSame(
            'Order created.',
            $log->description
        );

        $this->assertSame(
            $user->id,
            $log->user_id
        );

        $this->assertSame(
            Order::class,
            $log->properties['subject']['type']
        );

        $this->assertSame(
            $order->id,
            $log->properties['subject']['id']
        );

        $this->assertSame(
            $order->id,
            $log->properties['order']['id']
        );

        $this->assertSame(
            $order->order_number,
            $log->properties['order']['order_number']
        );

        $this->assertSame(
            OrderStatus::PENDING_PAYMENT->value,
            $log->properties['order']['status']
        );

        $this->assertSame(
            $user->id,
            $log->properties['user_id']
        );

        $this->assertSame(
            200.0,
            (float) $log->properties['subtotal']
        );

        $this->assertSame(
            20.0,
            (float) $log->properties['shipping_cost']
        );

        $this->assertSame(
            220.0,
            (float) $log->properties['total']
        );

        $this->assertSame(
            'IRT',
            $log->properties['currency']
        );

        $this->assertSame(
            $shippingMethod->id,
            $log->properties['shipping_method_id']
        );
    }

    public function test_place_order_creates_order_item_snapshot(): void
    {
        $user = User::factory()->create();

        $cartData = $this->createCartWithItem(
            $user,
            quantity: 2,
            priceSnapshot: 95
        );

        $shippingMethod = $this->createShippingMethod([
            'price' => 0,
        ]);

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $checkout = $service->setShippingAddress(
            $checkout,
            $this->shippingAddress()
        );

        $checkout = $service->setShippingMethod(
            $checkout,
            $shippingMethod->id
        );

        $order = $service->placeOrder(
            $user,
            $checkout
        );

        $orderItem = $order->items()->first();

        $this->assertNotNull(
            $orderItem
        );

        $this->assertSame(
            $cartData['product']->id,
            $orderItem->product_id
        );

        $this->assertSame(
            $cartData['variant']->id,
            $orderItem->product_variant_id
        );

        $this->assertSame(
            $cartData['product']->name,
            $orderItem->product_name
        );

        $this->assertSame(
            $cartData['variant']->sku,
            $orderItem->sku
        );

        $this->assertSame(
            2,
            $orderItem->quantity
        );

        $this->assertSame(
            95.0,
            (float) $orderItem->unit_price
        );

        $this->assertSame(
            190.0,
            (float) $orderItem->total
        );
    }

    public function test_place_order_reserves_inventory(): void
    {
        $user = User::factory()->create();

        $cartData = $this->createCartWithItem(
            $user,
            quantity: 2,
            priceSnapshot: 100
        );

        $shippingMethod = $this->createShippingMethod();

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $checkout = $service->setShippingAddress(
            $checkout,
            $this->shippingAddress()
        );

        $checkout = $service->setShippingMethod(
            $checkout,
            $shippingMethod->id
        );

        $service->placeOrder(
            $user,
            $checkout
        );

        $this->assertDatabaseHas('inventories', [
            'id' => $cartData['inventory']->id,
            'quantity' => 10,
            'reserved_quantity' => 2,
        ]);
    }

    public function test_place_order_creates_reservation_inventory_movement(): void
    {
        $user = User::factory()->create();

        $cartData = $this->createCartWithItem(
            $user,
            quantity: 2,
            priceSnapshot: 100
        );

        $shippingMethod = $this->createShippingMethod();

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $checkout = $service->setShippingAddress(
            $checkout,
            $this->shippingAddress()
        );

        $checkout = $service->setShippingMethod(
            $checkout,
            $shippingMethod->id
        );

        $order = $service->placeOrder(
            $user,
            $checkout
        );

        $movement = InventoryMovement::query()
            ->where(
                'order_id',
                $order->id
            )
            ->where(
                'inventory_id',
                $cartData['inventory']->id
            )
            ->first();

        $this->assertNotNull(
            $movement
        );

        $this->assertSame(
            InventoryMovementType::RESERVATION,
            $movement->type
        );

        $this->assertSame(
            0,
            $movement->quantity_delta
        );

        $this->assertSame(
            2,
            $movement->reserved_delta
        );

        $this->assertSame(
            $user->id,
            $movement->created_by
        );

        $this->assertSame(
            $order->id,
            $movement->order_id
        );
    }

    public function test_place_order_for_made_to_order_variant_does_not_reserve_inventory(): void
    {
        $user = User::factory()->create();

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
            'price' => 150,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
            'price_snapshot' => 150,
        ]);

        $shippingMethod = $this->createShippingMethod([
            'price' => 20,
        ]);

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $checkout = $service->setShippingAddress(
            $checkout,
            $this->shippingAddress()
        );

        $checkout = $service->setShippingMethod(
            $checkout,
            $shippingMethod->id
        );

        $order = $service->placeOrder(
            $user,
            $checkout
        );

        $this->assertInstanceOf(
            Order::class,
            $order
        );

        $this->assertSame(
            OrderStatus::PENDING_PAYMENT,
            $order->status
        );

        $this->assertSame(
            450.0,
            (float) $order->subtotal
        );

        $this->assertSame(
            470.0,
            (float) $order->total
        );

        $this->assertSame(
            0,
            Inventory::query()->count()
        );

        $this->assertSame(
            0,
            InventoryMovement::query()
                ->where(
                    'order_id',
                    $order->id
                )
                ->count()
        );

        $this->assertSame(
            1,
            $order->items()->count()
        );

        $orderItem = $order->items()->first();

        $this->assertSame(
            $variant->id,
            $orderItem->product_variant_id
        );

        $this->assertSame(
            '150.00',
            number_format(
                (float) $orderItem->unit_price,
                2,
                '.',
                ''
            )
        );

        $this->assertSame(
            3,
            $orderItem->quantity
        );
    }

    public function test_place_order_completes_checkout_and_converts_cart(): void
    {
        $user = User::factory()->create();

        $cartData = $this->createCartWithItem($user);

        $shippingMethod = $this->createShippingMethod();

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $checkout = $service->setShippingAddress(
            $checkout,
            $this->shippingAddress()
        );

        $checkout = $service->setShippingMethod(
            $checkout,
            $shippingMethod->id
        );

        $service->placeOrder(
            $user,
            $checkout
        );

        $this->assertSame(
            CheckoutSessionStatus::COMPLETED,
            $checkout->fresh()->status
        );

        $this->assertSame(
            CartStatus::CONVERTED,
            $cartData['cart']->fresh()->status
        );
    }

    public function test_place_order_requires_shipping_address(): void
    {
        $user = User::factory()->create();

        $this->createCartWithItem($user);

        $shippingMethod = $this->createShippingMethod();

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $checkout = $service->setShippingMethod(
            $checkout,
            $shippingMethod->id
        );

        try {
            $service->placeOrder(
                $user,
                $checkout
            );

            $this->fail(
                'Expected ValidationException was not thrown.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'shipping_address',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount(
            'orders',
            0
        );
    }

    public function test_place_order_requires_shipping_method(): void
    {
        $user = User::factory()->create();

        $this->createCartWithItem($user);

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $checkout = $service->setShippingAddress(
            $checkout,
            $this->shippingAddress()
        );

        try {
            $service->placeOrder(
                $user,
                $checkout
            );

            $this->fail(
                'Expected ValidationException was not thrown.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'shipping_method_id',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount(
            'orders',
            0
        );
    }

    public function test_place_order_rejects_insufficient_inventory(): void
    {
        $user = User::factory()->create();

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
            'quantity' => 10,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 2,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 11,
            'price_snapshot' => 100,
        ]);

        $service = app(CheckoutService::class);

        $this->expectException(
            ValidationException::class
        );

        $service->resolveCheckout($user);
    }

    public function test_user_cannot_place_order_for_another_users_checkout(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $cartData = $this->createCartWithItem(
            $anotherUser
        );

        $shippingMethod = $this->createShippingMethod();

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout(
            $anotherUser
        );

        $checkout = $service->setShippingAddress(
            $checkout,
            $this->shippingAddress()
        );

        $checkout = $service->setShippingMethod(
            $checkout,
            $shippingMethod->id
        );

        $this->expectException(
            ValidationException::class
        );

        $service->placeOrder(
            $user,
            $checkout
        );

        $this->assertDatabaseCount(
            'orders',
            0
        );

        $this->assertSame(
            10,
            $cartData['inventory']->fresh()->quantity
        );
    }

    public function test_place_order_uses_price_snapshot_not_current_variant_price(): void
    {
        $user = User::factory()->create();

        $cartData = $this->createCartWithItem(
            $user,
            quantity: 2,
            priceSnapshot: 80
        );

        $cartData['variant']->update([
            'price' => 150,
        ]);

        $shippingMethod = $this->createShippingMethod([
            'price' => 10,
        ]);

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $checkout = $service->setShippingAddress(
            $checkout,
            $this->shippingAddress()
        );

        $checkout = $service->setShippingMethod(
            $checkout,
            $shippingMethod->id
        );

        $order = $service->placeOrder(
            $user,
            $checkout
        );

        $this->assertSame(
            170.0,
            (float) $order->subtotal
                + (float) $order->shipping_cost
        );

        $this->assertSame(
            160.0,
            (float) $order->subtotal
        );

        $this->assertSame(
            80.0,
            (float) $order->items()->first()->unit_price
        );
    }

    public function test_completed_checkout_cannot_be_used_to_create_a_second_order(): void
    {
        $user = User::factory()->create();

        $cartData = $this->createCartWithItem(
            $user,
            quantity: 2,
            priceSnapshot: 100
        );

        $shippingMethod = $this->createShippingMethod([
            'price' => 20,
        ]);

        $service = app(CheckoutService::class);

        $checkout = $service->resolveCheckout($user);

        $checkout = $service->setShippingAddress(
            $checkout,
            $this->shippingAddress()
        );

        $checkout = $service->setShippingMethod(
            $checkout,
            $shippingMethod->id
        );

        $firstOrder = $service->placeOrder(
            $user,
            $checkout
        );

        $this->assertSame(
            1,
            Order::query()->count()
        );

        $this->assertSame(
            CheckoutSessionStatus::COMPLETED,
            $checkout->fresh()->status
        );

        $this->expectException(
            ValidationException::class
        );

        $service->placeOrder(
            $user,
            $checkout->fresh()
        );

        $this->assertNotNull(
            $firstOrder->fresh()
        );

        $this->assertSame(
            1,
            Order::query()->count()
        );

        $this->assertSame(
            1,
            InventoryMovement::query()
                ->where(
                    'order_id',
                    $firstOrder->id
                )
                ->where(
                    'type',
                    InventoryMovementType::RESERVATION
                )
                ->count()
        );

        $this->assertSame(
            2,
            $cartData['inventory']->fresh()->reserved_quantity
        );
    }

    public function test_place_order_rolls_back_order_and_inventory_reservation_when_later_item_fails(): void
    {
        $user = User::factory()->create();

        $cart = Cart::create([
            'user_id' => $user->id,
            'status' => CartStatus::ACTIVE,
        ]);

        /*
        |--------------------------------------------------------------------------
        | First item — enough inventory
        |--------------------------------------------------------------------------
        */

        $firstProduct = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
            'price' => 100,
        ]);

        $firstVariant = ProductVariant::factory()->create([
            'product_id' => $firstProduct->id,
            'status' => VariantStatus::ACTIVE,
            'price' => 100,
        ]);

        $firstInventory = Inventory::create([
            'product_variant_id' => $firstVariant->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 2,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $firstVariant->id,
            'quantity' => 2,
            'price_snapshot' => 100,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Second item — initially enough inventory
        |--------------------------------------------------------------------------
        */

        $secondProduct = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
            'price' => 50,
        ]);

        $secondVariant = ProductVariant::factory()->create([
            'product_id' => $secondProduct->id,
            'status' => VariantStatus::ACTIVE,
            'price' => 50,
        ]);

        $secondInventory = Inventory::create([
            'product_variant_id' => $secondVariant->id,
            'quantity' => 2,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 1,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $secondVariant->id,
            'quantity' => 2,
            'price_snapshot' => 50,
        ]);

        $shippingMethod = $this->createShippingMethod([
            'price' => 20,
        ]);

        $service = app(CheckoutService::class);

        /*
        |--------------------------------------------------------------------------
        | Initial checkout validation succeeds.
        |--------------------------------------------------------------------------
        */

        $checkout = $service->resolveCheckout($user);

        $checkout = $service->setShippingAddress(
            $checkout,
            $this->shippingAddress()
        );

        $checkout = $service->setShippingMethod(
            $checkout,
            $shippingMethod->id
        );

        /*
        |--------------------------------------------------------------------------
        | Simulate another customer consuming stock.
        |--------------------------------------------------------------------------
        */

        $secondInventory->update([
            'quantity' => 1,
        ]);

        /*
        |--------------------------------------------------------------------------
        | placeOrder() must fail inside its transaction.
        |--------------------------------------------------------------------------
        */

        try {
            $service->placeOrder(
                $user,
                $checkout
            );

            $this->fail(
                'Expected ValidationException was not thrown.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'cart',
                $exception->errors()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Entire transaction must be rolled back.
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseCount(
            'orders',
            0
        );

        $this->assertDatabaseCount(
            'order_items',
            0
        );

        $this->assertDatabaseCount(
            'inventory_movements',
            0
        );

        /*
        |--------------------------------------------------------------------------
        | First inventory must not retain the reservation.
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            10,
            $firstInventory->fresh()->quantity
        );

        $this->assertSame(
            0,
            $firstInventory->fresh()->reserved_quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Second inventory keeps the external stock change.
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            1,
            $secondInventory->fresh()->quantity
        );

        $this->assertSame(
            0,
            $secondInventory->fresh()->reserved_quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Cart and checkout must remain active.
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            CartStatus::ACTIVE,
            $cart->fresh()->status
        );

        $this->assertSame(
            CheckoutSessionStatus::ACTIVE,
            $checkout->fresh()->status
        );

        $this->assertDatabaseCount(
            'activity_logs',
            0
        );
    }

    private function createCartWithItem(
        User $user,
        int $quantity = 1,
        float $priceSnapshot = 100
    ): array {
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
            'price' => 100,
        ]);

        $inventory = Inventory::create([
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 2,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'price_snapshot' => $priceSnapshot,
        ]);

        return [
            'cart' => $cart,
            'product' => $product,
            'variant' => $variant,
            'inventory' => $inventory,
        ];
    }

    private function createShippingMethod(
        array $overrides = []
    ): ShippingMethod {
        return ShippingMethod::create(
            array_merge(
                [
                    'name' => 'Test Shipping',
                    'description' =>
                        'Temporary shipping method for tests',
                    'price' => 0,
                    'estimated_days' => 3,
                    'is_active' => true,
                    'sort_order' => 1,
                ],
                $overrides
            )
        );
    }

    private function shippingAddress(): array
    {
        return [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+49123456789',
            'country' => 'Germany',
            'state' => 'Hesse',
            'city' => 'Wiesbaden',
            'address_line_1' => 'Main Street 10',
            'address_line_2' => null,
            'postal_code' => '65183',
        ];
    }
}