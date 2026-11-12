<?php

namespace Tests\Feature;

use App\Models\Attribute as ProductAttribute;
use App\Models\AttributeValue;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CatalogDetailArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_supports_multiple_ordered_images_and_primary_image(): void
    {
        $product = Product::factory()->create();

        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'second.jpg',
            'sort_order' => 2,
            'is_primary' => false,
        ]);

        $primary = ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'primary.jpg',
            'sort_order' => 1,
            'is_primary' => true,
        ]);

        $this->assertCount(2, $product->images);
        $this->assertSame(
            ['primary.jpg', 'second.jpg'],
            $product->images->pluck('image_path')->all()
        );
        $this->assertTrue(
            $product->images->first()->is_primary
        );
        $this->assertTrue(
            $primary->product->is($product)
        );
    }

    public function test_variant_supports_multiple_ordered_images_and_product_parent(): void
    {
        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'image_path' => 'variant-two.jpg',
            'sort_order' => 2,
            'is_primary' => false,
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'image_path' => 'variant-one.jpg',
            'sort_order' => 1,
            'is_primary' => true,
        ]);

        $variant->load(
            'images',
            'product'
        );

        $this->assertSame(
            ['variant-one.jpg', 'variant-two.jpg'],
            $variant->images->pluck('image_path')->all()
        );

        $this->assertTrue(
            $variant->images->first()->is_primary
        );

        $this->assertTrue(
            $variant->product->is($product)
        );
    }

    public function test_image_foreign_keys_and_variant_ownership_are_enforced(): void
    {
        $product = Product::factory()->create();

        $this->expectException(QueryException::class);

        ProductImage::create([
            'product_id' => $product->id,
            'product_variant_id' => 999999,
            'image_path' => 'invalid.jpg',
        ]);
    }

    public function test_variant_image_cannot_belong_to_another_product(): void
    {
        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' => $otherProduct->id,
        ]);

        $this->expectException(ValidationException::class);

        ProductImage::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'image_path' => 'invalid-owner.jpg',
        ]);
    }

    public function test_attribute_belongs_to_values_and_values_have_unique_identity(): void
    {
        $attribute = ProductAttribute::create([
            'name' => 'Color',
            'slug' => 'color',
            'type' => 'select',
        ]);

        $value = AttributeValue::create([
            'attribute_id' => $attribute->id,
            'value' => 'Red',
        ]);

        $this->assertTrue(
            $value->attribute->is($attribute)
        );

        $this->assertTrue(
            $attribute->values->first()->is($value)
        );

        $this->expectException(QueryException::class);

        AttributeValue::create([
            'attribute_id' => $attribute->id,
            'value' => 'Red',
        ]);
    }

    public function test_variant_can_load_attribute_values_through_the_existing_pivot(): void
    {
        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
        ]);

        $color = ProductAttribute::create([
            'name' => 'Color',
            'slug' => 'color',
            'type' => 'select',
        ]);

        $size = ProductAttribute::create([
            'name' => 'Size',
            'slug' => 'size',
            'type' => 'select',
        ]);

        $red = AttributeValue::create([
            'attribute_id' => $color->id,
            'value' => 'Red',
        ]);

        $large = AttributeValue::create([
            'attribute_id' => $size->id,
            'value' => 'Large',
        ]);

        $variant->attributeValues()->sync([
            $red->id,
            $large->id,
        ]);

        $variant->load(
            'attributeValues.attribute'
        );

        $this->assertEqualsCanonicalizing(
            [$red->id, $large->id],
            $variant->attributeValues
                ->pluck('id')
                ->all()
        );

        $this->assertSame(
            ['Color', 'Size'],
            $variant->attributeValues
                ->pluck('attribute.name')
                ->all()
        );
    }

    public function test_variant_attribute_pivot_prevents_duplicate_relationships(): void
    {
        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
        ]);

        $attribute = ProductAttribute::create([
            'name' => 'Color',
            'slug' => 'color',
            'type' => 'select',
        ]);

        $value = AttributeValue::create([
            'attribute_id' => $attribute->id,
            'value' => 'Red',
        ]);

        $variant->attributeValues()->sync([
            $value->id,
        ]);

        $variant->attributeValues()->syncWithoutDetaching([
            $value->id,
        ]);

        $this->assertDatabaseCount(
            'variant_attribute_values',
            1
        );
    }

    public function test_product_can_belong_to_multiple_collections_without_duplicate_pivots(): void
    {
        $product = Product::factory()->create();

        $first = Collection::create([
            'name' => 'Tea',
            'slug' => 'tea',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $second = Collection::create([
            'name' => 'Gifts',
            'slug' => 'gifts',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $product->collections()->sync([
            $first->id,
            $second->id,
        ]);

        $product->collections()->syncWithoutDetaching([
            $first->id,
        ]);

        $product->load('collections');

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $product->collections
                ->pluck('id')
                ->all()
        );

        $this->assertDatabaseCount(
            'collection_product',
            2
        );
    }

    public function test_collection_can_load_its_products(): void
    {
        $product = Product::factory()->create();

        $collection = Collection::create([
            'name' => 'Featured',
            'slug' => 'featured',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $product->collections()->attach(
            $collection
        );

        $this->assertTrue(
            $collection
                ->fresh()
                ->products
                ->first()
                ->is($product)
        );
    }

    public function test_admin_rejects_invalid_attribute_value_id(): void
    {
        $admin = $this->adminUser();
        $product = Product::factory()->create();

        $this->actingAs($admin)
            ->postJson('/api/admin/products', [
                'category_id' => $product->category_id,

                'name' => [
                    'en' => 'Invalid Attribute Product',
                    'fa' => 'محصول ویژگی نامعتبر',
                ],

                'slug' => 'invalid-attribute-product',

                'price' => 10,

                'variants' => [
                    [
                        'sku' => 'INVALID-ATTRIBUTE',
                        'attribute_values' => [
                            999999,
                        ],
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'variants.0.attribute_values.0',
            ]);
    }

    public function test_admin_rejects_duplicate_attribute_values_and_multiple_values_from_one_attribute(): void
    {
        $admin = $this->adminUser();

        $product = Product::factory()->create();

        $attribute = ProductAttribute::create([
            'name' => 'Color',
            'slug' => 'color',
            'type' => 'select',
        ]);

        $red = AttributeValue::create([
            'attribute_id' => $attribute->id,
            'value' => 'Red',
        ]);

        $blue = AttributeValue::create([
            'attribute_id' => $attribute->id,
            'value' => 'Blue',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Duplicate Attribute Value
        |--------------------------------------------------------------------------
        */

        $this->actingAs($admin)
            ->postJson('/api/admin/products', [
                'category_id' => $product->category_id,

                'name' => [
                    'en' => 'Duplicate Attribute Product',
                    'fa' => 'محصول ویژگی تکراری',
                ],

                'slug' => 'duplicate-attribute-product',

                'price' => 10,

                'variants' => [
                    [
                        'sku' => 'DUPLICATE-ATTRIBUTE',

                        'attribute_values' => [
                            $red->id,
                            $red->id,
                        ],
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'variants.0.attribute_values.1',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Multiple Values From The Same Attribute
        |--------------------------------------------------------------------------
        */

        $this->actingAs($admin)
            ->postJson('/api/admin/products', [
                'category_id' => $product->category_id,

                'name' => [
                    'en' => 'Multiple Attribute Product',
                    'fa' => 'محصول چند مقدار از یک ویژگی',
                ],

                'slug' => 'multiple-attribute-product',

                'price' => 10,

                'variants' => [
                    [
                        'sku' => 'MULTIPLE-ATTRIBUTE',

                        'attribute_values' => [
                            $red->id,
                            $blue->id,
                        ],
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'variants.0.attribute_values',
            ]);
    }

    public function test_product_and_variant_relationships_support_detail_eager_loading(): void
    {
        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
        ]);

        $collection = Collection::create([
            'name' => 'Detail',
            'slug' => 'detail',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $attribute = ProductAttribute::create([
            'name' => 'Material',
            'slug' => 'material',
            'type' => 'select',
        ]);

        $value = AttributeValue::create([
            'attribute_id' => $attribute->id,
            'value' => 'Steel',
        ]);

        $product->collections()->attach(
            $collection
        );

        $variant->attributeValues()->attach(
            $value
        );

        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'product.jpg',
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'image_path' => 'variant.jpg',
        ]);

        $loaded = Product::with([
            'images',
            'collections',
            'variants.attributeValues.attribute',
            'variants.images',
        ])->findOrFail(
            $product->id
        );

        $this->assertTrue(
            $loaded->relationLoaded('images')
        );

        $this->assertTrue(
            $loaded->relationLoaded('collections')
        );

        $this->assertTrue(
            $loaded->variants
                ->first()
                ->relationLoaded('attributeValues')
        );

        $this->assertTrue(
            $loaded->variants
                ->first()
                ->relationLoaded('images')
        );
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
        ]);

        $permissions = collect([
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
        ])->map(
            fn (string $slug) => Permission::create([
                'name' => $slug,
                'slug' => $slug,
            ])
        );

        $role->permissions()->sync(
            $permissions->pluck('id')
        );

        $user->roles()->attach(
            $role
        );

        return $user;
    }
}
