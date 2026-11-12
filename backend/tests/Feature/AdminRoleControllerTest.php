<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoleControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_can_view_roles(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $role = $this->createRole(
            'catalog_manager',
            'Catalog Manager'
        );

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/admin/roles');

        $response
            ->assertOk()
            ->assertJsonCount(6, 'data');

        $response->assertJsonFragment([
            'id' => $role->id,
            'name' => 'Catalog Manager',
            'slug' => 'catalog_manager',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_can_view_role_details(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $permissionOne = $this->createPermission(
            'products.view'
        );

        $permissionTwo = $this->createPermission(
            'products.update'
        );

        $role = $this->createRole(
            'catalog_manager',
            'Catalog Manager'
        );

        $role->permissions()->sync([
            $permissionOne->id,
            $permissionTwo->id,
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->getJson(
                "/api/admin/roles/{$role->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $role->id
            )
            ->assertJsonPath(
                'data.name',
                'Catalog Manager'
            )
            ->assertJsonPath(
                'data.slug',
                'catalog_manager'
            )
            ->assertJsonPath(
                'data.users_count',
                0
            )
            ->assertJsonCount(
                2,
                'data.permissions'
            );

        $response->assertJsonFragment([
            'slug' => 'products.view',
        ]);

        $response->assertJsonFragment([
            'slug' => 'products.update',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_can_create_role(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $permissionOne = $this->createPermission(
            'products.view'
        );

        $permissionTwo = $this->createPermission(
            'products.create'
        );

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->postJson(
                '/api/admin/roles',
                [
                    'name' => 'Catalog Manager',
                    'slug' => 'catalog_manager',
                    'description' => 'Manages catalog operations.',
                    'permission_ids' => [
                        $permissionOne->id,
                        $permissionTwo->id,
                    ],
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Role created successfully.'
            )
            ->assertJsonPath(
                'data.name',
                'Catalog Manager'
            )
            ->assertJsonPath(
                'data.slug',
                'catalog_manager'
            )
            ->assertJsonPath(
                'data.users_count',
                0
            )
            ->assertJsonCount(
                2,
                'data.permissions'
            );

        $role = Role::where(
            'slug',
            'catalog_manager'
        )->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [
                $permissionOne->id,
                $permissionTwo->id,
            ],
            $role->permissions()
                ->pluck('id')
                ->all()
        );
    }

    public function test_super_admin_can_create_role_without_permissions(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->postJson(
                '/api/admin/roles',
                [
                    'name' => 'Empty Role',
                    'slug' => 'empty_role',
                    'description' => null,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.name',
                'Empty Role'
            )
            ->assertJsonPath(
                'data.slug',
                'empty_role'
            )
            ->assertJsonCount(
                0,
                'data.permissions'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_can_update_role_and_sync_permissions(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $oldPermission = $this->createPermission(
            'products.view'
        );

        $newPermissionOne = $this->createPermission(
            'products.update'
        );

        $newPermissionTwo = $this->createPermission(
            'products.delete'
        );

        $role = $this->createRole(
            'catalog_manager',
            'Catalog Manager'
        );

        $role->permissions()->sync([
            $oldPermission->id,
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/roles/{$role->id}",
                [
                    'name' => 'Senior Catalog Manager',
                    'description' => 'Updated catalog permissions.',
                    'permission_ids' => [
                        $newPermissionOne->id,
                        $newPermissionTwo->id,
                    ],
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Role updated successfully.'
            )
            ->assertJsonPath(
                'data.name',
                'Senior Catalog Manager'
            )
            ->assertJsonPath(
                'data.slug',
                'catalog_manager'
            )
            ->assertJsonCount(
                2,
                'data.permissions'
            );

        $this->assertEqualsCanonicalizing(
            [
                $newPermissionOne->id,
                $newPermissionTwo->id,
            ],
            $role->fresh()
                ->permissions()
                ->pluck('id')
                ->all()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    public function test_duplicate_role_slug_is_rejected(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->createRole(
            'catalog_manager',
            'Catalog Manager'
        );

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->postJson(
                '/api/admin/roles',
                [
                    'name' => 'Another Role',
                    'slug' => 'catalog_manager',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);
    }

    public function test_invalid_role_slug_format_is_rejected(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->postJson(
                '/api/admin/roles',
                [
                    'name' => 'Invalid Role',
                    'slug' => 'Catalog Manager',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);
    }

    public function test_invalid_permission_id_is_rejected(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->postJson(
                '/api/admin/roles',
                [
                    'name' => 'Catalog Manager',
                    'slug' => 'catalog_manager',
                    'permission_ids' => [
                        999999,
                    ],
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permission_ids.0',
            ]);
    }

    public function test_duplicate_permission_ids_are_rejected(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $permission = $this->createPermission(
            'products.view'
        );

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->postJson(
                '/api/admin/roles',
                [
                    'name' => 'Catalog Manager',
                    'slug' => 'catalog_manager',
                    'permission_ids' => [
                        $permission->id,
                        $permission->id,
                    ],
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permission_ids.1',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | System Role Protection
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_role_cannot_be_modified(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $role = Role::where(
            'slug',
            'super_admin'
        )->firstOrFail();

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/roles/{$role->id}",
                [
                    'name' => 'Dangerous Rename',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'role',
            ]);

        $this->assertDatabaseHas(
            'roles',
            [
                'id' => $role->id,
                'slug' => 'super_admin',
                'name' => $role->name,
            ]
        );
    }

    public function test_system_role_slug_cannot_be_changed(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $role = Role::where(
            'slug',
            'admin'
        )->firstOrFail();

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/roles/{$role->id}",
                [
                    'slug' => 'main_admin',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);

        $this->assertDatabaseHas(
            'roles',
            [
                'id' => $role->id,
                'slug' => 'admin',
            ]
        );
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $role = Role::where(
            'slug',
            'admin'
        )->firstOrFail();

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->deleteJson(
                "/api/admin/roles/{$role->id}"
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'role',
            ]);

        $this->assertDatabaseHas(
            'roles',
            [
                'id' => $role->id,
                'slug' => 'admin',
            ]
        );
    }

    public function test_admin_system_role_permissions_can_be_changed(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $existingPermission = $this->createPermission(
            'products.view'
        );

        $newPermission = $this->createPermission(
            'products.delete'
        );

        $role = Role::where(
            'slug',
            'admin'
        )->firstOrFail();

        $role->permissions()->sync([
            $existingPermission->id,
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/roles/{$role->id}",
                [
                    'permission_ids' => [
                        $newPermission->id,
                    ],
                ]
            );

        $response->assertOk();

        $this->assertEqualsCanonicalizing(
            [
                $newPermission->id,
            ],
            $role->fresh()
                ->permissions()
                ->pluck('id')
                ->all()
        );
    }

    public function test_accountant_system_role_permissions_can_be_changed(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $existingPermission = $this->createPermission(
            'orders.view'
        );

        $newPermission = $this->createPermission(
            'orders.manage'
        );

        $role = Role::where(
            'slug',
            'accountant'
        )->firstOrFail();

        $role->permissions()->sync([
            $existingPermission->id,
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/roles/{$role->id}",
                [
                    'permission_ids' => [
                        $newPermission->id,
                    ],
                ]
            );

        $response->assertOk();

        $this->assertEqualsCanonicalizing(
            [
                $newPermission->id,
            ],
            $role->fresh()
                ->permissions()
                ->pluck('id')
                ->all()
        );
    }

    public function test_customer_system_role_permissions_cannot_be_changed(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $existingPermission = $this->createPermission(
            'customers.view'
        );

        $newPermission = $this->createPermission(
            'customers.manage'
        );

        $role = Role::where(
            'slug',
            'customer'
        )->firstOrFail();

        $role->permissions()->sync([
            $existingPermission->id,
        ]);

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/roles/{$role->id}",
                [
                    'permission_ids' => [
                        $newPermission->id,
                    ],
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permission_ids',
            ]);

        $this->assertEqualsCanonicalizing(
            [
                $existingPermission->id,
            ],
            $role->fresh()
                ->permissions()
                ->pluck('id')
                ->all()
        );
    }

    public function test_role_with_assigned_users_cannot_be_deleted(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $role = $this->createRole(
            'catalog_manager',
            'Catalog Manager'
        );

        $user = User::factory()->create();

        $user->roles()->attach(
            $role->id
        );

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->deleteJson(
                "/api/admin/roles/{$role->id}"
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'role',
            ]);

        $this->assertDatabaseHas(
            'roles',
            [
                'id' => $role->id,
                'slug' => 'catalog_manager',
            ]
        );
    }

    public function test_super_admin_can_delete_unused_custom_role(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $role = $this->createRole(
            'catalog_manager',
            'Catalog Manager'
        );

        $permission = $this->createPermission(
            'products.view'
        );

        $role->permissions()->attach(
            $permission->id
        );

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->deleteJson(
                "/api/admin/roles/{$role->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Role deleted successfully.'
            );

        $this->assertDatabaseMissing(
            'roles',
            [
                'id' => $role->id,
            ]
        );

        $this->assertDatabaseMissing(
            'permission_role',
            [
                'role_id' => $role->id,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_admin_cannot_access_role_management(): void
    {
        $admin = $this->createStaff(
            'admin'
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/roles');

        $response->assertForbidden();
    }

    public function test_accountant_cannot_access_role_management(): void
    {
        $accountant = $this->createStaff(
            'accountant'
        );

        $response = $this
            ->actingAs($accountant, 'sanctum')
            ->getJson('/api/admin/roles');

        $response->assertForbidden();
    }

    public function test_support_cannot_access_role_management(): void
    {
        $support = $this->createStaff(
            'support'
        );

        $response = $this
            ->actingAs($support, 'sanctum')
            ->getJson('/api/admin/roles');

        $response->assertForbidden();
    }

    public function test_customer_cannot_access_role_management(): void
    {
        $customer = $this->createCustomer();

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->getJson('/api/admin/roles');

        $response->assertForbidden();
    }

    public function test_guest_cannot_access_role_management(): void
    {
        $response = $this->getJson(
            '/api/admin/roles'
        );

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function ensureSystemRoles(): void
    {
        $roles = [
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'accountant' => 'Accountant',
            'support' => 'Support',
            'customer' => 'Customer',
        ];

        foreach ($roles as $slug => $name) {
            Role::firstOrCreate(
                [
                    'slug' => $slug,
                ],
                [
                    'name' => $name,
                    'description' => "Test {$name} role",
                ]
            );
        }
    }

    private function createSuperAdmin(): User
    {
        $this->ensureSystemRoles();

        $user = User::factory()->create([
            'email' => 'role-super-admin-' .
                uniqid() .
                '@tkb.test',
            'status' => UserStatus::ACTIVE,
        ]);

        $role = Role::where(
            'slug',
            'super_admin'
        )->firstOrFail();

        $user->roles()->sync([
            $role->id,
        ]);

        return $user;
    }

    private function createStaff(
        string $roleSlug
    ): User {
        $this->ensureSystemRoles();

        $user = User::factory()->create([
            'email' => $roleSlug . '-' .
                uniqid() .
                '@tkb.test',
            'status' => UserStatus::ACTIVE,
        ]);

        $role = Role::where(
            'slug',
            $roleSlug
        )->firstOrFail();

        $user->roles()->sync([
            $role->id,
        ]);

        return $user;
    }

    private function createCustomer(): User
    {
        $this->ensureSystemRoles();

        $user = User::factory()->create([
            'email' => 'role-customer-' .
                uniqid() .
                '@tkb.test',
            'status' => UserStatus::ACTIVE,
        ]);

        $role = Role::where(
            'slug',
            'customer'
        )->firstOrFail();

        $user->roles()->sync([
            $role->id,
        ]);

        return $user;
    }

    private function createRole(
        string $slug,
        string $name
    ): Role {
        return Role::firstOrCreate(
            [
                'slug' => $slug,
            ],
            [
                'name' => $name,
                'description' => "Test {$name} role",
            ]
        );
    }

    private function createPermission(
        string $slug
    ): Permission {
        return Permission::create([
            'name' => str($slug)
                ->replace('.', ' ')
                ->title(),
            'slug' => $slug,
            'description' => "Test {$slug} permission",
        ]);
    }
}