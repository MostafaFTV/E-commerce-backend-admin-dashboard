<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use App\Models\Collection;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCollectionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_collections(): void
    {
        $admin = $this->createAdmin([
            'collections.view',
        ]);

        $collection = $this->createCollection();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/collections');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $collection->id
            )
            ->assertJsonPath(
                'data.0.name',
                $collection->name
            )
            ->assertJsonPath(
                'data.0.slug',
                $collection->slug
            );
    }

    public function test_admin_can_view_collection_details(): void
    {
        $admin = $this->createAdmin([
            'collections.view',
        ]);

        $collection = $this->createCollection();

        $product = $this->createProduct();

        $collection->products()->attach(
            $product->id
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/admin/collections/{$collection->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $collection->id
            )
            ->assertJsonPath(
                'data.products_count',
                1
            )
            ->assertJsonCount(
                1,
                'data.products'
            )
            ->assertJsonPath(
                'data.products.0.id',
                $product->id
            );
    }

    public function test_admin_can_create_collection_with_products(): void
    {
        $admin = $this->createAdmin([
            'collections.create',
        ]);

        $productOne = $this->createProduct();
        $productTwo = $this->createProduct();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/collections',
                [
                    'name' => [
                        'en' => 'Luxury Cookware',
                        'fa' => 'ظروف آشپزی لوکس',
                    ],
                    'slug' => 'luxury-cookware',
                    'description' => [
                        'en' => 'Premium cookware collection.',
                        'fa' => 'مجموعه ظروف آشپزی ممتاز.',
                    ],
                    'image' => 'collections/luxury-cookware.webp',
                    'is_active' => true,
                    'sort_order' => 5,
                    'product_ids' => [
                        $productOne->id,
                        $productTwo->id,
                    ],
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.name',
                'Luxury Cookware'
            )
            ->assertJsonPath(
                'data.slug',
                'luxury-cookware'
            )
            ->assertJsonPath(
                'data.is_active',
                true
            )
            ->assertJsonPath(
                'data.sort_order',
                5
            )
            ->assertJsonPath(
                'data.products_count',
                2
            );

        $collection = Collection::where(
            'slug',
            'luxury-cookware'
        )->firstOrFail();

        $this->assertDatabaseHas(
            'collection_product',
            [
                'collection_id' => $collection->id,
                'product_id' => $productOne->id,
            ]
        );

        $this->assertDatabaseHas(
            'collection_product',
            [
                'collection_id' => $collection->id,
                'product_id' => $productTwo->id,
            ]
        );
    }

    public function test_admin_can_update_collection_and_sync_products(): void
    {
        $admin = $this->createAdmin([
            'collections.update',
        ]);

        $collection = $this->createCollection([
            'name' => [
                'en' => 'Original Collection',
                'fa' => 'مجموعه اصلی',
            ],
            'slug' => 'original-collection',
        ]);

        $oldProduct = $this->createProduct();
        $newProduct = $this->createProduct();

        $collection->products()->attach(
            $oldProduct->id
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->putJson(
                "/api/admin/collections/{$collection->id}",
                [
                    'name' => [
                        'en' => 'Updated Collection',
                        'fa' => 'مجموعه به‌روزشده',
                    ],
                    'slug' => 'updated-collection',
                    'description' => [
                        'en' => 'Updated description.',
                        'fa' => 'توضیحات به‌روزشده.',
                    ],
                    'is_active' => false,
                    'sort_order' => 10,
                    'product_ids' => [
                        $newProduct->id,
                    ],
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Updated Collection'
            )
            ->assertJsonPath(
                'data.slug',
                'updated-collection'
            )
            ->assertJsonPath(
                'data.is_active',
                false
            )
            ->assertJsonPath(
                'data.sort_order',
                10
            )
            ->assertJsonPath(
                'data.products_count',
                1
            )
            ->assertJsonPath(
                'data.products.0.id',
                $newProduct->id
            );

        $this->assertDatabaseMissing(
            'collection_product',
            [
                'collection_id' => $collection->id,
                'product_id' => $oldProduct->id,
            ]
        );

        $this->assertDatabaseHas(
            'collection_product',
            [
                'collection_id' => $collection->id,
                'product_id' => $newProduct->id,
            ]
        );
    }

    public function test_admin_can_delete_collection(): void
    {
        $admin = $this->createAdmin([
            'collections.delete',
        ]);

        $collection = $this->createCollection();

        $product = $this->createProduct();

        $collection->products()->attach(
            $product->id
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->deleteJson(
                "/api/admin/collections/{$collection->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Collection deleted successfully.'
            );

        $this->assertDatabaseMissing(
            'collections',
            [
                'id' => $collection->id,
            ]
        );

        $this->assertDatabaseMissing(
            'collection_product',
            [
                'collection_id' => $collection->id,
                'product_id' => $product->id,
            ]
        );
    }

    public function test_user_without_collection_permission_cannot_view_collections(): void
    {
        $user = $this->createAdmin([]);

        $this->createCollection();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/admin/collections');

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'Forbidden: Missing permission',
                'errors' => [
                    'authorization' => [
                        'Missing required permission.',
                    ],
                ],
            ]);
    }

    public function test_user_without_create_permission_cannot_create_collection(): void
    {
        $user = $this->createAdmin([
            'collections.view',
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                '/api/admin/collections',
                [
                    'name' => [
                        'en' => 'Unauthorized Collection',
                        'fa' => 'مجموعه غیرمجاز',
                    ],
                    'slug' => 'unauthorized-collection',
                    'is_active' => true,
                ]
            );

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'Forbidden: Missing permission',
                'errors' => [
                    'authorization' => [
                        'Missing required permission.',
                    ],
                ],
            ]);

        $this->assertDatabaseMissing(
            'collections',
            [
                'slug' => 'unauthorized-collection',
            ]
        );
    }

    public function test_duplicate_collection_slug_is_rejected(): void
    {
        $admin = $this->createAdmin([
            'collections.create',
        ]);

        $this->createCollection([
            'slug' => 'duplicate-slug',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/collections',
                [
                    'name' => [
                        'en' => 'Another Collection',
                        'fa' => 'مجموعه دیگر',
                    ],
                    'slug' => 'duplicate-slug',
                    'is_active' => true,
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);
    }

    public function test_duplicate_product_ids_are_rejected(): void
    {
        $admin = $this->createAdmin([
            'collections.create',
        ]);

        $product = $this->createProduct();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/collections',
                [
                    'name' => [
                        'en' => 'Duplicate Product IDs',
                        'fa' => 'شناسه محصولات تکراری',
                    ],
                    'slug' => 'duplicate-product-ids',
                    'is_active' => true,
                    'product_ids' => [
                        $product->id,
                        $product->id,
                    ],
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'product_ids.1',
            ]);
    }

    public function test_guest_cannot_access_admin_collections(): void
    {
        $response = $this->getJson(
            '/api/admin/collections'
        );

        $response->assertUnauthorized();
    }

    private function createAdmin(
        array $permissionSlugs
    ): User {
        $admin = User::factory()->create([
            'status' => UserStatus::ACTIVE,
            'email' => 'collection-admin-' .
                uniqid() .
                '@tkb.test',
        ]);

        $role = Role::create([
            'name' => 'Collection Admin',
            'slug' => 'collection-admin-' .
                uniqid(),
            'description' => 'Test collection admin role',
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

    private function createCollection(
        array $attributes = []
    ): Collection {
        return Collection::create(
            array_merge(
                [
                    'name' => [
                        'en' => 'Test Collection',
                        'fa' => 'مجموعه تست',
                    ],
                    'slug' => 'test-collection-' .
                        uniqid(),
                    'description' => [
                        'en' => 'Test collection',
                        'fa' => 'مجموعه تست',
                    ],
                    'image' => null,
                    'is_active' => true,
                    'sort_order' => 1,
                ],
                $attributes
            )
        );
    }

    private function createProduct(
        array $attributes = []
    ): Product {
        return Product::factory()->create(
            array_merge(
                [
                    'status' => ProductStatus::ACTIVE,
                ],
                $attributes
            )
        );
    }
}
