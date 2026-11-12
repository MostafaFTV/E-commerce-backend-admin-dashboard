<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_can_view_permissions(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $permission = $this->createPermission(
            'catalog.view',
            'Catalog View'
        );

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/admin/permissions');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $permission->id
            )
            ->assertJsonPath(
                'data.0.name',
                'Catalog View'
            )
            ->assertJsonPath(
                'data.0.slug',
                'catalog.view'
            )
            ->assertJsonPath(
                'data.0.roles_count',
                0
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_can_view_permission_details(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $permission = $this->createPermission(
            'catalog.view',
            'Catalog View'
        );

        $role = $this->createRole(
            'catalog_manager',
            'Catalog Manager'
        );

        $role->permissions()->attach(
            $permission->id
        );

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->getJson(
                "/api/admin/permissions/{$permission->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $permission->id
            )
            ->assertJsonPath(
                'data.name',
                'Catalog View'
            )
            ->assertJsonPath(
                'data.slug',
                'catalog.view'
            )
            ->assertJsonPath(
                'data.roles_count',
                1
            )
            ->assertJsonPath(
                'data.roles.0.id',
                $role->id
            )
            ->assertJsonPath(
                'data.roles.0.slug',
                'catalog_manager'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_can_create_permission(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->postJson(
                '/api/admin/permissions',
                [
                    'name' => 'Catalog Create',
                    'slug' => 'catalog.create',
                    'description' => 'Allows creating catalog items.',
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Permission created successfully.'
            )
            ->assertJsonPath(
                'data.name',
                'Catalog Create'
            )
            ->assertJsonPath(
                'data.slug',
                'catalog.create'
            )
            ->assertJsonPath(
                'data.roles_count',
                0
            );

        $this->assertDatabaseHas(
            'permissions',
            [
                'name' => 'Catalog Create',
                'slug' => 'catalog.create',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_can_update_permission(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $permission = $this->createPermission(
            'catalog.view',
            'Catalog View'
        );

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/permissions/{$permission->id}",
                [
                    'name' => 'Catalog Read',
                    'slug' => 'catalog.read',
                    'description' => 'Updated permission.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Permission updated successfully.'
            )
            ->assertJsonPath(
                'data.name',
                'Catalog Read'
            )
            ->assertJsonPath(
                'data.slug',
                'catalog.read'
            );

        $this->assertDatabaseHas(
            'permissions',
            [
                'id' => $permission->id,
                'name' => 'Catalog Read',
                'slug' => 'catalog.read',
            ]
        );
    }

    public function test_super_admin_can_partially_update_permission(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $permission = $this->createPermission(
            'catalog.view',
            'Catalog View'
        );

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/permissions/{$permission->id}",
                [
                    'name' => 'Catalog Read',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Catalog Read'
            )
            ->assertJsonPath(
                'data.slug',
                'catalog.view'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | System Permission Security
    |--------------------------------------------------------------------------
    */

    public function test_system_permission_slug_cannot_be_changed(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $permission = $this->createPermission(
            'orders.view',
            'View Orders'
        );

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/permissions/{$permission->id}",
                [
                    'slug' => 'orders.read',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);

        $this->assertDatabaseHas(
            'permissions',
            [
                'id' => $permission->id,
                'slug' => 'orders.view',
            ]
        );
    }

    public function test_system_permission_can_update_name_and_description(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $permission = $this->createPermission(
            'orders.view',
            'View Orders'
        );

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->patchJson(
                "/api/admin/permissions/{$permission->id}",
                [
                    'name' => 'View Customer Orders',
                    'description' => 'Updated system permission description.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Permission updated successfully.'
            )
            ->assertJsonPath(
                'data.name',
                'View Customer Orders'
            )
            ->assertJsonPath(
                'data.slug',
                'orders.view'
            )
            ->assertJsonPath(
                'data.description',
                'Updated system permission description.'
            );

        $this->assertDatabaseHas(
            'permissions',
            [
                'id' => $permission->id,
                'name' => 'View Customer Orders',
                'slug' => 'orders.view',
                'description' => 'Updated system permission description.',
            ]
        );
    }

    public function test_system_permission_cannot_be_deleted(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $permission = $this->createPermission(
            'orders.view',
            'View Orders'
        );

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->deleteJson(
                "/api/admin/permissions/{$permission->id}"
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permission',
            ]);

        $this->assertDatabaseHas(
            'permissions',
            [
                'id' => $permission->id,
                'slug' => 'orders.view',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    public function test_duplicate_permission_slug_is_rejected(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->createPermission(
            'catalog.view',
            'Catalog View'
        );

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->postJson(
                '/api/admin/permissions',
                [
                    'name' => 'Another Permission',
                    'slug' => 'catalog.view',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);
    }

    public function test_invalid_permission_slug_format_is_rejected(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->postJson(
                '/api/admin/permissions',
                [
                    'name' => 'Invalid Permission',
                    'slug' => 'Catalog View',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);
    }

    public function test_permission_slug_with_hyphen_is_rejected(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->postJson(
                '/api/admin/permissions',
                [
                    'name' => 'Invalid Permission',
                    'slug' => 'catalog-view',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_can_delete_unused_permission(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $permission = $this->createPermission(
            'catalog.delete',
            'Catalog Delete'
        );

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->deleteJson(
                "/api/admin/permissions/{$permission->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Permission deleted successfully.'
            );

        $this->assertDatabaseMissing(
            'permissions',
            [
                'id' => $permission->id,
            ]
        );
    }

    public function test_permission_assigned_to_role_cannot_be_deleted(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $permission = $this->createPermission(
            'catalog.delete',
            'Catalog Delete'
        );

        $role = $this->createRole(
            'catalog_manager',
            'Catalog Manager'
        );

        $role->permissions()->attach(
            $permission->id
        );

        $response = $this
            ->actingAs($superAdmin, 'sanctum')
            ->deleteJson(
                "/api/admin/permissions/{$permission->id}"
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permission',
            ]);

        $this->assertDatabaseHas(
            'permissions',
            [
                'id' => $permission->id,
            ]
        );

        $this->assertDatabaseHas(
            'permission_role',
            [
                'permission_id' => $permission->id,
                'role_id' => $role->id,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_admin_cannot_access_permission_management(): void
    {
        $admin = $this->createStaff(
            'admin'
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/permissions');

        $response->assertForbidden();
    }

    public function test_accountant_cannot_access_permission_management(): void
    {
        $accountant = $this->createStaff(
            'accountant'
        );

        $response = $this
            ->actingAs($accountant, 'sanctum')
            ->getJson('/api/admin/permissions');

        $response->assertForbidden();
    }

    public function test_support_cannot_access_permission_management(): void
    {
        $support = $this->createStaff(
            'support'
        );

        $response = $this
            ->actingAs($support, 'sanctum')
            ->getJson('/api/admin/permissions');

        $response->assertForbidden();
    }

    public function test_customer_cannot_access_permission_management(): void
    {
        $customer = $this->createCustomer();

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->getJson('/api/admin/permissions');

        $response->assertForbidden();
    }

    public function test_guest_cannot_access_permission_management(): void
    {
        $response = $this->getJson(
            '/api/admin/permissions'
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
            'email' => 'permission-super-admin-' .
                uniqid() .
                '@tkb.test',
            'status' => 'active',
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
            'status' => 'active',
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
            'email' => 'permission-customer-' .
                uniqid() .
                '@tkb.test',
            'status' => 'active',
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
        string $slug,
        ?string $name = null
    ): Permission {
        return Permission::create([
            'name' => $name ??
                str($slug)
                    ->replace('.', ' ')
                    ->title(),
            'slug' => $slug,
            'description' => "Test {$slug} permission",
        ]);
    }
}
