<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Collection;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_collection_index_returns_only_active_collections(): void
    {
        $activeCollection = $this->createCollection([
            'name' => 'Active Collection',
            'slug' => 'active-collection',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->createCollection([
            'name' => 'Inactive Collection',
            'slug' => 'inactive-collection',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $response = $this->getJson(
            '/api/collections'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $activeCollection->id
            )
            ->assertJsonPath(
                'data.0.slug',
                'active-collection'
            );
    }

    public function test_public_collection_show_returns_active_collection(): void
    {
        $collection = $this->createCollection([
            'name' => 'Tableware',
            'slug' => 'tableware',
            'is_active' => true,
        ]);

        $response = $this->getJson(
            '/api/collections/tableware'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $collection->id
            )
            ->assertJsonPath(
                'data.name',
                'Tableware'
            )
            ->assertJsonPath(
                'data.slug',
                'tableware'
            );
    }

    public function test_inactive_collection_is_not_publicly_visible(): void
    {
        $this->createCollection([
            'name' => 'Hidden Collection',
            'slug' => 'hidden-collection',
            'is_active' => false,
        ]);

        $response = $this->getJson(
            '/api/collections/hidden-collection'
        );

        $response->assertNotFound();
    }

    public function test_unknown_collection_slug_returns_404(): void
    {
        $response = $this->getJson(
            '/api/collections/does-not-exist'
        );

        $response->assertNotFound();
    }

    public function test_collection_show_returns_only_active_non_deleted_products(): void
    {
        $collection = $this->createCollection([
            'name' => 'Kitchen Collection',
            'slug' => 'kitchen-collection',
            'is_active' => true,
        ]);

        $activeProduct = $this->createProduct([
            'name' => 'Active Product',
            'slug' => 'active-product',
            'status' => ProductStatus::ACTIVE,
        ]);

        $inactiveProduct = $this->createProduct([
            'name' => 'Inactive Product',
            'slug' => 'inactive-product',
            'status' => ProductStatus::INACTIVE,
        ]);

        $deletedProduct = $this->createProduct([
            'name' => 'Deleted Product',
            'slug' => 'deleted-product',
            'status' => ProductStatus::ACTIVE,
        ]);

        $collection->products()->attach([
            $activeProduct->id,
            $inactiveProduct->id,
            $deletedProduct->id,
        ]);

        $deletedProduct->delete();

        $response = $this->getJson(
            '/api/collections/kitchen-collection'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath(
                'data.products.0.id',
                $activeProduct->id
            )
            ->assertJsonPath(
                'data.products.0.slug',
                'active-product'
            );
    }

    public function test_collection_show_returns_products_count(): void
    {
        $collection = $this->createCollection([
            'name' => 'Cookware',
            'slug' => 'cookware',
            'is_active' => true,
        ]);

        $activeProductOne = $this->createProduct([
            'status' => ProductStatus::ACTIVE,
        ]);

        $activeProductTwo = $this->createProduct([
            'status' => ProductStatus::ACTIVE,
        ]);

        $inactiveProduct = $this->createProduct([
            'status' => ProductStatus::INACTIVE,
        ]);

        $collection->products()->attach([
            $activeProductOne->id,
            $activeProductTwo->id,
            $inactiveProduct->id,
        ]);

        $response = $this->getJson(
            '/api/collections/cookware'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.products_count',
                2
            );
    }

    public function test_collection_can_be_viewed_by_guest(): void
    {
        $this->createCollection([
            'name' => 'Guest Collection',
            'slug' => 'guest-collection',
            'is_active' => true,
        ]);

        $response = $this->getJson(
            '/api/collections/guest-collection'
        );

        $response->assertOk();
    }

    public function test_collection_api_does_not_require_authentication(): void
    {
        $this->createCollection([
            'name' => 'Public Collection',
            'slug' => 'public-collection',
            'is_active' => true,
        ]);

        $response = $this->getJson(
            '/api/collections'
        );

        $response->assertOk();
    }

    private function createCollection(
        array $attributes = []
    ): Collection {
        return Collection::create(
            array_merge(
                [
                    'name' => 'Test Collection',
                    'slug' => 'test-collection-' . uniqid(),
                    'description' => 'Test collection',
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
