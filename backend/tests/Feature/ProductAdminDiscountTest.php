<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAdminDiscountTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_discount_can_be_updated_and_removed_without_changing_base_price(): void
    {
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrator']
        );
        $permission = Permission::firstOrCreate(
            ['slug' => 'products.update'],
            ['name' => 'Update Products']
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $admin->roles()->syncWithoutDetaching([$role->id]);

        $product = Product::factory()->create([
            'price' => 2500,
            'discount_percentage' => 15,
        ]);

        $this->actingAs($admin)
            ->putJson("/api/admin/products/{$product->id}", [
                'discount_percentage' => 40,
            ])
            ->assertOk()
            ->assertJsonPath('data.discount_percentage', 40)
            ->assertJsonPath('data.base_price', 2500);

        $this->actingAs($admin)
            ->putJson("/api/admin/products/{$product->id}", [
                'discount_percentage' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.discount_percentage', null)
            ->assertJsonPath('data.base_price', 2500);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'price' => 2500,
            'discount_percentage' => null,
        ]);
    }

    public function test_product_discount_requires_products_update_permission(): void
    {
        $product = Product::factory()->create();
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->putJson("/api/admin/products/{$product->id}", [
                'discount_percentage' => 20,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'discount_percentage' => null,
        ]);
    }
}
