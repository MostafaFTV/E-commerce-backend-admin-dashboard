<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public function test_guest_cannot_view_orders(): void
    {
        $response = $this->getJson(
            '/api/orders'
        );

        $response->assertUnauthorized();
    }

    public function test_inactive_customer_cannot_view_orders(): void
    {
        $customer = $this->createUser([
            'status' => UserStatus::INACTIVE,
        ]);

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->getJson('/api/orders');

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Account is not active.'
            );
    }

    public function test_suspended_customer_cannot_view_orders(): void
    {
        $customer = $this->createUser([
            'status' => UserStatus::SUSPENDED,
        ]);

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->getJson('/api/orders');

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Account is not active.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function test_customer_can_view_only_their_own_orders(): void
    {
        $customer = $this->createActiveUser();

        $otherCustomer = $this->createActiveUser();

        $ownOrder = $this->createOrderFor(
            $customer,
            'TKB-TEST-OWN-001'
        );

        $this->createOrderFor(
            $otherCustomer,
            'TKB-TEST-OTHER-001'
        );

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->getJson('/api/orders');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $ownOrder->id
            )
            ->assertJsonPath(
                'data.0.order_number',
                'TKB-TEST-OWN-001'
            );

        $response->assertJsonMissing([
            'order_number' => 'TKB-TEST-OTHER-001',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_customer_can_view_their_own_order(): void
    {
        $customer = $this->createActiveUser();

        $order = $this->createOrderFor(
            $customer,
            'TKB-TEST-DETAIL-001'
        );

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->getJson(
                "/api/orders/{$order->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $order->id
            )
            ->assertJsonPath(
                'data.order_number',
                'TKB-TEST-DETAIL-001'
            )
            ->assertJsonPath(
                'data.status',
                OrderStatus::PAID->value
            )
            ->assertJsonPath(
                'data.total',
                120
            );

        $response->assertJsonMissingPath(
            'data.user_id'
        );

        $response->assertJsonMissingPath(
            'data.payments'
        );

        $response->assertJsonMissingPath(
            'data.status_history'
        );
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $customer = $this->createActiveUser();

        $otherCustomer = $this->createActiveUser();

        $otherOrder = $this->createOrderFor(
            $otherCustomer,
            'TKB-TEST-FORBIDDEN-001'
        );

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->getJson(
                "/api/orders/{$otherOrder->id}"
            );

        $response->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Order History
    |--------------------------------------------------------------------------
    */

    public function test_customer_can_view_order_history(): void
    {
        $customer = $this->createActiveUser();

        $order = $this->createOrderFor(
            $customer,
            'TKB-TEST-HISTORY-001'
        );

        $order->statusHistory()->create([
            'from_status' => OrderStatus::PENDING_PAYMENT,
            'to_status' => OrderStatus::PAID,
            'changed_by' => null,
            'note' => 'Payment verified successfully by gateway.',
            'created_at' => now()->subMinutes(2),
        ]);

        $order->statusHistory()->create([
            'from_status' => OrderStatus::PAID,
            'to_status' => OrderStatus::PROCESSING,
            'changed_by' => null,
            'note' => 'Order is being prepared.',
            'created_at' => now()->subMinute(),
        ]);

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->getJson(
                "/api/orders/{$order->id}/history"
            );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath(
                'data.0.from_status',
                OrderStatus::PENDING_PAYMENT->value
            )
            ->assertJsonPath(
                'data.0.to_status',
                OrderStatus::PAID->value
            )
            ->assertJsonPath(
                'data.1.from_status',
                OrderStatus::PAID->value
            )
            ->assertJsonPath(
                'data.1.to_status',
                OrderStatus::PROCESSING->value
            );
    }

    public function test_customer_cannot_view_another_customers_order_history(): void
    {
        $customer = $this->createActiveUser();

        $otherCustomer = $this->createActiveUser();

        $otherOrder = $this->createOrderFor(
            $otherCustomer,
            'TKB-TEST-HISTORY-FORBIDDEN-001'
        );

        $otherOrder->statusHistory()->create([
            'from_status' => OrderStatus::PENDING_PAYMENT,
            'to_status' => OrderStatus::PAID,
            'changed_by' => null,
            'note' => 'Payment verified successfully by gateway.',
            'created_at' => now(),
        ]);

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->getJson(
                "/api/orders/{$otherOrder->id}/history"
            );

        $response->assertNotFound();
    }

    public function test_customer_order_history_is_returned_oldest_to_newest(): void
    {
        $customer = $this->createActiveUser();

        $order = $this->createOrderFor(
            $customer,
            'TKB-TEST-TIMELINE-001'
        );

        $admin = $this->createAdminUser();

        $statusService = app(
            OrderStatusService::class
        );

        $statusService->changeStatus(
            $order,
            OrderStatus::PROCESSING,
            $admin
        );

        $statusService->changeStatus(
            $order->fresh(),
            OrderStatus::SHIPPED,
            $admin
        );

        $statusService->changeStatus(
            $order->fresh(),
            OrderStatus::DELIVERED,
            $admin
        );

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->getJson(
                "/api/orders/{$order->id}/history"
            );

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath(
                'data.0.from_status',
                OrderStatus::PAID->value
            )
            ->assertJsonPath(
                'data.0.to_status',
                OrderStatus::PROCESSING->value
            )
            ->assertJsonPath(
                'data.1.from_status',
                OrderStatus::PROCESSING->value
            )
            ->assertJsonPath(
                'data.1.to_status',
                OrderStatus::SHIPPED->value
            )
            ->assertJsonPath(
                'data.2.from_status',
                OrderStatus::SHIPPED->value
            )
            ->assertJsonPath(
                'data.2.to_status',
                OrderStatus::DELIVERED->value
            );

        $response->assertJsonMissingPath(
            'data.0.changed_by.password'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createActiveUser(
        array $attributes = []
    ): User {
        return $this->createUser(
            array_merge(
                [
                    'status' => UserStatus::ACTIVE,
                ],
                $attributes
            )
        );
    }

    private function createUser(
        array $attributes = []
    ): User {
        return User::factory()->create(
            $attributes
        );
    }

    private function createAdminUser(): User
    {
        return $this->createActiveUser([
            'email' => 'timeline-admin-' . uniqid() . '@tkb.test',
        ]);
    }

    private function createOrderFor(
        User $user,
        string $orderNumber
    ): Order {
        return Order::create([
            'user_id' => $user->id,
            'order_number' => $orderNumber,
            'status' => OrderStatus::PAID,
            'subtotal' => 100,
            'discount' => 0,
            'shipping_cost' => 20,
            'tax' => 0,
            'total' => 120,
            'currency' => 'IRT',
            'shipping_method_id' => null,
            'shipping_address' => [
                'first_name' => 'Test',
                'last_name' => 'Customer',
                'phone' => '+49123456789',
                'country' => 'Germany',
                'state' => 'Hesse',
                'city' => 'Wiesbaden',
                'postal_code' => '65183',
                'address_line_1' => 'Main Street 10',
                'address_line_2' => null,
            ],
            'billing_address' => null,
        ]);
    }
}
