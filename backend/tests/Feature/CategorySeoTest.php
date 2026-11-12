<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySeoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $permission): User
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'Admin',
                'description' => 'Test admin role',
            ]
        );

        $user->roles()->syncWithoutDetaching([
            $role->id,
        ]);

        $permissionModel = Permission::firstOrCreate(
            ['slug' => $permission],
            [
                'name' => $permission,
                'description' => 'Test permission',
            ]
        );

        $role->permissions()->syncWithoutDetaching([
            $permissionModel->id,
        ]);

        return $user;
    }

    private function category(array $attributes = []): Category
    {
        return Category::create(array_merge([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف پخت‌وپز',
            ],
            'slug' => 'seo-category-test',
            'description' => [
                'en' => 'Cookware description',
                'fa' => 'توضیحات ظروف',
            ],
            'is_active' => true,
            'sort_order' => 0,
        ], $attributes));
    }

    private function translations(
        Category $category,
        string $field
    ): ?array {
        $category->refresh();

        $raw = $category->getRawOriginal($field);

        return $raw === null
            ? null
            : json_decode($raw, true);
    }

    public function test_admin_can_create_category_with_bilingual_seo(): void
    {
        $admin = $this->admin('categories.create');

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/categories', [
                'name' => [
                    'en' => 'Tableware',
                    'fa' => 'ظروف پذیرایی',
                ],
                'slug' => 'seo-tableware',
                'seo_title' => [
                    'en' => 'Luxury Tableware',
                    'fa' => 'ظروف پذیرایی لوکس',
                ],
                'seo_description' => [
                    'en' => 'Explore luxury tableware.',
                    'fa' => 'خرید ظروف پذیرایی لوکس.',
                ],
                'seo_image' => '/images/seo/tableware.webp',
            ]);

        $response->assertCreated();

        $category = Category::where(
            'slug',
            'seo-tableware'
        )->firstOrFail();

        $this->assertSame(
            [
                'en' => 'Luxury Tableware',
                'fa' => 'ظروف پذیرایی لوکس',
            ],
            $this->translations($category, 'seo_title')
        );

        $this->assertSame(
            [
                'en' => 'Explore luxury tableware.',
                'fa' => 'خرید ظروف پذیرایی لوکس.',
            ],
            $this->translations($category, 'seo_description')
        );

        $this->assertSame(
            '/images/seo/tableware.webp',
            $category->refresh()->seo_image
        );
    }

    public function test_partial_seo_update_preserves_english_translation(): void
    {
        $admin = $this->admin('categories.update');

        $category = $this->category([
            'seo_title' => [
                'en' => 'Original English SEO',
                'fa' => 'عنوان قدیمی',
            ],
            'seo_description' => [
                'en' => 'Original English Description',
                'fa' => 'توضیحات قدیمی',
            ],
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/categories/{$category->id}", [
                'seo_title' => [
                    'fa' => 'عنوان جدید فارسی',
                ],
                'seo_description' => [
                    'fa' => 'توضیحات جدید فارسی',
                ],
            ])
            ->assertOk();

        $this->assertSame(
            [
                'en' => 'Original English SEO',
                'fa' => 'عنوان جدید فارسی',
            ],
            $this->translations($category, 'seo_title')
        );

        $this->assertSame(
            [
                'en' => 'Original English Description',
                'fa' => 'توضیحات جدید فارسی',
            ],
            $this->translations($category, 'seo_description')
        );
    }

    public function test_admin_can_clear_seo_fields_with_null(): void
    {
        $admin = $this->admin('categories.update');

        $category = $this->category([
            'seo_title' => [
                'en' => 'Old SEO Title',
                'fa' => 'عنوان قدیمی',
            ],
            'seo_description' => [
                'en' => 'Old SEO Description',
                'fa' => 'توضیحات قدیمی',
            ],
            'seo_image' => '/images/seo/old.webp',
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/categories/{$category->id}", [
                'seo_title' => null,
                'seo_description' => null,
                'seo_image' => null,
            ])
            ->assertOk();

        $category->refresh();

        $this->assertNull(
            $category->getRawOriginal('seo_title')
        );

        $this->assertNull(
            $category->getRawOriginal('seo_description')
        );

        $this->assertNull($category->seo_image);
    }

    public function test_admin_rejects_invalid_seo_translations(): void
    {
        $admin = $this->admin('categories.create');

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/categories', [
                'name' => [
                    'en' => 'Invalid SEO Category',
                    'fa' => 'دسته‌بندی نامعتبر',
                ],
                'slug' => 'invalid-seo-category',
                'seo_title' => [
                    'de' => 'Unexpected locale',
                ],
                'seo_description' => [
                    'en' => str_repeat('A', 1001),
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'seo_title',
                'seo_description.en',
            ]);
    }

    public function test_public_api_returns_localized_category_seo(): void
    {
        $category = $this->category([
            'seo_title' => [
                'en' => 'English SEO Category',
                'fa' => 'عنوان سئو فارسی',
            ],
            'seo_description' => [
                'en' => 'English SEO Description',
                'fa' => 'توضیحات سئو فارسی',
            ],
            'seo_image' => '/images/seo/category.webp',
        ]);

        $this
            ->withHeader('Accept-Language', 'en')
            ->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath(
                'data.0.slug',
                $category->slug
            )
            ->assertJsonPath(
                'data.0.seo_title',
                'English SEO Category'
            )
            ->assertJsonPath(
                'data.0.seo_description',
                'English SEO Description'
            );

        $this
            ->withHeader('Accept-Language', 'fa')
            ->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath(
                'data.0.slug',
                $category->slug
            )
            ->assertJsonPath(
                'data.0.seo_title',
                'عنوان سئو فارسی'
            )
            ->assertJsonPath(
                'data.0.seo_description',
                'توضیحات سئو فارسی'
            )
            ->assertJsonPath(
                'data.0.seo_image',
                '/images/seo/category.webp'
            );
    }
}