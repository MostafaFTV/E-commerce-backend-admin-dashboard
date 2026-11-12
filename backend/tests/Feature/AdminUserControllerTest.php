<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_can_view_staff_users(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $staff = $this->createStaffUser([
            'role' => 'admin',
            'email' => 'staff@example.com',
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/admin/users');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $staff->id
            )
            ->assertJsonPath(
                'data.0.email',
                'staff@example.com'
            );
    }

    public function test_staff_index_does_not_include_super_admin_or_customers(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->createStaffUser([
            'role' => 'admin',
            'email' => 'staff@example.com',
        ]);

        $this->createCustomer([
            'email' => 'customer@example.com',
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/admin/users');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.email',
                'staff@example.com'
            );
    }

    public function test_super_admin_can_search_staff_by_name(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $matchingStaff = $this->createStaffUser([
            'role' => 'admin',
            'first_name' => 'Alexander',
            'last_name' => 'Morgan',
            'email' => 'alexander@example.com',
        ]);

        $this->createStaffUser([
            'role' => 'support',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->getJson(
                '/api/admin/users?search=Alexander'
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $matchingStaff->id
            );
    }

    public function test_super_admin_can_search_staff_by_email(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $matchingStaff = $this->createStaffUser([
            'role' => 'admin',
            'email' => 'alexander@example.com',
        ]);

        $this->createStaffUser([
            'role' => 'support',
            'email' => 'john@example.com',
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->getJson(
                '/api/admin/users?search=alexander@example.com'
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $matchingStaff->id
            );
    }

    public function test_super_admin_can_search_staff_by_phone(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $matchingStaff = $this->createStaffUser([
            'role' => 'admin',
            'phone' => '+33111111111',
        ]);

        $this->createStaffUser([
            'role' => 'support',
            'phone' => '+33222222222',
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->getJson(
                '/api/admin/users?search=+33111111111'
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $matchingStaff->id
            );
    }

    public function test_super_admin_can_filter_staff_by_status(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $activeStaff = $this->createStaffUser([
            'role' => 'admin',
            'email' => 'active@example.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $this->createStaffUser([
            'role' => 'support',
            'email' => 'inactive@example.com',
            'status' => UserStatus::INACTIVE,
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->getJson(
                '/api/admin/users?status=active'
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $activeStaff->id
            )
            ->assertJsonPath(
                'data.0.status',
                'active'
            );
    }

    public function test_super_admin_can_filter_staff_by_role(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $admin = $this->createStaffUser([
            'role' => 'admin',
            'email' => 'admin@example.com',
        ]);

        $this->createStaffUser([
            'role' => 'support',
            'email' => 'support@example.com',
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->getJson(
                '/api/admin/users?role=admin'
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $admin->id
            )
            ->assertJsonPath(
                'data.0.email',
                'admin@example.com'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_can_view_staff_details(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $staff = $this->createStaffUser([
            'role' => 'admin',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->getJson(
                "/api/admin/users/{$staff->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $staff->id
            )
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
                'data.roles.0',
                'admin'
            );
    }

    public function test_super_admin_cannot_view_customer_as_staff(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $customer = $this->createCustomer();

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->getJson(
                "/api/admin/users/{$customer->id}"
            );

        $response->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_can_create_admin_staff(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->createSystemRole('admin');

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->postJson(
                '/api/admin/users',
                [
                    'first_name' => 'Admin',
                    'last_name' => 'User',
                    'email' => 'admin@example.com',
                    'phone' => '+33111111111',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'status' => 'active',
                    'role' => 'admin',
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Staff user created successfully.'
            )
            ->assertJsonPath(
                'data.email',
                'admin@example.com'
            )
            ->assertJsonPath(
                'data.roles.0',
                'admin'
            );
    }

    public function test_super_admin_can_create_accountant_staff(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->createSystemRole('accountant');

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->postJson(
                '/api/admin/users',
                [
                    'first_name' => 'Accountant',
                    'last_name' => 'User',
                    'email' => 'accountant@example.com',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'status' => 'active',
                    'role' => 'accountant',
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.roles.0',
                'accountant'
            );
    }

    public function test_super_admin_can_create_support_staff(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->createSystemRole('support');

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->postJson(
                '/api/admin/users',
                [
                    'first_name' => 'Support',
                    'last_name' => 'User',
                    'email' => 'support@example.com',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'status' => 'active',
                    'role' => 'support',
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.roles.0',
                'support'
            );
    }

    public function test_super_admin_cannot_create_super_admin_through_staff_api(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->postJson(
                '/api/admin/users',
                [
                    'first_name' => 'Root',
                    'last_name' => 'User',
                    'email' => 'root@example.com',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'status' => 'active',
                    'role' => 'super_admin',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'role',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_can_update_staff(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->createSystemRole('admin');
        $this->createSystemRole('support');

        $staff = $this->createStaffUser([
            'role' => 'admin',
            'email' => 'staff@example.com',
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/users/{$staff->id}",
                [
                    'first_name' => 'Updated',
                    'role' => 'support',
                    'status' => 'active',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.first_name',
                'Updated'
            )
            ->assertJsonPath(
                'data.roles.0',
                'support'
            );
    }

    public function test_super_admin_can_change_staff_password(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $staff = $this->createStaffUser([
            'role' => 'admin',
            'email' => 'password@example.com',
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/users/{$staff->id}",
                [
                    'password' => 'newpassword123',
                    'password_confirmation' => 'newpassword123',
                ]
            );

        $response->assertOk();
    }

    public function test_super_admin_cannot_set_staff_password_to_null(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $staff = $this->createStaffUser([
            'role' => 'admin',
            'email' => 'null-password@example.com',
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/users/{$staff->id}",
                [
                    'password' => null,
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password',
            ]);
    }

    public function test_super_admin_can_partially_update_staff(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $staff = $this->createStaffUser([
            'role' => 'admin',
            'first_name' => 'John',
            'email' => 'partial@example.com',
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/users/{$staff->id}",
                [
                    'first_name' => 'Jane',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.first_name',
                'Jane'
            );
    }

    public function test_super_admin_cannot_update_customer_as_staff(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $customer = $this->createCustomer();

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/users/{$customer->id}",
                [
                    'first_name' => 'Hacker',
                    'role' => 'admin',
                ]
            );

        $response->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Token Revocation
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_revokes_all_staff_tokens_when_staff_becomes_inactive(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $staff = $this->createStaffUser([
            'role' => 'admin',
            'email' => 'inactive-token@example.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $staff->createToken('auth-token-1');
        $staff->createToken('auth-token-2');
        $staff->createToken('auth-token-3');

        $this->assertDatabaseCount(
            'personal_access_tokens',
            3
        );

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/users/{$staff->id}",
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

        $this->assertDatabaseMissing(
            'personal_access_tokens',
            [
                'tokenable_id' => $staff->id,
            ]
        );
    }

    public function test_super_admin_revokes_all_staff_tokens_when_staff_becomes_suspended(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $staff = $this->createStaffUser([
            'role' => 'admin',
            'email' => 'suspended-token@example.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $staff->createToken('auth-token-1');
        $staff->createToken('auth-token-2');

        $this->assertDatabaseCount(
            'personal_access_tokens',
            2
        );

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/users/{$staff->id}",
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

        $this->assertDatabaseMissing(
            'personal_access_tokens',
            [
                'tokenable_id' => $staff->id,
            ]
        );
    }

    public function test_super_admin_does_not_revoke_staff_tokens_when_status_remains_active(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $staff = $this->createStaffUser([
            'role' => 'admin',
            'email' => 'active-token@example.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $staff->createToken('auth-token-1');

        $this->assertDatabaseHas(
            'personal_access_tokens',
            [
                'tokenable_id' => $staff->id,
            ]
        );

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/users/{$staff->id}",
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

        $this->assertDatabaseHas(
            'personal_access_tokens',
            [
                'tokenable_id' => $staff->id,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    public function test_duplicate_staff_email_is_rejected(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->createStaffUser([
            'role' => 'admin',
            'email' => 'existing@example.com',
        ]);

        $staff = $this->createStaffUser([
            'role' => 'support',
            'email' => 'staff@example.com',
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/users/{$staff->id}",
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

    public function test_duplicate_staff_phone_is_rejected(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->createStaffUser([
            'role' => 'admin',
            'phone' => '+33111111111',
        ]);

        $staff = $this->createStaffUser([
            'role' => 'support',
            'email' => 'staff@example.com',
            'phone' => '+33222222222',
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/users/{$staff->id}",
                [
                    'phone' => '+33111111111',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'phone',
            ]);
    }

    public function test_invalid_staff_role_is_rejected(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $staff = $this->createStaffUser([
            'role' => 'admin',
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/users/{$staff->id}",
                [
                    'role' => 'super_admin',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'role',
            ]);
    }

    public function test_invalid_staff_status_is_rejected(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $staff = $this->createStaffUser([
            'role' => 'admin',
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/users/{$staff->id}",
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
    | Self Protection
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_cannot_deactivate_themselves(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/users/{$superAdmin->id}",
                [
                    'status' => 'inactive',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);
    }

    public function test_super_admin_cannot_suspend_themselves(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/users/{$superAdmin->id}",
                [
                    'status' => 'suspended',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);
    }

    public function test_super_admin_cannot_delete_themselves(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->deleteJson(
                "/api/admin/users/{$superAdmin->id}"
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'user',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_can_delete_staff(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $staff = $this->createStaffUser([
            'role' => 'admin',
        ]);

        $staff->createToken('auth-token');

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->deleteJson(
                "/api/admin/users/{$staff->id}"
            );

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Staff user deleted successfully.',
            ]);

        $this->assertDatabaseMissing(
            'users',
            [
                'id' => $staff->id,
            ]
        );

        $this->assertDatabaseMissing(
            'personal_access_tokens',
            [
                'tokenable_id' => $staff->id,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_admin_cannot_access_staff_management(): void
    {
        $admin = $this->createStaffUser([
            'role' => 'admin',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users');

        $response->assertForbidden();
    }

    public function test_accountant_cannot_access_staff_management(): void
    {
        $accountant = $this->createStaffUser([
            'role' => 'accountant',
        ]);

        $response = $this
            ->actingAs($accountant, 'sanctum')
            ->getJson('/api/admin/users');

        $response->assertForbidden();
    }

    public function test_support_cannot_access_staff_management(): void
    {
        $support = $this->createStaffUser([
            'role' => 'support',
        ]);

        $response = $this
            ->actingAs($support, 'sanctum')
            ->getJson('/api/admin/users');

        $response->assertForbidden();
    }

    public function test_customer_cannot_access_staff_management(): void
    {
        $customer = $this->createCustomer();

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->getJson('/api/admin/users');

        $response->assertForbidden();
    }

    public function test_guest_cannot_access_staff_management(): void
    {
        $response = $this->getJson(
            '/api/admin/users'
        );

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Activity Logs
    |--------------------------------------------------------------------------
    */

    public function test_creating_staff_user_creates_activity_log(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->createSystemRole('admin');

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->postJson(
                '/api/admin/users',
                [
                    'first_name' => 'Admin',
                    'last_name' => 'User',
                    'email' => 'activity-create@example.com',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'status' => 'active',
                    'role' => 'admin',
                ]
            );

        $response->assertCreated();

        $this->assertDatabaseHas(
            'activity_logs',
            [
                'action' => 'users.created',
            ]
        );
    }

    public function test_updating_staff_user_creates_activity_log(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $staff = $this->createStaffUser([
            'role' => 'admin',
        ]);

        $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/users/{$staff->id}",
                [
                    'first_name' => 'Updated',
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'activity_logs',
            [
                'action' => 'users.updated',
            ]
        );
    }

    public function test_changing_staff_password_creates_safe_activity_log(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $staff = $this->createStaffUser([
            'role' => 'admin',
        ]);

        $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/users/{$staff->id}",
                [
                    'password' => 'newpassword123',
                    'password_confirmation' => 'newpassword123',
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'activity_logs',
            [
                'action' => 'users.updated',
            ]
        );

        $this->assertDatabaseMissing(
            'activity_logs',
            [
                'description' => 'newpassword123',
            ]
        );
    }

    public function test_deleting_staff_user_creates_activity_log(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $staff = $this->createStaffUser([
            'role' => 'admin',
        ]);

        $this
            ->actingAs($superAdmin, 'sanctum')
            ->deleteJson(
                "/api/admin/users/{$staff->id}"
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'activity_logs',
            [
                'action' => 'users.deleted',
            ]
        );
    }

    public function test_failed_self_protection_does_not_create_activity_log(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/users/{$superAdmin->id}",
                [
                    'status' => 'inactive',
                ]
            )
            ->assertUnprocessable();

        $this->assertDatabaseMissing(
            'activity_logs',
            [
                'action' => 'users.updated',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createSystemRole(
        string $slug
    ): Role {
        return Role::firstOrCreate(
            [
                'slug' => $slug,
            ],
            [
                'name' => match ($slug) {
                    'admin' => 'Admin',
                    'accountant' => 'Accountant',
                    'support' => 'Support',
                    'super_admin' => 'Super Admin',
                    'customer' => 'Customer',
                    default => ucfirst($slug),
                },
                'description' => 'System role',
            ]
        );
    }

    private function createSuperAdmin(): User
    {
        $role = $this->createSystemRole(
            'super_admin'
        );

        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $user->roles()->sync([
            $role->id,
        ]);

        return $user->fresh('roles');
    }

    private function createStaffUser(
        array $attributes = []
    ): User {
        $roleSlug = $attributes['role']
            ?? 'admin';

        unset(
            $attributes['role']
        );

        $role = $this->createSystemRole(
            $roleSlug
        );

        $user = User::factory()->create(
            array_merge(
                [
                    'status' => UserStatus::ACTIVE,
                ],
                $attributes
            )
        );

        $user->roles()->sync([
            $role->id,
        ]);

        return $user->fresh('roles');
    }

    private function createCustomer(
        array $attributes = []
    ): User {
        $role = $this->createSystemRole(
            'customer'
        );

        $user = User::factory()->create(
            array_merge(
                [
                    'status' => UserStatus::ACTIVE,
                ],
                $attributes
            )
        );

        $user->roles()->sync([
            $role->id,
        ]);

        return $user->fresh('roles');
    }
}
