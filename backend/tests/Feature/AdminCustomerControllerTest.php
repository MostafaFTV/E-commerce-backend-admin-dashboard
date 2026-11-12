<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function test_admin_with_view_permission_can_view_customers(): void
    {
        $admin = $this->createAdmin([
            'customers.view',
        ]);

        $customer = $this->createCustomer([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+33123456789',
        ]);

        $this->createAddress($customer);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/customers');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $customer->id
            )
            ->assertJsonPath(
                'data.0.first_name',
                'John'
            )
            ->assertJsonPath(
                'data.0.last_name',
                'Doe'
            )
            ->assertJsonPath(
                'data.0.email',
                'john@example.com'
            )
            ->assertJsonPath(
                'data.0.addresses_count',
                1
            )
            ->assertJsonPath(
                'data.0.orders_count',
                0
            );
    }

    public function test_customer_index_does_not_include_non_customers(): void
    {
        $admin = $this->createAdmin([
            'customers.view',
        ]);

        $this->createCustomer([
            'email' => 'customer@example.com',
        ]);

        $this->createStaffUser([
            'email' => 'admin@example.com',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/customers');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.email',
                'customer@example.com'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_search_customers_by_name(): void
    {
        $admin = $this->createAdmin([
            'customers.view',
        ]);

        $matchingCustomer = $this->createCustomer([
            'first_name' => 'Alexander',
            'last_name' => 'Morgan',
            'email' => 'alexander@example.com',
        ]);

        $this->createCustomer([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/customers?search=Alexander'
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $matchingCustomer->id
            );
    }

    public function test_admin_can_search_customers_by_email(): void
    {
        $admin = $this->createAdmin([
            'customers.view',
        ]);

        $matchingCustomer = $this->createCustomer([
            'email' => 'alexander@example.com',
        ]);

        $this->createCustomer([
            'email' => 'john@example.com',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/customers?search=alexander@example.com'
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $matchingCustomer->id
            );
    }

    public function test_admin_can_search_customers_by_phone(): void
    {
        $admin = $this->createAdmin([
            'customers.view',
        ]);

        $matchingCustomer = $this->createCustomer([
            'phone' => '+33111111111',
        ]);

        $this->createCustomer([
            'phone' => '+33222222222',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/customers?search=+33111111111'
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $matchingCustomer->id
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Status Filter
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_filter_customers_by_status(): void
    {
        $admin = $this->createAdmin([
            'customers.view',
        ]);

        $activeCustomer = $this->createCustomer([
            'email' => 'active@example.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $this->createCustomer([
            'email' => 'inactive@example.com',
            'status' => UserStatus::INACTIVE,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/customers?status=active'
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $activeCustomer->id
            )
            ->assertJsonPath(
                'data.0.status',
                'active'
            )
            ->assertJsonPath(
                'meta.available_statuses',
                [
                    'active',
                    'inactive',
                    'suspended',
                ]
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_view_customer_details(): void
    {
        $admin = $this->createAdmin([
            'customers.view',
        ]);

        $customer = $this->createCustomer([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        $address = $this->createAddress(
            $customer,
            [
                'title' => 'Home',
                'is_default' => true,
            ]
        );

        $order = $this->createOrder(
            $customer,
            [
                'status' => OrderStatus::PAID,
            ]
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/admin/customers/{$customer->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $customer->id
            )
            ->assertJsonPath(
                'data.first_name',
                'John'
            )
            ->assertJsonPath(
                'data.email',
                'john@example.com'
            )
            ->assertJsonCount(
                1,
                'data.addresses'
            )
            ->assertJsonPath(
                'data.addresses.0.id',
                $address->id
            )
            ->assertJsonPath(
                'data.addresses.0.is_default',
                true
            )
            ->assertJsonCount(
                1,
                'data.orders'
            )
            ->assertJsonPath(
                'data.orders.0.id',
                $order->id
            )
            ->assertJsonPath(
                'data.orders.0.status',
                'paid'
            );
    }

    public function test_admin_customer_details_include_profile_preferences(): void
    {
        $admin = $this->createAdmin([
            'customers.view',
        ]);

        $customer = $this->createCustomer([
            'birth_date' => '1995-04-20',
            'marketing_sms' => true,
        ]);

        $category = \App\Models\Category::create([
            'name' => [
                'en' => 'Tableware',
                'fa' => 'لوازم سرو و پذیرایی',
            ],
            'slug' => 'tableware-admin-profile-test',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $customer->preferredCategories()->attach($category->id);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/customers/{$customer->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.birth_date', '1995-04-20')
            ->assertJsonPath('data.marketing_sms', true)
            ->assertJsonPath(
                'data.preferred_category_ids',
                [$category->id]
            )
            ->assertJsonPath(
                'data.preferred_categories.0.id',
                $category->id
            )
            ->assertJsonPath(
                'data.preferred_categories.0.slug',
                'tableware-admin-profile-test'
            )
            ->assertJsonPath(
                'data.preferred_categories.0.name_en',
                'Tableware'
            )
            ->assertJsonPath(
                'data.preferred_categories.0.name_fa',
                'لوازم سرو و پذیرایی'
            );
    }
    public function test_admin_cannot_view_non_customer_as_customer(): void
    {
        $admin = $this->createAdmin([
            'customers.view',
        ]);

        $staff = $this->createStaffUser();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/admin/customers/{$staff->id}"
            );

        $response->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_admin_with_manage_permission_can_update_customer(): void
    {
        $admin = $this->createAdmin([
            'customers.manage',
        ]);

        $customer = $this->createCustomer([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+33123456789',
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/customers/{$customer->id}",
                [
                    'first_name' => 'Jane',
                    'last_name' => 'Smith',
                    'email' => 'jane@example.com',
                    'phone' => '+33987654321',
                    'status' => 'suspended',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Customer updated successfully.'
            )
            ->assertJsonPath(
                'data.first_name',
                'Jane'
            )
            ->assertJsonPath(
                'data.last_name',
                'Smith'
            )
            ->assertJsonPath(
                'data.email',
                'jane@example.com'
            )
            ->assertJsonPath(
                'data.phone',
                '+33987654321'
            )
            ->assertJsonPath(
                'data.status',
                'suspended'
            );

        $this->assertDatabaseHas(
            'users',
            [
                'id' => $customer->id,
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane@example.com',
                'phone' => '+33987654321',
                'status' => 'suspended',
            ]
        );
    }

    public function test_admin_can_partially_update_customer(): void
    {
        $admin = $this->createAdmin([
            'customers.manage',
        ]);

        $customer = $this->createCustomer([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+33123456789',
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/customers/{$customer->id}",
                [
                    'status' => 'inactive',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.first_name',
                'John'
            )
            ->assertJsonPath(
                'data.last_name',
                'Doe'
            )
            ->assertJsonPath(
                'data.email',
                'john@example.com'
            )
            ->assertJsonPath(
                'data.status',
                'inactive'
            );
    }

    public function test_admin_revokes_all_customer_tokens_when_customer_becomes_inactive(): void
    {
        $admin = $this->createAdmin([
            'customers.manage',
        ]);

        $customer = $this->createCustomer([
            'email' => 'inactive-token@example.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $customer->createToken(
            'auth-token-1'
        );

        $customer->createToken(
            'auth-token-2'
        );

        $customer->createToken(
            'auth-token-3'
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            3
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/customers/{$customer->id}",
                [
                    'status' => 'inactive',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'inactive'
            );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_admin_revokes_all_customer_tokens_when_customer_becomes_suspended(): void
    {
        $admin = $this->createAdmin([
            'customers.manage',
        ]);

        $customer = $this->createCustomer([
            'email' => 'suspended-token@example.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $customer->createToken(
            'auth-token-1'
        );

        $customer->createToken(
            'auth-token-2'
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            2
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/customers/{$customer->id}",
                [
                    'status' => 'suspended',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'suspended'
            );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_admin_does_not_revoke_tokens_when_customer_status_does_not_become_inactive_or_suspended(): void
    {
        $admin = $this->createAdmin([
            'customers.manage',
        ]);

        $customer = $this->createCustomer([
            'email' => 'active-token@example.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $customer->createToken(
            'auth-token-1'
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/customers/{$customer->id}",
                [
                    'first_name' => 'Updated',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.first_name',
                'Updated'
            );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1
        );
    }

    public function test_admin_can_update_non_status_fields_without_changing_customer_status(): void
    {
        $admin = $this->createAdmin([
            'customers.manage',
        ]);

        $customer = $this->createCustomer([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/customers/{$customer->id}",
                [
                    'first_name' => 'Jane',
                    'last_name' => 'Smith',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.first_name',
                'Jane'
            )
            ->assertJsonPath(
                'data.last_name',
                'Smith'
            )
            ->assertJsonPath(
                'data.status',
                'active'
            );
    }

    public function test_admin_cannot_update_non_customer(): void
    {
        $admin = $this->createAdmin([
            'customers.manage',
        ]);

        $staff = $this->createStaffUser([
            'email' => 'staff@example.com',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/customers/{$staff->id}",
                [
                    'status' => 'inactive',
                ]
            );

        $response->assertNotFound();

        $this->assertDatabaseHas(
            'users',
            [
                'id' => $staff->id,
                'status' => 'active',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    public function test_duplicate_customer_email_is_rejected(): void
    {
        $admin = $this->createAdmin([
            'customers.manage',
        ]);

        $customer = $this->createCustomer([
            'email' => 'customer@example.com',
        ]);

        $this->createCustomer([
            'email' => 'existing@example.com',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/customers/{$customer->id}",
                [
                    'email' => 'existing@example.com',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);
    }

    public function test_duplicate_customer_phone_is_rejected(): void
    {
        $admin = $this->createAdmin([
            'customers.manage',
        ]);

        $customer = $this->createCustomer([
            'phone' => '+33111111111',
        ]);

        $this->createCustomer([
            'phone' => '+33222222222',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/customers/{$customer->id}",
                [
                    'phone' => '+33222222222',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'phone',
            ]);
    }

    public function test_invalid_customer_status_is_rejected(): void
    {
        $admin = $this->createAdmin([
            'customers.manage',
        ]);

        $customer = $this->createCustomer();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/customers/{$customer->id}",
                [
                    'status' => 'deleted',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Permissions / Authentication
    |--------------------------------------------------------------------------
    */

    public function test_user_without_view_permission_cannot_view_customers(): void
    {
        $admin = $this->createAdmin([]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/customers');

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Forbidden: Missing permission'
            );
    }

    public function test_user_without_manage_permission_cannot_update_customer(): void
    {
        $admin = $this->createAdmin([
            'customers.view',
        ]);

        $customer = $this->createCustomer();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/customers/{$customer->id}",
                [
                    'status' => 'suspended',
                ]
            );

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Forbidden: Missing permission'
            );

        $this->assertDatabaseHas(
            'users',
            [
                'id' => $customer->id,
                'status' => 'active',
            ]
        );
    }

    public function test_guest_cannot_access_customer_management(): void
    {
        $response = $this->getJson(
            '/api/admin/customers'
        );

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createAdmin(
        array $permissionSlugs
    ): User {
        $admin = User::factory()->create([
            'email' => 'customer-admin-' .
                uniqid() .
                '@tkb.test',
            'status' => UserStatus::ACTIVE,
        ]);

        $role = Role::create([
            'name' => 'Customer Admin',
            'slug' => 'customer-admin-' .
                uniqid(),
            'description' => 'Test customer admin role',
        ]);

        $permissions = collect(
            $permissionSlugs
        )->map(
            function (string $slug) {
                return Permission::create([
                    'name' => str($slug)->replace(
                        '.',
                        ' '
                    )->title(),
                    'slug' => $slug,
                ]);
            }
        );

        $role->permissions()->sync(
            $permissions->pluck('id')->toArray()
        );

        $admin->roles()->attach(
            $role->id
        );

        return $admin;
    }

    private function createCustomer(
        array $attributes = []
    ): User {
        $customer = User::factory()->create(
            array_merge(
                [
                    'status' => UserStatus::ACTIVE,
                ],
                $attributes
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Real Customer Role
        |--------------------------------------------------------------------------
        |
        | Admin CustomerController identifies customers by the role slug
        | "customer". Therefore the test must use the same production role.
        |
        |--------------------------------------------------------------------------
        */

        $role = Role::firstOrCreate(
            [
                'slug' => 'customer',
            ],
            [
                'name' => 'Customer',
                'description' => 'Test customer role',
            ]
        );

        $customer->roles()->syncWithoutDetaching([
            $role->id,
        ]);

        return $customer;
    }

    private function createStaffUser(
        array $attributes = []
    ): User {
        $user = User::factory()->create(
            array_merge(
                [
                    'status' => UserStatus::ACTIVE,
                ],
                $attributes
            )
        );

        $role = Role::firstOrCreate(
            [
                'slug' => 'test-admin',
            ],
            [
                'name' => 'Admin',
                'description' => 'Test admin role',
            ]
        );

        $user->roles()->syncWithoutDetaching([
            $role->id,
        ]);

        return $user;
    }

    private function createAddress(
        User $user,
        array $attributes = []
    ): Address {
        return Address::create(
            array_merge(
                [
                    'user_id' => $user->id,
                    'title' => 'Home',
                    'recipient_name' => 'John Doe',
                    'phone' => '+33123456789',
                    'address' => '10 Rue de Paris',
                    'city' => 'Paris',
                    'state' => 'Île-de-France',
                    'postal_code' => '75001',
                    'country' => 'France',
                    'is_default' => false,
                ],
                $attributes
            )
        );
    }

    private function createOrder(
        User $user,
        array $attributes = []
    ): Order {
        return Order::create(
            array_merge(
                [
                    'user_id' => $user->id,
                    'order_number' => 'TKB-TEST-' .
                        uniqid(),
                    'status' => OrderStatus::PENDING_PAYMENT,
                    'subtotal' => 100,
                    'discount' => 0,
                    'shipping_cost' => 10,
                    'tax' => 0,
                    'total' => 110,
                    'currency' => 'IRT',
                    'shipping_address' => [
                        'recipient_name' => 'John Doe',
                        'phone' => '+33123456789',
                        'address' => '10 Rue de Paris',
                        'city' => 'Paris',
                        'postal_code' => '75001',
                        'country' => 'France',
                    ],
                    'billing_address' => [
                        'recipient_name' => 'John Doe',
                        'phone' => '+33123456789',
                        'address' => '10 Rue de Paris',
                        'city' => 'Paris',
                        'postal_code' => '75001',
                        'country' => 'France',
                    ],
                ],
                $attributes
            )
        );
    }
}
