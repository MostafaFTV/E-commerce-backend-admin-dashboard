<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CustomerDiscountCode;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountCodesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_only_sees_codes_assigned_to_the_authenticated_account(): void
    {
        $customer = $this->customer();
        $otherCustomer = $this->customer();
        $category = Category::factory()->create();

        CustomerDiscountCode::create([
            'code' => 'ACTIVE20',
            'percentage' => 20,
            'customer_id' => $customer->id,
            'scope' => 'all',
            'category_id' => null,
            'expires_at' => now()->addDay(),
            'is_active' => true,
        ]);
        CustomerDiscountCode::create([
            'code' => 'EXPIRED10',
            'percentage' => 10,
            'customer_id' => $customer->id,
            'scope' => 'category',
            'category_id' => $category->id,
            'expires_at' => now()->subDay(),
            'is_active' => true,
        ]);
        CustomerDiscountCode::create([
            'code' => 'INACTIVE5',
            'percentage' => 5,
            'customer_id' => $customer->id,
            'scope' => 'all',
            'category_id' => null,
            'expires_at' => now()->addDay(),
            'is_active' => false,
        ]);
        CustomerDiscountCode::create([
            'code' => 'OTHER15',
            'percentage' => 15,
            'customer_id' => $otherCustomer->id,
            'scope' => 'all',
            'category_id' => null,
            'expires_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($customer)
            ->getJson('/api/discounts?locale=fa')
            ->assertOk()
            ->assertJsonPath('meta.active_count', 1)
            ->assertJsonPath('data.0.status', 'inactive');

        $codes = collect($response->json('data'))->keyBy('code');

        $this->assertCount(3, $codes);
        $this->assertArrayNotHasKey('OTHER15', $codes->all());
        $this->assertSame('expired', $codes['EXPIRED10']['status']);
        $this->assertSame('inactive', $codes['INACTIVE5']['status']);
        $this->assertSame('فعال', $codes['ACTIVE20']['status_label']);
        $this->assertSame($category->id, $codes['EXPIRED10']['category']['id']);
    }

    public function test_admin_can_create_update_and_deactivate_customer_code(): void
    {
        $admin = $this->admin(['customers.view', 'customers.manage']);
        $customer = $this->customer();
        $category = Category::factory()->create();
        $childCategory = Category::factory()->create([
            'parent_id' => $category->id,
        ]);
        $this->actingAs($admin);

        $created = $this->postJson('/api/admin/discount-codes', [
            'code' => ' spring20 ',
            'percentage' => 20,
            'customer_id' => $customer->id,
            'scope' => 'category',
            'category_id' => $category->id,
            'expires_at' => now()->addDays(30)->toIso8601String(),
            'is_active' => true,
            'description' => 'Campaign note',
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'SPRING20')
            ->assertJsonPath('data.customer_id', $customer->id)
            ->assertJsonPath('data.scope', 'category')
            ->assertJsonPath('data.category.id', $category->id);

        $codeId = $created->json('data.id');

        $this->getJson('/api/admin/discount-codes?search=SPRING&status=active&scope=category')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.customer.id', $customer->id);

        $this->getJson("/api/admin/discount-codes/options?customer_id={$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.customers.0.id', $customer->id)
            ->assertJsonPath('data.categories.0.depth', 0)
            ->assertJsonPath('data.categories.1.depth', 1)
            ->assertJsonPath('data.categories.1.id', $childCategory->id);

        $this->putJson("/api/admin/discount-codes/{$codeId}", [
            'percentage' => 25,
        ])
            ->assertOk()
            ->assertJsonPath('data.percentage', 25)
            ->assertJsonPath('data.customer_id', $customer->id)
            ->assertJsonPath('data.category_id', $category->id)
            ->assertJsonPath('data.description', 'Campaign note');

        $this->patchJson("/api/admin/discount-codes/{$codeId}/deactivate", [])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');
        $this->getJson('/api/admin/discount-codes?status=inactive')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'inactive');

        $this->assertDatabaseHas('customer_discount_codes', [
            'id' => $codeId,
            'customer_id' => $customer->id,
            'percentage' => 25,
            'category_id' => $category->id,
            'is_active' => false,
        ]);
    }

    public function test_discount_code_crud_requires_customer_permissions_and_valid_scope(): void
    {
        $customer = $this->customer();
        $admin = $this->admin(['customers.view']);
        $assignedCustomer = $this->customer();
        $inactiveCategory = Category::factory()->create([
            'is_active' => false,
        ]);
        $this->actingAs($admin);

        $this->postJson('/api/admin/discount-codes', [
            'code' => 'NOTALLOWED',
            'percentage' => 10,
            'customer_id' => $assignedCustomer->id,
            'scope' => 'all',
            'category_id' => null,
            'expires_at' => now()->addDay()->toIso8601String(),
            'is_active' => true,
        ])->assertForbidden();

        $this->actingAs($this->admin(['customers.view', 'customers.manage']))
            ->postJson('/api/admin/discount-codes', [
                'code' => 'BADCAT',
                'percentage' => 10,
                'customer_id' => $assignedCustomer->id,
                'scope' => 'category',
                'category_id' => $inactiveCategory->id,
                'expires_at' => now()->addDay()->toIso8601String(),
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');

        $this->actingAs($customer)
            ->getJson('/api/admin/discount-codes')
            ->assertForbidden();

        $this->assertDatabaseCount('customer_discount_codes', 0);
    }

    public function test_duplicate_customer_codes_and_invalid_expiry_are_rejected(): void
    {
        $admin = $this->admin(['customers.view', 'customers.manage']);
        $customer = $this->customer();
        $this->actingAs($admin);

        $payload = [
            'code' => 'UNIQUE10',
            'percentage' => 10,
            'customer_id' => $customer->id,
            'scope' => 'all',
            'category_id' => null,
            'expires_at' => now()->addDay()->toIso8601String(),
            'is_active' => true,
        ];

        $this->postJson('/api/admin/discount-codes', $payload)->assertCreated();
        $this->postJson('/api/admin/discount-codes', [
            ...$payload,
            'code' => 'unique10',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/admin/discount-codes', [
            ...$payload,
            'code' => 'PAST10',
            'expires_at' => now()->subDay()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('expires_at');
        $this->postJson('/api/admin/discount-codes', [
            ...$payload,
            'code' => 'ZEROPER',
            'percentage' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('percentage');

        $this->assertDatabaseCount('customer_discount_codes', 1);
    }

    private function customer(): User
    {
        $customer = User::factory()->create();
        $role = Role::firstOrCreate(
            ['slug' => 'customer'],
            ['name' => 'Customer']
        );
        $customer->roles()->syncWithoutDetaching([$role->id]);

        return $customer;
    }

    private function admin(array $permissionSlugs): User
    {
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrator']
        );

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug]
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $admin->roles()->syncWithoutDetaching([$role->id]);

        return $admin;
    }
}
