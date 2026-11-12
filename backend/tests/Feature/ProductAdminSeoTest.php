<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAdminSeoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions): User
    {
        $admin = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $role = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Product SEO test admin role',
        ]);

        foreach ($permissions as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $slug,
                    'description' => 'SEO test permission',
                ]
            );

            $role->permissions()->syncWithoutDetaching([
                $permission->id,
            ]);
        }

        $admin->roles()->attach($role->id);

        return $admin;
    }

    private function category(): Category
    {
        return Category::factory()->create([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف آشپزی',
            ],
        ]);
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'category_id' => $this->category()->id,
            'name' => [
                'en' => 'Luxury Pan',
                'fa' => 'تابه لوکس',
            ],
            'slug' => 'seo-product-' . uniqid(),
            'price' => 150,
            'status' => ProductStatus::ACTIVE,
        ], $attributes));
    }

    private function seoProduct(
        array $attributes = []
    ): Product {
        return $this->product(array_merge([
            'seo_title' => [
                'en' => 'Luxury Pan SEO',
                'fa' => 'عنوان سئو تابه لوکس',
            ],
            'seo_description' => [
                'en' => 'Premium luxury cookware.',
                'fa' => 'خرید ظروف آشپزی لوکس.',
            ],
            'seo_image' => '/images/seo/pan.webp',
        ], $attributes));
    }

    private function translations(
        Product $product,
        string $field
    ): ?array {
        $product->refresh();

        $raw = $product->getRawOriginal($field);

        return $raw === null
            ? null
            : json_decode($raw, true);
    }

    public function test_admin_can_create_product_with_bilingual_seo(): void
    {
        $admin = $this->admin(['products.create']);
        $category = $this->category();

        $this
            ->actingAs($admin)
            ->postJson('/api/admin/products', [
                'category_id' => $category->id,
                'name' => [
                    'en' => 'Premium Pan',
                    'fa' => 'تابه ممتاز',
                ],
                'slug' => 'premium-pan-seo',
                'price' => 200,
                'seo_title' => [
                    'en' => 'Buy Premium Pan',
                    'fa' => 'خرید تابه ممتاز',
                ],
                'seo_description' => [
                    'en' => 'Discover premium pans.',
                    'fa' => 'خرید تابه‌های ممتاز.',
                ],
                'seo_image' => '/images/seo/premium.webp',
            ])
            ->assertCreated();

        $product = Product::where(
            'slug',
            'premium-pan-seo'
        )->firstOrFail();

        $this->assertSame(
            [
                'en' => 'Buy Premium Pan',
                'fa' => 'خرید تابه ممتاز',
            ],
            $this->translations($product, 'seo_title')
        );

        $this->assertSame(
            [
                'en' => 'Discover premium pans.',
                'fa' => 'خرید تابه‌های ممتاز.',
            ],
            $this->translations($product, 'seo_description')
        );

        $this->assertSame(
            '/images/seo/premium.webp',
            $product->refresh()->seo_image
        );
    }

    public function test_persian_update_preserves_english_seo(): void
    {
        $admin = $this->admin(['products.update']);
        $product = $this->seoProduct();

        $this
            ->actingAs($admin)
            ->putJson("/api/admin/products/{$product->id}", [
                'seo_title' => [
                    'fa' => 'عنوان فارسی جدید',
                ],
                'seo_description' => [
                    'fa' => 'توضیحات فارسی جدید',
                ],
            ])
            ->assertOk();

        $this->assertSame(
            [
                'en' => 'Luxury Pan SEO',
                'fa' => 'عنوان فارسی جدید',
            ],
            $this->translations($product, 'seo_title')
        );

        $this->assertSame(
            [
                'en' => 'Premium luxury cookware.',
                'fa' => 'توضیحات فارسی جدید',
            ],
            $this->translations($product, 'seo_description')
        );
    }

    public function test_english_update_preserves_persian_seo(): void
    {
        $admin = $this->admin(['products.update']);
        $product = $this->seoProduct();

        $this
            ->actingAs($admin)
            ->patchJson("/api/admin/products/{$product->id}", [
                'seo_title' => [
                    'en' => 'Updated English SEO',
                ],
            ])
            ->assertOk();

        $this->assertSame(
            [
                'en' => 'Updated English SEO',
                'fa' => 'عنوان سئو تابه لوکس',
            ],
            $this->translations($product, 'seo_title')
        );
    }

    public function test_admin_can_clear_all_seo_fields(): void
    {
        $admin = $this->admin(['products.update']);
        $product = $this->seoProduct();

        $this
            ->actingAs($admin)
            ->putJson("/api/admin/products/{$product->id}", [
                'seo_title' => null,
                'seo_description' => null,
                'seo_image' => null,
            ])
            ->assertOk();

        $product->refresh();

        $this->assertNull(
            $product->getRawOriginal('seo_title')
        );

        $this->assertNull(
            $product->getRawOriginal('seo_description')
        );

        $this->assertNull($product->seo_image);
    }

    public function test_omitted_seo_fields_remain_unchanged(): void
    {
        $admin = $this->admin(['products.update']);
        $product = $this->seoProduct();

        $title = $this->translations($product, 'seo_title');
        $description = $this->translations(
            $product,
            'seo_description'
        );

        $this
            ->actingAs($admin)
            ->putJson("/api/admin/products/{$product->id}", [
                'price' => 175,
            ])
            ->assertOk();

        $this->assertSame(
            $title,
            $this->translations($product, 'seo_title')
        );

        $this->assertSame(
            $description,
            $this->translations($product, 'seo_description')
        );

        $this->assertSame(
            '/images/seo/pan.webp',
            $product->refresh()->seo_image
        );

        $this->assertEquals(175, $product->price);
    }

    public function test_invalid_seo_payload_is_rejected(): void
    {
        $admin = $this->admin(['products.create']);
        $category = $this->category();

        $this
            ->actingAs($admin)
            ->postJson('/api/admin/products', [
                'category_id' => $category->id,
                'name' => [
                    'en' => 'Invalid SEO Product',
                    'fa' => 'محصول نامعتبر',
                ],
                'slug' => 'invalid-product-seo',
                'price' => 100,
                'seo_title' => [
                    'de' => 'Unsupported language',
                ],
                'seo_description' => [
                    'en' => str_repeat('X', 1001),
                ],
                'seo_image' => str_repeat('x', 2049),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'seo_title',
                'seo_description.en',
                'seo_image',
            ]);

        $this->assertDatabaseMissing('products', [
            'slug' => 'invalid-product-seo',
        ]);
    }

    public function test_public_api_returns_localized_seo(): void
    {
        $product = $this->seoProduct();

        $this
            ->withHeader('Accept-Language', 'en')
            ->getJson("/api/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath(
                'data.seo_title',
                'Luxury Pan SEO'
            )
            ->assertJsonPath(
                'data.seo_description',
                'Premium luxury cookware.'
            )
            ->assertJsonPath(
                'data.seo_image',
                '/images/seo/pan.webp'
            );

        $this
            ->withHeader('Accept-Language', 'fa')
            ->getJson("/api/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath(
                'data.seo_title',
                'عنوان سئو تابه لوکس'
            )
            ->assertJsonPath(
                'data.seo_description',
                'خرید ظروف آشپزی لوکس.'
            );
    }

    public function test_admin_can_read_both_seo_translations(): void
    {
        $admin = $this->admin(['products.view']);
        $product = $this->seoProduct();

        $this
            ->actingAs($admin)
            ->getJson("/api/admin/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath(
                'data.seo_title.en',
                'Luxury Pan SEO'
            )
            ->assertJsonPath(
                'data.seo_title.fa',
                'عنوان سئو تابه لوکس'
            )
            ->assertJsonPath(
                'data.seo_description.en',
                'Premium luxury cookware.'
            )
            ->assertJsonPath(
                'data.seo_description.fa',
                'خرید ظروف آشپزی لوکس.'
            );
    }

    public function test_user_without_update_permission_cannot_edit_seo(): void
    {
        $admin = $this->admin(['products.view']);
        $product = $this->seoProduct();

        $this
            ->actingAs($admin)
            ->putJson("/api/admin/products/{$product->id}", [
                'seo_title' => [
                    'en' => 'Unauthorized SEO',
                ],
            ])
            ->assertForbidden();

        $this->assertSame(
            'Luxury Pan SEO',
            $this->translations($product, 'seo_title')['en']
        );
    }

    public function test_seo_update_preserves_core_product_data(): void
    {
        $admin = $this->admin(['products.update']);
        $product = $this->seoProduct();

        $originalCategory = $product->category_id;
        $originalSlug = $product->slug;
        $originalPrice = $product->price;
        $originalName = json_decode(
            $product->getRawOriginal('name'),
            true
        );
        $originalStatus = $product->status;

        $this
            ->actingAs($admin)
            ->putJson("/api/admin/products/{$product->id}", [
                'seo_title' => [
                    'fa' => 'عنوان بهینه‌شده',
                ],
            ])
            ->assertOk();

        $product->refresh();

        $this->assertSame(
            $originalCategory,
            $product->category_id
        );

        $this->assertSame(
            $originalSlug,
            $product->slug
        );

        $this->assertEquals(
            $originalPrice,
            $product->price
        );

        $this->assertSame(
            $originalName,
            json_decode(
                $product->getRawOriginal('name'),
                true
            )
        );

        $this->assertEquals(
            $originalStatus,
            $product->status
        );
    }
}