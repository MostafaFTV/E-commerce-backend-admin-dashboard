<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminAuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createRole(
        string $slug,
        array $permissionSlugs = []
    ): Role {
        $role = Role::firstOrCreate(
            [
                'slug' => $slug,
            ],
            [
                'name' => ucwords(
                    str_replace('_', ' ', $slug)
                ),
                'description' => 'Test staff role',
            ]
        );

        $permissions = collect(
            $permissionSlugs
        )->map(
            function (string $permissionSlug) {
                return Permission::firstOrCreate(
                    [
                        'slug' => $permissionSlug,
                    ],
                    [
                        'name' => $permissionSlug,
                        'description' =>
                            'Test permission ' .
                            $permissionSlug,
                    ]
                );
            }
        );

        if ($permissions->isNotEmpty()) {
            $role->permissions()->sync(
                $permissions
                    ->pluck('id')
                    ->all()
            );
        }

        return $role;
    }

    private function createStaff(
        string $roleSlug,
        array $attributes = [],
        array $permissionSlugs = []
    ): User {
        $role = $this->createRole(
            $roleSlug,
            $permissionSlugs
        );

        $user = User::factory()->create(
            array_merge(
                [
                    'email' =>
                        $roleSlug .
                        '@example.com',
                    'password' =>
                        'password123',
                    'status' =>
                        'active',
                ],
                $attributes
            )
        );

        $user->roles()->sync([
            $role->id,
        ]);

        return $user->fresh([
            'roles.permissions',
        ]);
    }

    private function createCustomer(): User
    {
        $role = $this->createRole(
            'customer'
        );

        $user = User::factory()->create([
            'email' =>
                'customer@example.com',
            'password' =>
                'password123',
            'status' =>
                'active',
        ]);

        $user->roles()->sync([
            $role->id,
        ]);

        return $user->fresh([
            'roles.permissions',
        ]);
    }

    private function authHeaders(
        string $token
    ): array {
        return [
            'Authorization' =>
                'Bearer ' . $token,
            'Accept' =>
                'application/json',
        ];
    }

    private function assertStaffCanLogin(
        string $roleSlug
    ): void {
        $user = $this->createStaff(
            $roleSlug
        );

        $response = $this->postJson(
            '/api/admin/auth/login',
            [
                'identifier' =>
                    $user->email,
                'password' =>
                    'password123',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Login successful'
            )
            ->assertJsonPath(
                'user.email',
                $user->email
            )
            ->assertJsonPath(
                'user.roles.0.slug',
                $roleSlug
            );

        $this->assertIsString(
            $response->json('token')
        );

        $this->assertNotEmpty(
            $response->json('token')
        );

        $this->assertDatabaseHas(
            'personal_access_tokens',
            [
                'tokenable_id' =>
                    $user->id,
                'name' =>
                    'admin-token',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    */

    public function test_customer_cannot_login_to_admin_panel(): void
    {
        $customer =
            $this->createCustomer();

        $response = $this->postJson(
            '/api/admin/auth/login',
            [
                'identifier' =>
                    $customer->email,
                'password' =>
                    'password123',
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.identifier.0',
                'Invalid credentials.'
            );

        $this->assertDatabaseMissing(
            'personal_access_tokens',
            [
                'tokenable_id' =>
                    $customer->id,
                'name' =>
                    'admin-token',
            ]
        );
    }

    public function test_wrong_password_is_rejected(): void
    {
        $admin = $this->createStaff(
            'admin'
        );

        $this->postJson(
            '/api/admin/auth/login',
            [
                'identifier' =>
                    $admin->email,
                'password' =>
                    'wrong-password',
            ]
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.identifier.0',
                'Invalid credentials.'
            );
    }

    public function test_inactive_staff_is_rejected(): void
    {
        $admin = $this->createStaff(
            'admin',
            [
                'status' =>
                    'inactive',
            ]
        );

        $this->postJson(
            '/api/admin/auth/login',
            [
                'identifier' =>
                    $admin->email,
                'password' =>
                    'password123',
            ]
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.identifier.0',
                'Account is not active.'
            );
    }

    public function test_super_admin_can_login(): void
    {
        $this->assertStaffCanLogin(
            'super_admin'
        );
    }

    public function test_admin_can_login_and_receives_effective_permissions(): void
    {
        $admin = $this->createStaff(
            'admin',
            [],
            [
                'products.view',
                'orders.view',
            ]
        );

        $response = $this->postJson(
            '/api/admin/auth/login',
            [
                'identifier' =>
                    $admin->email,
                'password' =>
                    'password123',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'user.roles.0.slug',
                'admin'
            );

        $this->assertSame(
            [
                'orders.view',
                'products.view',
            ],
            $response->json(
                'user.permissions'
            )
        );

        $this->assertDatabaseHas(
            'personal_access_tokens',
            [
                'tokenable_id' =>
                    $admin->id,
                'name' =>
                    'admin-token',
            ]
        );
    }

    public function test_accountant_can_login(): void
    {
        $this->assertStaffCanLogin(
            'accountant'
        );
    }

    public function test_support_can_login(): void
    {
        $this->assertStaffCanLogin(
            'support'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Current Admin
    |--------------------------------------------------------------------------
    */

    public function test_guest_cannot_access_admin_me(): void
    {
        $this->getJson(
            '/api/admin/auth/me'
        )
            ->assertUnauthorized();
    }

    public function test_customer_token_cannot_access_admin_me(): void
    {
        $customer =
            $this->createCustomer();

        $token = $customer
            ->createToken(
                'auth-token'
            )
            ->plainTextToken;

        $this->getJson(
            '/api/admin/auth/me',
            $this->authHeaders(
                $token
            )
        )
            ->assertForbidden();
    }

    public function test_admin_me_returns_roles_and_effective_permissions(): void
    {
        $admin = $this->createStaff(
            'admin',
            [],
            [
                'customers.view',
                'orders.view',
            ]
        );

        $token = $admin
            ->createToken(
                'admin-token'
            )
            ->plainTextToken;

        $response = $this->getJson(
            '/api/admin/auth/me',
            $this->authHeaders(
                $token
            )
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'user.id',
                $admin->id
            )
            ->assertJsonPath(
                'user.roles.0.slug',
                'admin'
            );

        $this->assertSame(
            [
                'customers.view',
                'orders.view',
            ],
            $response->json(
                'user.permissions'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    public function test_admin_logout_revokes_only_current_token(): void
    {
        $admin = $this->createStaff(
            'admin'
        );

        $otherToken = $admin
            ->createToken(
                'other-token'
            )
            ->plainTextToken;

        $loginResponse =
            $this->postJson(
                '/api/admin/auth/login',
                [
                    'identifier' =>
                        $admin->email,
                    'password' =>
                        'password123',
                ]
            )
                ->assertOk();

        $adminToken =
            $loginResponse->json(
                'token'
            );

        $this->assertDatabaseHas(
            'personal_access_tokens',
            [
                'tokenable_id' =>
                    $admin->id,
                'name' =>
                    'admin-token',
            ]
        );

        $this->assertDatabaseHas(
            'personal_access_tokens',
            [
                'tokenable_id' =>
                    $admin->id,
                'name' =>
                    'other-token',
            ]
        );

        $this->postJson(
            '/api/admin/auth/logout',
            [],
            $this->authHeaders(
                $adminToken
            )
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Logged out successfully'
            );

        $this->assertDatabaseMissing(
            'personal_access_tokens',
            [
                'tokenable_id' =>
                    $admin->id,
                'name' =>
                    'admin-token',
            ]
        );

        $this->assertDatabaseHas(
            'personal_access_tokens',
            [
                'tokenable_id' =>
                    $admin->id,
                'name' =>
                    'other-token',
            ]
        );

        Auth::forgetGuards();

        $this->getJson(
            '/api/admin/auth/me',
            $this->authHeaders(
                $adminToken
            )
        )
            ->assertUnauthorized();

        Auth::forgetGuards();

        $this->getJson(
            '/api/admin/auth/me',
            $this->authHeaders(
                $otherToken
            )
        )
            ->assertOk();
    }
}
