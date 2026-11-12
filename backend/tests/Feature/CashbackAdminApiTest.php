<?php

namespace Tests\Feature;

use App\Models\CashbackRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbackAdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_rejected(): void
    {
        $this->getJson('/api/admin/cashback/rules')->assertUnauthorized();
    }

    public function test_customer_cannot_manage_rules(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/admin/cashback/rules', [
                'name' => 'Not allowed', 'scope' => 'all',
                'percentage' => '5.00', 'expires_after_days' => 30,
            ])->assertForbidden();
        $this->assertDatabaseCount('cashback_rules', 0);
    }

    public function test_authorized_admin_can_manage_rule_and_view_report(): void
    {
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        foreach (['orders.view', 'orders.manage'] as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $admin->roles()->syncWithoutDetaching([$role->id]);
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/cashback/rules', [
            'name' => 'Storewide reward',
            'scope' => 'all',
            'percentage' => '7.50',
            'priority' => 10,
            'is_active' => true,
            'expires_after_days' => 90,
        ])->assertCreated()->assertJsonPath('data.name', 'Storewide reward');
        $id = $response->json('data.id');
        $this->putJson('/api/admin/cashback/rules/'.$id, [
            'percentage' => '8.00',
        ])->assertOk()->assertJsonPath('data.percentage', '8.00');
        $this->getJson('/api/admin/cashback/rules')->assertOk();
        $this->getJson('/api/admin/cashback/report')->assertOk();
        $this->deleteJson('/api/admin/cashback/rules/'.$id)->assertOk();
        $this->assertDatabaseCount('cashback_rules', 0);
    }

    public function test_rule_rejects_invalid_scope_configuration(): void
    {
        $this->withoutMiddleware();
        $this->postJson('/api/admin/cashback/rules', [
            'name' => 'Invalid rule',
            'scope' => 'products',
            'percentage' => '5.00',
            'expires_after_days' => 30,
        ])->assertUnprocessable();
        $this->assertDatabaseCount('cashback_rules', 0);
    }
}