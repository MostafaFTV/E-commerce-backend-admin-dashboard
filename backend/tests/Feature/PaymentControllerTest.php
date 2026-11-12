<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\CartStatus;
use App\Enums\CheckoutSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use App\Enums\VariantStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CheckoutSession;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'storefront.online_checkout_enabled' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Payment
    |--------------------------------------------------------------------------
    */

    public function test_customer_can_create_payment_for_their_own_order(): void
    {
        $data = $this->preparePendingOrder();

        $response = $this
            ->actingAs(
                $data['user'],
                'sanctum'
            )
            ->postJson(
                "/api/payments/{$data['order']->id}/pay"
            );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'order_id',
                    'status',
                    'amount',
                    'currency',
                    'method',
                    'gateway_reference',
                    'transaction_id',
                    'payment_url',
                    'paid_at',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonPath(
                'data.order_id',
                $data['order']->id
            )
            ->assertJsonPath(
                'data.status',
                PaymentStatus::PENDING->value
            )
            ->assertJsonPath(
                'data.amount',
                220
            )
            ->assertJsonPath(
                'data.currency',
                'IRT'
            )
            ->assertJsonPath(
                'data.method',
                'gateway'
            );

        $payment = Payment::firstOrFail();

        $this->assertSame(
            'http://fake-gateway.test/pay/' .
            $payment->id,
            $response->json(
                'data.payment_url'
            )
        );

        $this->assertDatabaseCount(
            'payments',
            1
        );
    }

    public function test_inactive_customer_cannot_create_payment(): void
    {
        $data = $this->preparePendingOrder();

        $data['user']->update([
            'status' => UserStatus::INACTIVE,
        ]);

        $response = $this
            ->actingAs(
                $data['user'],
                'sanctum'
            )
            ->postJson(
                "/api/payments/{$data['order']->id}/pay"
            );

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Account is not active.'
            );

        $this->assertDatabaseCount(
            'payments',
            0
        );
    }

    public function test_suspended_customer_cannot_create_payment(): void
    {
        $data = $this->preparePendingOrder();

        $data['user']->update([
            'status' => UserStatus::SUSPENDED,
        ]);

        $response = $this
            ->actingAs(
                $data['user'],
                'sanctum'
            )
            ->postJson(
                "/api/payments/{$data['order']->id}/pay"
            );

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Account is not active.'
            );

        $this->assertDatabaseCount(
            'payments',
            0
        );
    }

    public function test_client_cannot_override_payment_amount_or_currency(): void
    {
        $data = $this->preparePendingOrder();

        $response = $this
            ->actingAs(
                $data['user'],
                'sanctum'
            )
            ->postJson(
                "/api/payments/{$data['order']->id}/pay",
                [
                    'amount' =>
                        1,

                    'currency' =>
                        'EUR',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.amount',
                220
            )
            ->assertJsonPath(
                'data.currency',
                'IRT'
            );

        $payment = Payment::firstOrFail();

        $this->assertSame(
            220.0,
            (float) $payment->amount
        );

        $this->assertSame(
            'IRT',
            $payment->currency
        );
    }

    public function test_customer_cannot_create_payment_for_another_users_order(): void
    {
        $ownerData = $this->preparePendingOrder();

        $anotherUser = $this->createActiveUser([
            'email' =>
                'another-payment-user-'
                . uniqid()
                . '@tkb.test',
        ]);

        $response = $this
            ->actingAs(
                $anotherUser,
                'sanctum'
            )
            ->postJson(
                "/api/payments/{$ownerData['order']->id}/pay"
            );

        $response
            ->assertForbidden()
            ->assertJson([
                'message' =>
                    'You are not allowed to perform this action.',
            ])
            ->assertJsonPath(
                'errors.order.0',
                'You are not allowed to pay for this order.'
            );

        $this->assertDatabaseCount(
            'payments',
            0
        );
    }

    public function test_guest_cannot_create_payment(): void
    {
        $data = $this->preparePendingOrder();

        $this
            ->postJson(
                "/api/payments/{$data['order']->id}/pay"
            )
            ->assertUnauthorized();

        $this->assertDatabaseCount(
            'payments',
            0
        );
    }

    public function test_customer_cannot_create_payment_for_already_paid_order(): void
    {
        $data = $this->preparePendingOrder();

        $data['order']->update([
            'status' =>
                OrderStatus::PAID,
        ]);

        $response = $this
            ->actingAs(
                $data['user'],
                'sanctum'
            )
            ->postJson(
                "/api/payments/{$data['order']->id}/pay"
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'order',
            ]);

        $this->assertDatabaseCount(
            'payments',
            0
        );
    }

    public function test_repeated_payment_request_reuses_existing_pending_payment(): void
    {
        $data = $this->preparePendingOrder();

        $firstResponse = $this
            ->actingAs(
                $data['user'],
                'sanctum'
            )
            ->postJson(
                "/api/payments/{$data['order']->id}/pay"
            );

        $firstResponse->assertOk();

        $firstPayment = Payment::firstOrFail();

        $secondResponse = $this
            ->actingAs(
                $data['user'],
                'sanctum'
            )
            ->postJson(
                "/api/payments/{$data['order']->id}/pay"
            );

        $secondResponse
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $firstPayment->id
            )
            ->assertJsonPath(
                'data.gateway_reference',
                $firstPayment->gateway_reference
            );

        $this->assertDatabaseCount(
            'payments',
            1
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Failure
    |--------------------------------------------------------------------------
    */

    public function test_invalid_gateway_response_during_payment_creation_returns_502_and_rolls_back_payment(): void
    {
        $data = $this->preparePendingOrder();

        $failingGateway = new class implements PaymentGatewayInterface
        {
            public function requestPayment(
                Payment $payment
            ): array {
                return [
                    'reference' =>
                        '',

                    'payment_url' =>
                        '',
                ];
            }

            public function extractReference(
                array $callbackData
            ): string {
                return '';
            }

            public function verifyPayment(
                Payment $payment,
                array $callbackData
            ): array {
                return [
                    'success' =>
                        false,
                ];
            }

            public function refundPayment(
                Payment $payment
            ): array {
                return [
                    'success' =>
                        false,
                ];
            }
        };

        app()->instance(
            PaymentGatewayInterface::class,
            $failingGateway
        );

        $response = $this
            ->actingAs(
                $data['user'],
                'sanctum'
            )
            ->postJson(
                "/api/payments/{$data['order']->id}/pay"
            );

        $response
            ->assertStatus(502)
            ->assertJson([
                'message' =>
                    'Payment gateway error.',
            ])
            ->assertJsonPath(
                'errors.payment.0',
                'The payment gateway returned an invalid payment response.'
            );

        $this->assertDatabaseCount(
            'payments',
            0
        );

        $this->assertSame(
            OrderStatus::PENDING_PAYMENT,
            $data['order']->fresh()->status
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Callback
    |--------------------------------------------------------------------------
    */

    public function test_payment_callback_returns_minimal_public_payment_contract(): void
    {
        $data = $this->preparePendingOrder();

        $createResponse = $this
            ->actingAs(
                $data['user'],
                'sanctum'
            )
            ->postJson(
                "/api/payments/{$data['order']->id}/pay"
            )
            ->assertOk();

        $payment = Payment::firstOrFail();

        $response = $this->getJson(
            '/api/payment/callback?'
            . http_build_query([
                'reference' =>
                    $payment->gateway_reference,

                'gateway_status' =>
                    'success',

                'transaction_id' =>
                    'TEST-TXN-001',

                'amount' =>
                    220,

                'currency' =>
                    'IRT',
            ])
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Payment completed successfully.'
            )
            ->assertJsonPath(
                'payment.status',
                PaymentStatus::PAID->value
            )
            ->assertJsonPath(
                'payment.paid_at',
                fn ($value) => is_string($value)
            );

        $response->assertJsonMissingPath(
            'payment.id'
        );

        $response->assertJsonMissingPath(
            'payment.order_id'
        );

        $response->assertJsonMissingPath(
            'payment.amount'
        );

        $response->assertJsonMissingPath(
            'payment.currency'
        );

        $response->assertJsonMissingPath(
            'payment.method'
        );

        $response->assertJsonMissingPath(
            'payment.gateway_reference'
        );

        $response->assertJsonMissingPath(
            'payment.transaction_id'
        );

        $response->assertJsonMissingPath(
            'payment.payment_url'
        );

        $response->assertJsonMissingPath(
            'payment.created_at'
        );

        $response->assertJsonMissingPath(
            'payment.updated_at'
        );

        $response->assertJsonMissingPath(
            'payment.gateway_response'
        );

        $this->assertSame(
            $payment->gateway_reference,
            $createResponse->json(
                'data.gateway_reference'
            )
        );
    }

    public function test_payment_callback_does_not_require_authentication(): void
    {
        $data = $this->preparePendingOrder();

        $createResponse = $this
            ->actingAs(
                $data['user'],
                'sanctum'
            )
            ->postJson(
                "/api/payments/{$data['order']->id}/pay"
            )
            ->assertOk();

        $payment = Payment::firstOrFail();

        $response = $this->getJson(
            '/api/payment/callback?'
            . http_build_query([
                'reference' =>
                    $createResponse->json(
                        'data.gateway_reference'
                    ),

                'gateway_status' =>
                    'success',

                'transaction_id' =>
                    'GUEST-CALLBACK-001',

                'amount' =>
                    220,

                'currency' =>
                    'IRT',
            ])
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'payment.status',
                PaymentStatus::PAID->value
            );
    }

    public function test_payment_callback_does_not_expose_gateway_response(): void
    {
        $data = $this->preparePendingOrder();

        $createResponse = $this
            ->actingAs(
                $data['user'],
                'sanctum'
            )
            ->postJson(
                "/api/payments/{$data['order']->id}/pay"
            )
            ->assertOk();

        $payment = Payment::firstOrFail();

        $response = $this->getJson(
            '/api/payment/callback?'
            . http_build_query([
                'reference' =>
                    $createResponse->json(
                        'data.gateway_reference'
                    ),

                'gateway_status' =>
                    'success',

                'transaction_id' =>
                    'PUBLIC-CALLBACK-TXN',

                'amount' =>
                    220,

                'currency' =>
                    'IRT',
            ])
        );

        $response
            ->assertOk()
            ->assertJsonMissingPath(
                'payment.gateway_response'
            );
    }

    public function test_payment_callback_does_not_expose_payment_method_secrets(): void
    {
        $data = $this->preparePendingOrder();

        $createResponse = $this
            ->actingAs(
                $data['user'],
                'sanctum'
            )
            ->postJson(
                "/api/payments/{$data['order']->id}/pay"
            )
            ->assertOk();

        $response = $this->getJson(
            '/api/payment/callback?'
            . http_build_query([
                'reference' =>
                    $createResponse->json(
                        'data.gateway_reference'
                    ),

                'gateway_status' =>
                    'success',

                'transaction_id' =>
                    'PUBLIC-SECRETS-CHECK',

                'amount' =>
                    220,

                'currency' =>
                    'IRT',
            ])
        );

        $response
            ->assertOk()
            ->assertJsonMissingPath(
                'payment.gateway_response'
            );

        $response
            ->assertJsonMissingPath(
                'payment.card_number'
            );

        $response
            ->assertJsonMissingPath(
                'payment.card_token'
            );

        $response
            ->assertJsonMissingPath(
                'payment.authorization_code'
            );

        $response
            ->assertJsonMissingPath(
                'payment.secret'
            );
    }

    public function test_failed_payment_callback_returns_failed_payment(): void
    {
        $data = $this->preparePendingOrder();

        $createResponse = $this
            ->actingAs(
                $data['user'],
                'sanctum'
            )
            ->postJson(
                "/api/payments/{$data['order']->id}/pay"
            )
            ->assertOk();

        $response = $this->getJson(
            '/api/payment/callback?'
            . http_build_query([
                'reference' =>
                    $createResponse->json(
                        'data.gateway_reference'
                    ),

                'gateway_status' =>
                    'failed',
            ])
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Payment failed.'
            )
            ->assertJsonPath(
                'payment.status',
                PaymentStatus::FAILED->value
            );

        $this->assertSame(
            PaymentStatus::FAILED,
            Payment::firstOrFail()->status
        );
    }

    public function test_invalid_payment_callback_is_rejected(): void
    {
        $response = $this->getJson(
            '/api/payment/callback?'
            . http_build_query([
                'reference' =>
                    'UNKNOWN-REFERENCE',

                'gateway_status' =>
                    'success',

                'transaction_id' =>
                    'INVALID-CALLBACK-001',
            ])
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'payment',
            ]);
    }

    public function test_payment_callback_is_rate_limited(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '10.254.254.10',
        ]);

        for ($i = 1; $i <= 30; $i++) {
            $response = $this->getJson(
                '/api/payment/callback?'
                . http_build_query([
                    'reference' =>
                        'UNKNOWN-REFERENCE-' . $i,

                    'gateway_status' =>
                        'failed',
                ])
            );

            $response->assertUnprocessable();

            $response->assertJsonValidationErrors([
                'payment',
            ]);
        }

        $response = $this->getJson(
            '/api/payment/callback?'
            . http_build_query([
                'reference' =>
                    'UNKNOWN-REFERENCE-31',

                'gateway_status' =>
                    'failed',
            ])
        );

        $response->assertStatus(429);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createActiveUser(
        array $attributes = []
    ): User {
        return User::factory()->create(
            array_merge(
                [
                    'status' =>
                        UserStatus::ACTIVE,
                ],
                $attributes
            )
        );
    }

    private function preparePendingOrder(): array
    {
        $user = $this->createActiveUser([
            'email' =>
                'payment-controller-'
                . uniqid()
                . '@tkb.test',

            'password' =>
                Hash::make('password'),
        ]);

        $cart = Cart::create([
            'user_id' =>
                $user->id,

            'status' =>
                CartStatus::ACTIVE,
        ]);

        $product = Product::factory()->create([
            'status' =>
                ProductStatus::ACTIVE,

            'price' =>
                100,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' =>
                $product->id,

            'status' =>
                VariantStatus::ACTIVE,

            'price' =>
                100,
        ]);

        Inventory::create([
            'product_variant_id' =>
                $variant->id,

            'quantity' =>
                10,

            'reserved_quantity' =>
                0,

            'low_stock_threshold' =>
                2,
        ]);

        CartItem::create([
            'cart_id' =>
                $cart->id,

            'product_variant_id' =>
                $variant->id,

            'quantity' =>
                2,

            'price_snapshot' =>
                100,
        ]);

        $shippingMethod = ShippingMethod::create([
            'name' =>
                'Test Express',

            'description' =>
                'Payment controller test shipping',

            'price' =>
                20,

            'estimated_days' =>
                2,

            'is_active' =>
                true,

            'sort_order' =>
                1,
        ]);

        $checkout = CheckoutSession::create([
            'user_id' =>
                $user->id,

            'cart_id' =>
                $cart->id,

            'status' =>
                CheckoutSessionStatus::ACTIVE,

            'shipping_address' => [
                'first_name' =>
                    'John',

                'last_name' =>
                    'Doe',

                'phone' =>
                    '+49123456789',

                'country' =>
                    'Germany',

                'state' =>
                    'Hesse',

                'city' =>
                    'Wiesbaden',

                'address_line_1' =>
                    'Main Street 10',

                'address_line_2' =>
                    null,

                'postal_code' =>
                    '65183',
            ],

            'shipping_method_id' =>
                $shippingMethod->id,
        ]);

        $checkoutService = app(
            CheckoutService::class
        );

        $order = $checkoutService->placeOrder(
            $user,
            $checkout
        );

        return [
            'user' =>
                $user,

            'order' =>
                $order,
        ];
    }
}