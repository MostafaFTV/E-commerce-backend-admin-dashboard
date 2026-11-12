<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingMethodApiTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Public API
    |--------------------------------------------------------------------------
    */

    public function test_public_endpoint_returns_only_active_shipping_methods(): void
    {
        $active = $this->createShippingMethod([
            'name' => 'Express Delivery',
            'is_active' => true,
            'sort_order' => 1,
            'price' => 20,
        ]);

        $inactive = $this->createShippingMethod([
            'name' => 'Disabled Delivery',
            'is_active' => false,
            'sort_order' => 2,
            'price' => 10,
        ]);

        $response = $this
            ->getJson('/api/shipping-methods')
            ->assertOk();

        $ids = collect(
            $response->json('data')
        )->pluck('id');

        $this->assertTrue(
            $ids->contains($active->id)
        );

        $this->assertFalse(
            $ids->contains($inactive->id)
        );
    }

    public function test_public_shipping_methods_are_ordered_by_sort_order_then_id(): void
    {
        $first = $this->createShippingMethod([
            'sort_order' => 1,
        ]);

        $second = $this->createShippingMethod([
            'sort_order' => 2,
        ]);

        $sameOrderLowerId = $this->createShippingMethod([
            'sort_order' => 3,
        ]);

        $sameOrderHigherId = $this->createShippingMethod([
            'sort_order' => 3,
        ]);

        $response = $this
            ->getJson('/api/shipping-methods')
            ->assertOk();

        $ids = collect(
            $response->json('data')
        )->pluck('id')
            ->values();

        $this->assertSame(
            [
                $first->id,
                $second->id,
                $sameOrderLowerId->id,
                $sameOrderHigherId->id,
            ],
            $ids->all()
        );
    }

    public function test_public_shipping_method_response_exposes_expected_fields(): void
    {
        $shippingMethod = $this->createShippingMethod([
            'name' => 'Express Delivery',
            'description' => 'Fast delivery',
            'price' => 25,
            'estimated_days' => '1-2 days',
            'is_active' => true,
            'sort_order' => 4,
        ]);

        $response = $this
            ->getJson('/api/shipping-methods')
            ->assertOk();

        $response
            ->assertJsonPath(
                'data.0.id',
                $shippingMethod->id
            )
            ->assertJsonPath(
                'data.0.name',
                'Express Delivery'
            )
            ->assertJsonPath(
                'data.0.description',
                'Fast delivery'
            )
            ->assertJsonPath(
                'data.0.price',
                25
            )
            ->assertJsonPath(
                'data.0.estimated_days',
                '1-2 days'
            )
            ->assertJsonPath(
                'data.0.is_active',
                true
            )
            ->assertJsonPath(
                'data.0.sort_order',
                4
            );
    }

    public function test_public_shipping_methods_do_not_require_authentication(): void
    {
        $this->createShippingMethod();

        $this
            ->getJson('/api/shipping-methods')
            ->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Admin List / Show
    |--------------------------------------------------------------------------
    */

    public function test_admin_with_view_permission_can_list_shipping_methods(): void
    {
        $admin = $this->adminUser([
            'shipping_methods.view',
        ]);

        $shippingMethod = $this->createShippingMethod([
            'name' => 'Standard Delivery',
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/shipping-methods')
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $shippingMethod->id
            );
    }

    public function test_admin_can_search_shipping_methods(): void
    {
        $admin = $this->adminUser([
            'shipping_methods.view',
        ]);

        $matching = $this->createShippingMethod([
            'name' => 'Express Delivery',
            'description' => 'Fast shipping',
        ]);

        $this->createShippingMethod([
            'name' => 'Standard Delivery',
            'description' => 'Regular delivery',
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/shipping-methods?search=Express'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $matching->id
            );
    }

    public function test_admin_can_filter_shipping_methods_by_active_status(): void
    {
        $admin = $this->adminUser([
            'shipping_methods.view',
        ]);

        $active = $this->createShippingMethod([
            'is_active' => true,
        ]);

        $inactive = $this->createShippingMethod([
            'is_active' => false,
        ]);

        $activeResponse = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/shipping-methods?is_active=1'
            )
            ->assertOk();

        $activeIds = collect(
            $activeResponse->json('data')
        )->pluck('id');

        $this->assertTrue(
            $activeIds->contains($active->id)
        );

        $this->assertFalse(
            $activeIds->contains($inactive->id)
        );

        $inactiveResponse = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/shipping-methods?is_active=0'
            )
            ->assertOk();

        $inactiveIds = collect(
            $inactiveResponse->json('data')
        )->pluck('id');

        $this->assertTrue(
            $inactiveIds->contains($inactive->id)
        );

        $this->assertFalse(
            $inactiveIds->contains($active->id)
        );
    }

    public function test_admin_with_view_permission_can_show_shipping_method(): void
    {
        $admin = $this->adminUser([
            'shipping_methods.view',
        ]);

        $shippingMethod = $this->createShippingMethod();

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/admin/shipping-methods/{$shippingMethod->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $shippingMethod->id
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Create
    |--------------------------------------------------------------------------
    */

    public function test_admin_with_create_permission_can_create_shipping_method(): void
    {
        $admin = $this->adminUser([
            'shipping_methods.create',
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/shipping-methods',
                [
                    'name' => 'Premium Delivery',
                    'description' => 'Premium shipping service',
                    'price' => 35,
                    'estimated_days' => '1-2 days',
                    'is_active' => true,
                    'sort_order' => 5,
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.name',
                'Premium Delivery'
            )
            ->assertJsonPath(
                'data.price',
                35
            )
            ->assertJsonPath(
                'data.is_active',
                true
            );

        $this->assertDatabaseHas(
            'shipping_methods',
            [
                'name' => 'Premium Delivery',
                'price' => 35,
                'is_active' => true,
                'sort_order' => 5,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Update
    |--------------------------------------------------------------------------
    */

    public function test_admin_with_update_permission_can_update_shipping_method(): void
    {
        $admin = $this->adminUser([
            'shipping_methods.update',
        ]);

        $shippingMethod = $this->createShippingMethod([
            'name' => 'Old Delivery',
            'price' => 10,
            'estimated_days' => '4-6 days',
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson(
                "/api/admin/shipping-methods/{$shippingMethod->id}",
                [
                    'name' => 'Updated Delivery',
                    'price' => 30,
                    'estimated_days' => '1-2 days',
                    'is_active' => true,
                    'sort_order' => 2,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Updated Delivery'
            )
            ->assertJsonPath(
                'data.price',
                30
            );

        $this->assertDatabaseHas(
            'shipping_methods',
            [
                'id' => $shippingMethod->id,
                'name' => 'Updated Delivery',
                'price' => 30,
                'estimated_days' => '1-2 days',
            ]
        );
    }

    public function test_admin_can_partially_update_shipping_method(): void
    {
        $admin = $this->adminUser([
            'shipping_methods.update',
        ]);

        $shippingMethod = $this->createShippingMethod([
            'name' => 'Standard Delivery',
            'price' => 10,
            'estimated_days' => '3-5 days',
            'sort_order' => 7,
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson(
                "/api/admin/shipping-methods/{$shippingMethod->id}",
                [
                    'price' => 15,
                ]
            )
            ->assertOk();

        $shippingMethod->refresh();

        $this->assertSame(
            'Standard Delivery',
            $shippingMethod->name
        );

        $this->assertSame(
            15.0,
            (float) $shippingMethod->price
        );

        $this->assertSame(
            '3-5 days',
            $shippingMethod->estimated_days
        );

        $this->assertSame(
            7,
            $shippingMethod->sort_order
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Delete
    |--------------------------------------------------------------------------
    */

    public function test_admin_with_delete_permission_can_delete_unused_shipping_method(): void
    {
        $admin = $this->adminUser([
            'shipping_methods.delete',
        ]);

        $shippingMethod = $this->createShippingMethod();

        $this
            ->actingAs($admin, 'sanctum')
            ->deleteJson(
                "/api/admin/shipping-methods/{$shippingMethod->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Shipping method deleted successfully.'
            )
            ->assertJsonPath(
                'deleted',
                true
            )
            ->assertJsonPath(
                'deactivated',
                false
            );

        $this->assertDatabaseMissing(
            'shipping_methods',
            [
                'id' => $shippingMethod->id,
            ]
        );
    }

    public function test_shipping_method_with_historical_orders_is_deactivated_instead_of_deleted(): void
    {
        $admin = $this->adminUser([
            'shipping_methods.delete',
        ]);

        $shippingMethod = $this->createShippingMethod([
            'name' => 'Historical Shipping',
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'TEST-ORDER-' . uniqid(),
            'status' => OrderStatus::PENDING_PAYMENT,
            'subtotal' => 100,
            'discount' => 0,
            'shipping_cost' => $shippingMethod->price,
            'tax' => 0,
            'total' => 100 + (float) $shippingMethod->price,
            'currency' => 'IRT',
            'shipping_method_id' => $shippingMethod->id,
            'shipping_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'phone' => '+49123456789',
                'country' => 'Germany',
                'state' => 'Berlin',
                'city' => 'Berlin',
                'address_line_1' => 'Main Street 10',
                'address_line_2' => null,
                'postal_code' => '10115',
            ],
            'billing_address' => null,
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->deleteJson(
                "/api/admin/shipping-methods/{$shippingMethod->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'This shipping method has historical orders and was deactivated instead of deleted.'
            )
            ->assertJsonPath(
                'deleted',
                false
            )
            ->assertJsonPath(
                'deactivated',
                true
            );

        $shippingMethod->refresh();

        $this->assertTrue(
            $shippingMethod->exists
        );

        $this->assertFalse(
            $shippingMethod->is_active
        );

        $this->assertDatabaseHas(
            'orders',
            [
                'id' => $order->id,
                'shipping_method_id' => $shippingMethod->id,
            ]
        );

        $this->assertDatabaseHas(
            'shipping_methods',
            [
                'id' => $shippingMethod->id,
                'is_active' => false,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Activity Logging
    |--------------------------------------------------------------------------
    */

    public function test_creating_shipping_method_creates_activity_log(): void
    {
        $admin = $this->adminUser([
            'shipping_methods.create',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/shipping-methods',
                [
                    'name' => 'Activity Shipping',
                    'description' => 'Activity shipping method',
                    'price' => 25,
                    'estimated_days' => '2-3 days',
                    'is_active' => true,
                    'sort_order' => 3,
                ]
            )
            ->assertCreated();

        $shippingMethodId = $response->json(
            'data.id'
        );

        $log = ActivityLog::query()
            ->where(
                'action',
                'shipping_methods.created'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $admin->id,
            $log->user_id
        );

        $this->assertSame(
            'shipping_methods.created',
            $log->action
        );

        $this->assertSame(
            'Shipping method created.',
            $log->description
        );

        $this->assertSame(
            ShippingMethod::class,
            $log->properties['subject']['type']
        );

        $this->assertSame(
            $shippingMethodId,
            $log->properties['subject']['id']
        );

        $this->assertSame(
            $shippingMethodId,
            $log->properties['shipping_method']['id']
        );

        $this->assertSame(
            'Activity Shipping',
            $log->properties['shipping_method']['name']
        );
    }

    public function test_updating_shipping_method_creates_activity_log(): void
    {
        $admin = $this->adminUser([
            'shipping_methods.update',
        ]);

        $shippingMethod = $this->createShippingMethod([
            'name' => 'Old Shipping',
            'price' => 10,
            'estimated_days' => '4-6 days',
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson(
                "/api/admin/shipping-methods/{$shippingMethod->id}",
                [
                    'name' => 'Updated Shipping',
                    'price' => 30,
                    'estimated_days' => '1-2 days',
                ]
            )
            ->assertOk();

        $log = ActivityLog::query()
            ->where(
                'action',
                'shipping_methods.updated'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $admin->id,
            $log->user_id
        );

        $this->assertSame(
            'shipping_methods.updated',
            $log->action
        );

        $this->assertSame(
            ShippingMethod::class,
            $log->properties['subject']['type']
        );

        $this->assertSame(
            $shippingMethod->id,
            $log->properties['subject']['id']
        );

        $this->assertContains(
            'name',
            $log->properties['changed_fields']
        );

        $this->assertContains(
            'price',
            $log->properties['changed_fields']
        );

        $this->assertContains(
            'estimated_days',
            $log->properties['changed_fields']
        );

        $this->assertSame(
            'Updated Shipping',
            $log->properties['changes']['name']
        );

        $this->assertSame(
            '30',
            (string) $log->properties['changes']['price']
        );

        $this->assertSame(
            '1-2 days',
            $log->properties['changes']['estimated_days']
        );
    }

    public function test_deleting_unused_shipping_method_creates_activity_log(): void
    {
        $admin = $this->adminUser([
            'shipping_methods.delete',
        ]);

        $shippingMethod = $this->createShippingMethod();

        $this
            ->actingAs($admin, 'sanctum')
            ->deleteJson(
                "/api/admin/shipping-methods/{$shippingMethod->id}"
            )
            ->assertOk();

        $log = ActivityLog::query()
            ->where(
                'action',
                'shipping_methods.deleted'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $admin->id,
            $log->user_id
        );

        $this->assertSame(
            'shipping_methods.deleted',
            $log->action
        );

        $this->assertSame(
            ShippingMethod::class,
            $log->properties['subject']['type']
        );

        $this->assertSame(
            $shippingMethod->id,
            $log->properties['subject']['id']
        );

        $this->assertSame(
            $shippingMethod->name,
            $log->properties['shipping_method']['name']
        );
    }

    public function test_shipping_method_with_historical_orders_creates_deactivation_log(): void
    {
        $admin = $this->adminUser([
            'shipping_methods.delete',
        ]);

        $shippingMethod = $this->createShippingMethod([
            'name' => 'Historical Shipping',
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'AUDIT-ORDER-' . uniqid(),
            'status' => OrderStatus::PENDING_PAYMENT,
            'subtotal' => 100,
            'discount' => 0,
            'shipping_cost' => $shippingMethod->price,
            'tax' => 0,
            'total' => 100 + (float) $shippingMethod->price,
            'currency' => 'IRT',
            'shipping_method_id' => $shippingMethod->id,
            'shipping_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'phone' => '+49123456789',
                'country' => 'Germany',
                'state' => 'Berlin',
                'city' => 'Berlin',
                'address_line_1' => 'Main Street 10',
                'address_line_2' => null,
                'postal_code' => '10115',
            ],
            'billing_address' => null,
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->deleteJson(
                "/api/admin/shipping-methods/{$shippingMethod->id}"
            )
            ->assertOk();

        $log = ActivityLog::query()
            ->where(
                'action',
                'shipping_methods.deactivated'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $admin->id,
            $log->user_id
        );

        $this->assertSame(
            'shipping_methods.deactivated',
            $log->action
        );

        $this->assertSame(
            ShippingMethod::class,
            $log->properties['subject']['type']
        );

        $this->assertSame(
            $shippingMethod->id,
            $log->properties['subject']['id']
        );

        $this->assertSame(
            $shippingMethod->id,
            $log->properties['shipping_method']['id']
        );

        $this->assertSame(
            'historical_orders',
            $log->properties['reason']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    public function test_create_requires_name_and_active_status(): void
    {
        $admin = $this->adminUser([
            'shipping_methods.create',
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/shipping-methods',
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'is_active',
            ]);
    }

    public function test_negative_price_is_rejected(): void
    {
        $admin = $this->adminUser([
            'shipping_methods.create',
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/shipping-methods',
                [
                    'name' => 'Invalid Shipping',
                    'price' => -1,
                    'is_active' => true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'price',
            ]);
    }

    public function test_negative_sort_order_is_rejected(): void
    {
        $admin = $this->adminUser([
            'shipping_methods.create',
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/shipping-methods',
                [
                    'name' => 'Invalid Shipping',
                    'price' => 10,
                    'is_active' => true,
                    'sort_order' => -1,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'sort_order',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication / Authorization
    |--------------------------------------------------------------------------
    */

    public function test_guest_cannot_access_admin_shipping_methods(): void
    {
        $shippingMethod = $this->createShippingMethod();

        $this
            ->getJson('/api/admin/shipping-methods')
            ->assertUnauthorized();

        $this
            ->getJson(
                "/api/admin/shipping-methods/{$shippingMethod->id}"
            )
            ->assertUnauthorized();

        $this
            ->postJson(
                '/api/admin/shipping-methods',
                [
                    'name' => 'Guest Shipping',
                    'is_active' => true,
                ]
            )
            ->assertUnauthorized();

        $this
            ->putJson(
                "/api/admin/shipping-methods/{$shippingMethod->id}",
                [
                    'name' => 'Guest Update',
                ]
            )
            ->assertUnauthorized();

        $this
            ->deleteJson(
                "/api/admin/shipping-methods/{$shippingMethod->id}"
            )
            ->assertUnauthorized();
    }

    public function test_user_without_view_permission_cannot_view_shipping_methods(): void
    {
        $admin = $this->adminUser([]);

        $shippingMethod = $this->createShippingMethod();

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/shipping-methods')
            ->assertForbidden();

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/admin/shipping-methods/{$shippingMethod->id}"
            )
            ->assertForbidden();
    }

    public function test_user_without_create_permission_cannot_create_shipping_method(): void
    {
        $admin = $this->adminUser([
            'shipping_methods.view',
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/shipping-methods',
                [
                    'name' => 'Unauthorized Shipping',
                    'price' => 10,
                    'is_active' => true,
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseMissing(
            'shipping_methods',
            [
                'name' => 'Unauthorized Shipping',
            ]
        );
    }

    public function test_user_without_update_permission_cannot_update_shipping_method(): void
    {
        $admin = $this->adminUser([
            'shipping_methods.view',
        ]);

        $shippingMethod = $this->createShippingMethod([
            'name' => 'Protected Shipping',
            'price' => 10,
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson(
                "/api/admin/shipping-methods/{$shippingMethod->id}",
                [
                    'name' => 'Hacked Shipping',
                    'price' => 1,
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseHas(
            'shipping_methods',
            [
                'id' => $shippingMethod->id,
                'name' => 'Protected Shipping',
                'price' => 10,
            ]
        );
    }

    public function test_user_without_delete_permission_cannot_delete_shipping_method(): void
    {
        $admin = $this->adminUser([
            'shipping_methods.view',
        ]);

        $shippingMethod = $this->createShippingMethod();

        $this
            ->actingAs($admin, 'sanctum')
            ->deleteJson(
                "/api/admin/shipping-methods/{$shippingMethod->id}"
            )
            ->assertForbidden();

        $this->assertDatabaseHas(
            'shipping_methods',
            [
                'id' => $shippingMethod->id,
            ]
        );
    }

    public function test_accountant_cannot_manage_shipping_methods(): void
    {
        $accountant = $this->roleUser(
            'Accountant',
            'accountant'
        );

        $shippingMethod = $this->createShippingMethod();

        $this
            ->actingAs($accountant, 'sanctum')
            ->getJson('/api/admin/shipping-methods')
            ->assertForbidden();

        $this
            ->actingAs($accountant, 'sanctum')
            ->getJson(
                "/api/admin/shipping-methods/{$shippingMethod->id}"
            )
            ->assertForbidden();
    }

    public function test_support_cannot_manage_shipping_methods(): void
    {
        $support = $this->roleUser(
            'Support',
            'support'
        );

        $shippingMethod = $this->createShippingMethod();

        $this
            ->actingAs($support, 'sanctum')
            ->getJson('/api/admin/shipping-methods')
            ->assertForbidden();

        $this
            ->actingAs($support, 'sanctum')
            ->getJson(
                "/api/admin/shipping-methods/{$shippingMethod->id}"
            )
            ->assertForbidden();
    }

    public function test_super_admin_can_manage_shipping_methods(): void
    {
        $superAdmin = $this->roleUser(
            'Super Admin',
            'super_admin'
        );

        $role = $superAdmin
            ->roles()
            ->where(
                'slug',
                'super_admin'
            )
            ->firstOrFail();

        $permissions = collect([
            'shipping_methods.view',
            'shipping_methods.create',
            'shipping_methods.update',
            'shipping_methods.delete',
        ])->map(
            fn (string $slug) => Permission::firstOrCreate(
                [
                    'slug' => $slug,
                ],
                [
                    'name' => $slug,
                ]
            )
        );

        $role->permissions()->sync(
            $permissions->pluck('id')
        );

        $shippingMethod = $this->createShippingMethod();

        $this
            ->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/admin/shipping-methods')
            ->assertOk();

        $this
            ->actingAs($superAdmin, 'sanctum')
            ->getJson(
                "/api/admin/shipping-methods/{$shippingMethod->id}"
            )
            ->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createShippingMethod(
        array $overrides = []
    ): ShippingMethod {
        return ShippingMethod::create(
            array_merge(
                [
                    'name' => 'Test Shipping',
                    'description' => 'Test shipping method',
                    'price' => 0,
                    'estimated_days' => '3-5 days',
                    'is_active' => true,
                    'sort_order' => 1,
                ],
                $overrides
            )
        );
    }

    private function roleUser(
        string $name,
        string $slug
    ): User {
        $user = User::factory()->create([
            'email' =>
                strtolower($slug)
                . '-shipping-'
                . uniqid()
                . '@tkb.test',

            'status' => UserStatus::ACTIVE,
        ]);

        $role = Role::firstOrCreate(
            [
                'slug' => $slug,
            ],
            [
                'name' => $name,
            ]
        );

        $user->roles()->sync([
            $role->id,
        ]);

        return $user;
    }

    private function adminUser(
        array $permissions = []
    ): User {
        $user = User::factory()->create([
            'email' =>
                'shipping-admin-'
                . uniqid()
                . '@tkb.test',

            'status' => UserStatus::ACTIVE,
        ]);

        $role = Role::firstOrCreate(
            [
                'slug' => 'admin',
            ],
            [
                'name' => 'Admin',
            ]
        );

        $permissionIds = collect(
            $permissions
        )
            ->map(
                fn (string $slug) =>
                    Permission::firstOrCreate(
                        [
                            'slug' => $slug,
                        ],
                        [
                            'name' => $slug,
                        ]
                    )->id
            )
            ->all();

        $role->permissions()->sync(
            $permissionIds
        );

        $user->roles()->sync([
            $role->id,
        ]);

        return $user;
    }
}
