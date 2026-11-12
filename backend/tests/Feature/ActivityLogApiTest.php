<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityLogApiTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function createRole(
        string $slug,
        array $permissionSlugs = []
    ): Role {
        $role = Role::create([
            'name' => Str::headline($slug),
            'slug' => $slug,
        ]);

        $permissions = collect(
            $permissionSlugs
        )->map(
            function (string $permissionSlug) {
                return Permission::firstOrCreate(
                    [
                        'slug' => $permissionSlug,
                    ],
                    [
                        'name' => Str::headline(
                            str_replace(
                                '.',
                                ' ',
                                $permissionSlug
                            )
                        ),
                    ]
                );
            }
        );

        if ($permissions->isNotEmpty()) {
            $role->permissions()->sync(
                $permissions->pluck('id')
            );
        }

        return $role;
    }

    protected function createUserWithRole(
        string $roleSlug,
        array $permissionSlugs = []
    ): User {
        $role = $this->createRole(
            $roleSlug,
            $permissionSlugs
        );

        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $user->roles()->attach(
            $role->id
        );

        return $user;
    }

    protected function createLog(
        array $attributes = []
    ): ActivityLog {
        return ActivityLog::create(
            array_merge(
                [
                    'user_id' => null,
                    'action' => 'product.updated',
                    'description' => 'Product updated.',
                    'properties' => [
                        'subject' => [
                            'type' => 'App\\Models\\Product',
                            'id' => 1,
                        ],
                        'changes' => [
                            'name' => [
                                'old' => 'Old Product',
                                'new' => 'New Product',
                            ],
                        ],
                    ],
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'PHPUnit Test Browser',
                    'created_at' => now(),
                ],
                $attributes
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication / Authorization
    |--------------------------------------------------------------------------
    */

    public function test_guest_cannot_access_activity_logs(): void
    {
        $this->getJson(
            '/api/admin/activity-logs'
        )->assertUnauthorized();
    }

    public function test_admin_without_activity_log_permission_is_forbidden(): void
    {
        $user = $this->createUserWithRole(
            'admin'
        );

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/admin/activity-logs'
        )->assertForbidden();
    }

    public function test_admin_with_activity_log_permission_can_access_logs(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'activity_logs.view',
            ]
        );

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/admin/activity-logs'
        )->assertOk();
    }

    public function test_accountant_cannot_access_activity_logs(): void
    {
        $user = $this->createUserWithRole(
            'accountant'
        );

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/admin/activity-logs'
        )->assertForbidden();
    }

    public function test_support_cannot_access_activity_logs(): void
    {
        $user = $this->createUserWithRole(
            'support'
        );

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/admin/activity-logs'
        )->assertForbidden();
    }

    public function test_customer_cannot_access_activity_logs(): void
    {
        $user = $this->createUserWithRole(
            'customer'
        );

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/admin/activity-logs'
        )->assertForbidden();
    }

    public function test_super_admin_with_activity_log_permission_can_access_logs(): void
    {
        $user = $this->createUserWithRole(
            'super_admin',
            [
                'activity_logs.view',
            ]
        );

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/admin/activity-logs'
        )->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_list_activity_logs(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'activity_logs.view',
            ]
        );

        $this->createLog();

        $this->createLog([
            'action' => 'settings.updated',
            'description' => 'Settings updated.',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/admin/activity-logs'
        );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);

        $this->assertCount(
            2,
            $response->json('data')
        );
    }

    public function test_activity_logs_are_ordered_newest_first(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'activity_logs.view',
            ]
        );

        $older = $this->createLog([
            'action' => 'products.created',
            'created_at' => Carbon::now()->subDay(),
        ]);

        $newer = $this->createLog([
            'action' => 'products.updated',
            'created_at' => Carbon::now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/admin/activity-logs'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $newer->id
            )
            ->assertJsonPath(
                'data.1.id',
                $older->id
            );
    }

    public function test_activity_logs_can_be_filtered_by_action(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'activity_logs.view',
            ]
        );

        $this->createLog([
            'action' => 'products.updated',
        ]);

        $this->createLog([
            'action' => 'settings.updated',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/admin/activity-logs?action=settings.updated'
        );

        $response->assertOk();

        $data = $response->json('data');

        $this->assertCount(
            1,
            $data
        );

        $this->assertSame(
            'settings.updated',
            $data[0]['action']
        );
    }

    public function test_activity_logs_can_be_filtered_by_user(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'activity_logs.view',
            ]
        );

        $actorOne = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $actorTwo = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $this->createLog([
            'user_id' => $actorOne->id,
        ]);

        $this->createLog([
            'user_id' => $actorTwo->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/admin/activity-logs?user_id='
            . $actorOne->id
        );

        $response->assertOk();

        $data = $response->json('data');

        $this->assertCount(
            1,
            $data
        );

        $this->assertSame(
            $actorOne->id,
            $data[0]['user']['id']
        );
    }

    public function test_activity_logs_can_be_searched(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'activity_logs.view',
            ]
        );

        $this->createLog([
            'action' => 'products.updated',
            'description' => 'Luxury pan updated.',
        ]);

        $this->createLog([
            'action' => 'settings.updated',
            'description' => 'Settings updated.',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/admin/activity-logs?search=Luxury'
        );

        $response->assertOk();

        $data = $response->json('data');

        $this->assertCount(
            1,
            $data
        );

        $this->assertSame(
            'Luxury pan updated.',
            $data[0]['description']
        );
    }

    public function test_activity_log_actions_are_available_for_filtering(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'activity_logs.view',
            ]
        );

        $this->createLog([
            'action' => 'products.updated',
        ]);

        $this->createLog([
            'action' => 'settings.updated',
        ]);

        $this->createLog([
            'action' => 'products.updated',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/admin/activity-logs/actions'
        );

        $response
            ->assertOk()
            ->assertJson([
                'data' => [
                    'products.updated',
                    'settings.updated',
                ],
            ]);
    }

    public function test_activity_logs_can_be_filtered_by_date_range(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'activity_logs.view',
            ]
        );

        $this->createLog([
            'action' => 'old.action',
            'created_at' => Carbon::parse(
                '2026-01-15 10:00:00'
            ),
        ]);

        $included = $this->createLog([
            'action' => 'included.action',
            'created_at' => Carbon::parse(
                '2026-09-05 12:00:00'
            ),
        ]);

        $this->createLog([
            'action' => 'future.action',
            'created_at' => Carbon::parse(
                '2026-09-20 12:00:00'
            ),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/admin/activity-logs?from=2026-09-05&to=2026-09-05'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $included->id
            );

        $this->assertCount(
            1,
            $response->json('data')
        );
    }

    public function test_activity_logs_are_paginated(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'activity_logs.view',
            ]
        );

        for (
            $index = 1;
            $index <= 3;
            $index++
        ) {
            $this->createLog([
                'action' => 'test.action.' . $index,
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/admin/activity-logs?per_page=2'
        );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);

        $this->assertCount(
            2,
            $response->json('data')
        );

        $this->assertSame(
            2,
            $response->json('meta.per_page')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_view_single_activity_log(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'activity_logs.view',
            ]
        );

        $actor = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $log = $this->createLog([
            'user_id' => $actor->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/admin/activity-logs/' . $log->id
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $log->id
            )
            ->assertJsonPath(
                'data.action',
                $log->action
            )
            ->assertJsonPath(
                'data.user.id',
                $actor->id
            )
            ->assertJsonPath(
                'data.properties.subject.type',
                'App\\Models\\Product'
            );
    }

    public function test_unknown_activity_log_returns_404(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'activity_logs.view',
            ]
        );

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/admin/activity-logs/999999'
        )->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Read-only API
    |--------------------------------------------------------------------------
    */

    public function test_activity_logs_do_not_support_create(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'activity_logs.view',
            ]
        );

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/admin/activity-logs',
            []
        )->assertMethodNotAllowed();
    }

    public function test_activity_logs_do_not_support_update(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'activity_logs.view',
            ]
        );

        $log = $this->createLog();

        Sanctum::actingAs($user);

        $this->putJson(
            '/api/admin/activity-logs/' . $log->id,
            []
        )->assertMethodNotAllowed();
    }

    public function test_activity_logs_do_not_support_delete(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'activity_logs.view',
            ]
        );

        $log = $this->createLog();

        Sanctum::actingAs($user);

        $this->deleteJson(
            '/api/admin/activity-logs/' . $log->id
        )->assertMethodNotAllowed();

        $this->assertDatabaseHas(
            'activity_logs',
            [
                'id' => $log->id,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Resource Data
    |--------------------------------------------------------------------------
    */

    public function test_activity_log_resource_exposes_expected_fields(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'activity_logs.view',
            ]
        );

        $log = $this->createLog();

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/admin/activity-logs/' . $log->id
        );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user',
                    'action',
                    'description',
                    'properties',
                    'ip_address',
                    'user_agent',
                    'created_at',
                ],
            ]);
    }

    public function test_activity_log_without_user_returns_null_user(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'activity_logs.view',
            ]
        );

        $log = $this->createLog([
            'user_id' => null,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/admin/activity-logs/' . $log->id
        )
            ->assertOk()
            ->assertJsonPath(
                'data.user',
                null
            );
    }
}