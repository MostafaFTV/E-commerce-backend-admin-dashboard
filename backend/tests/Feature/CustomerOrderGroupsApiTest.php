<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderGroupsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_group_includes_only_active_order_statuses(): void
    {
        $user = $this->customer();

        foreach (OrderStatus::cases() as $status) {
            $this->order($user, $status);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/orders?group=current');

        $response->assertOk()->assertJsonCount(4, 'data');

        $statuses = array_column($response->json('data'), 'status');

        $this->assertEqualsCanonicalizing([
            'pending_payment',
            'paid',
            'processing',
            'shipped',
        ], $statuses);
    }

    public function test_terminal_groups_filter_correctly(): void
    {
        $user = $this->customer();

        foreach (OrderStatus::cases() as $status) {
            $this->order($user, $status);
        }

        foreach (['delivered', 'returned', 'cancelled'] as $group) {
            $response = $this->actingAs($user, 'sanctum')
                ->getJson("/api/orders?group={$group}");

            $response
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.status', $group);
        }
    }

    public function test_group_counts_exclude_other_customers_orders(): void
    {
        $user = $this->customer();
        $other = $this->customer();

        $this->order($user, OrderStatus::PAID);
        $this->order($user, OrderStatus::PROCESSING);
        $this->order($user, OrderStatus::DELIVERED);
        $this->order($user, OrderStatus::RETURNED);
        $this->order($other, OrderStatus::CANCELLED);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/orders?group=current');

        $response
            ->assertOk()
            ->assertJsonPath('meta.group_counts.current', 2)
            ->assertJsonPath('meta.group_counts.delivered', 1)
            ->assertJsonPath('meta.group_counts.returned', 1)
            ->assertJsonPath('meta.group_counts.cancelled', 0);
    }

    public function test_group_pagination_returns_only_requested_page(): void
    {
        $user = $this->customer();

        for ($i = 0; $i < 25; $i++) {
            $this->order($user, OrderStatus::PAID);
        }

        $first = $this->actingAs($user, 'sanctum')
            ->getJson('/api/orders?group=current&page=1');

        $second = $this->actingAs($user, 'sanctum')
            ->getJson('/api/orders?group=current&page=2');

        $first
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 25);

        $second
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2);

        $this->assertEmpty(array_intersect(
            array_column($first->json('data'), 'id'),
            array_column($second->json('data'), 'id')
        ));
    }

    public function test_invalid_group_returns_validation_error(): void
    {
        $user = $this->customer();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/orders?group=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('group');
    }

    public function test_legacy_index_still_returns_all_orders(): void
    {
        $user = $this->customer();

        $this->order($user, OrderStatus::PAID);
        $this->order($user, OrderStatus::DELIVERED);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_group_response_preserves_pagination_metadata(): void
    {
        $user = $this->customer();

        $this->order($user, OrderStatus::PAID);
        $this->order($user, OrderStatus::DELIVERED);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/orders?group=current&page=1');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.group_counts.current', 1)
            ->assertJsonPath('meta.group_counts.delivered', 1)
            ->assertJsonPath('meta.group_counts.returned', 0)
            ->assertJsonPath('meta.group_counts.cancelled', 0);
    }
    private function customer(): User
    {
        return User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);
    }

    private function order(User $user, OrderStatus $status): Order
    {
        static $sequence = 0;

        $sequence++;

        return Order::create([
            'user_id' => $user->id,
            'order_number' => 'TKB-GROUP-TEST-' . $sequence,
            'status' => $status,
            'subtotal' => 100,
            'discount' => 0,
            'shipping_cost' => 0,
            'tax' => 0,
            'total' => 100,
            'currency' => 'IRT',
            'shipping_method_id' => null,
            'shipping_address' => [
                'first_name' => 'Test',
                'last_name' => 'Customer',
                'phone' => '09123456789',
                'country' => 'Iran',
                'state' => 'Tehran',
                'city' => 'Tehran',
                'address_line_1' => 'Test Street 10',
                'address_line_2' => null,
                'postal_code' => '1234567890',
            ],
            'billing_address' => null,
        ]);
    }
}