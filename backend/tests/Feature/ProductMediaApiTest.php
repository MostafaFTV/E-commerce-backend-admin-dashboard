<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use App\Enums\VariantStatus;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductMediaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_detail_separates_general_and_color_images(): void
    {
        $category = Category::factory()->create([
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'slug' => 'media-color-product',
            'status' => ProductStatus::ACTIVE,
        ]);

        $color = Attribute::create([
            'name' => 'Color',
            'slug' => 'color',
            'type' => 'color',
        ]);

        $green = AttributeValue::create([
            'attribute_id' => $color->id,
            'value' => 'Green',
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::ACTIVE,
        ]);

        $variant
            ->attributeValues()
            ->attach($green->id);

        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'general.jpg',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'attribute_value_id' => $green->id,
            'image_path' => 'green.jpg',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $this
            ->getJson(
                "/api/products/{$product->slug}"
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.images'
            )
            ->assertJsonCount(
                1,
                'data.color_images'
            )
            ->assertJsonPath(
                'data.images.0.image_path',
                'general.jpg'
            )
            ->assertJsonPath(
                'data.color_images.0.image_path',
                'green.jpg'
            )
            ->assertJsonPath(
                'data.color_images.0.attribute_value_id',
                $green->id
            );
    }

    public function test_admin_can_upload_multiple_general_images(): void
    {
        Storage::fake('public');

        $admin = $this->adminUser([
            'products.update',
        ]);

        $product = Product::factory()->create([
            'slug' => 'upload-media-product',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->post(
                "/api/admin/products/{$product->id}/images",
                [
                    'images' => [
                        $this->fakePng('front.png'),

                        $this->fakePng('hover.png'),
                    ],
                ],
                [
                    'Accept' => 'application/json',
                ]
            )
            ->assertCreated()
            ->assertJsonCount(
                2,
                'data'
            );

        $images =
            ProductImage::query()
                ->where(
                    'product_id',
                    $product->id
                )
                ->orderBy('sort_order')
                ->get();

        $this->assertCount(
            2,
            $images
        );

        $this->assertTrue(
            (bool) $images[0]->is_primary
        );

        $this->assertFalse(
            (bool) $images[1]->is_primary
        );

        $this->assertSame(
            0,
            $images[0]->sort_order
        );

        $this->assertSame(
            1,
            $images[1]->sort_order
        );

        foreach ($images as $image) {
            Storage::disk('public')
                ->assertExists(
                    Str::after(
                        $image->image_path,
                        '/storage/'
                    )
                );
        }
    }

    public function test_color_image_requires_color_used_by_product(): void
    {
        Storage::fake('public');

        $admin = $this->adminUser([
            'products.update',
        ]);

        $product =
            Product::factory()->create();

        $color = Attribute::create([
            'name' => 'Color',
            'slug' => 'color',
            'type' => 'color',
        ]);

        $green = AttributeValue::create([
            'attribute_id' => $color->id,
            'value' => 'Green',
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->post(
                "/api/admin/products/{$product->id}/images",
                [
                    'images' => [
                        $this->fakePng('green.png'),
                    ],

                    'attribute_value_id' =>
                        $green->id,
                ],
                [
                    'Accept' => 'application/json',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'attribute_value_id',
            ]);
    }

    public function test_media_rejects_variant_from_another_product(): void
    {
        Storage::fake('public');

        $admin = $this->adminUser([
            'products.update',
        ]);

        $product =
            Product::factory()->create();

        $otherProduct =
            Product::factory()->create();

        $otherVariant =
            ProductVariant::factory()->create([
                'product_id' =>
                    $otherProduct->id,
            ]);

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->post(
                "/api/admin/products/{$product->id}/images",
                [
                    'images' => [
                        $this->fakePng('wrong.png'),
                    ],

                    'product_variant_id' =>
                        $otherVariant->id,
                ],
                [
                    'Accept' => 'application/json',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'product_variant_id',
            ]);
    }

    public function test_general_gallery_reorder_does_not_mix_color_gallery(): void
    {
        $admin = $this->adminUser([
            'products.update',
        ]);

        $product =
            Product::factory()->create();

        $color = Attribute::create([
            'name' => 'Color',
            'slug' => 'color',
            'type' => 'color',
        ]);

        $green = AttributeValue::create([
            'attribute_id' => $color->id,
            'value' => 'Green',
        ]);

        $variant =
            ProductVariant::factory()->create([
                'product_id' =>
                    $product->id,
            ]);

        $variant
            ->attributeValues()
            ->attach($green->id);

        $first = ProductImage::create([
            'product_id' =>
                $product->id,

            'image_path' =>
                'first.jpg',

            'sort_order' =>
                0,

            'is_primary' =>
                true,
        ]);

        $second = ProductImage::create([
            'product_id' =>
                $product->id,

            'image_path' =>
                'second.jpg',

            'sort_order' =>
                1,

            'is_primary' =>
                false,
        ]);

        $colorImage =
            ProductImage::create([
                'product_id' =>
                    $product->id,

                'attribute_value_id' =>
                    $green->id,

                'image_path' =>
                    'green.jpg',

                'sort_order' =>
                    7,

                'is_primary' =>
                    true,
            ]);

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/products/{$product->id}/images/reorder",
                [
                    'image_ids' => [
                        $second->id,
                        $first->id,
                    ],
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'product_images',
            [
                'id' => $second->id,
                'sort_order' => 0,
            ]
        );

        $this->assertDatabaseHas(
            'product_images',
            [
                'id' => $first->id,
                'sort_order' => 1,
            ]
        );

        $this->assertDatabaseHas(
            'product_images',
            [
                'id' => $colorImage->id,
                'sort_order' => 7,
            ]
        );

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->postJson(
                "/api/admin/products/{$product->id}/images/reorder",
                [
                    'image_ids' => [
                        $first->id,
                    ],
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'image_ids',
            ]);
    }

    public function test_setting_primary_image_clears_previous_primary_in_same_scope(): void
    {
        $admin = $this->adminUser([
            'products.update',
        ]);

        $product =
            Product::factory()->create();

        $first = ProductImage::create([
            'product_id' =>
                $product->id,

            'image_path' =>
                'first.jpg',

            'sort_order' =>
                0,

            'is_primary' =>
                true,
        ]);

        $second = ProductImage::create([
            'product_id' =>
                $product->id,

            'image_path' =>
                'second.jpg',

            'sort_order' =>
                1,

            'is_primary' =>
                false,
        ]);

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->patchJson(
                "/api/admin/products/{$product->id}/images/{$second->id}",
                [
                    'is_primary' => true,
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'product_images',
            [
                'id' => $first->id,
                'is_primary' => false,
            ]
        );

        $this->assertDatabaseHas(
            'product_images',
            [
                'id' => $second->id,
                'is_primary' => true,
            ]
        );
    }

    public function test_admin_without_products_update_cannot_upload_media(): void
    {
        Storage::fake('public');

        $admin = $this->adminUser([
            'products.view',
        ]);

        $product =
            Product::factory()->create();

        $this
            ->actingAs(
                $admin,
                'sanctum'
            )
            ->post(
                "/api/admin/products/{$product->id}/images",
                [
                    'images' => [
                        $this->fakePng('blocked.png'),
                    ],
                ],
                [
                    'Accept' => 'application/json',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'product_images',
            0
        );
    }

    private function fakePng(
        string $name
    ): UploadedFile {
        $contents = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );

        if ($contents === false) {
            throw new \RuntimeException(
                'Unable to create fake PNG image.'
            );
        }

        return UploadedFile::fake()
            ->createWithContent(
                $name,
                $contents
            );
    }

    private function adminUser(
        array $permissions
    ): User {
        $user = User::factory()->create([
            'email' =>
                'media-admin-' .
                uniqid() .
                '@tkb.test',

            'status' =>
                UserStatus::ACTIVE,
        ]);

        $role = Role::firstOrCreate(
            [
                'slug' => 'admin',
            ],
            [
                'name' => 'Admin',
            ]
        );

        $permissionIds =
            collect($permissions)
                ->map(
                    fn (string $slug) =>
                        Permission::firstOrCreate(
                            [
                                'slug' => $slug,
                            ],
                            [
                                'name' => $slug,
                            ]
                        )->id
                )
                ->all();

        $role
            ->permissions()
            ->sync(
                $permissionIds
            );

        $user
            ->roles()
            ->sync([
                $role->id,
            ]);

        return $user;
    }
}
