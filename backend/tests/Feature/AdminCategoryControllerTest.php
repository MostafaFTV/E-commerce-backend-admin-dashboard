<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryControllerTest extends TestCase
{
    use RefreshDatabase;


    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function test_user_with_categories_view_permission_can_view_categories(): void
    {
        $admin = $this->createAdminWithPermission(
            'categories.view'
        );

        $category = $this->createCategory([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف پخت‌وپز',
            ],
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/categories');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $category->id
            )
            ->assertJsonPath(
                'data.0.name',
                'Cookware'
            )
            ->assertJsonPath(
                'data.0.slug',
                $category->slug
            );
    }


    public function test_user_without_categories_view_permission_cannot_view_categories(): void
    {
        $admin = $this->createAdmin();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/categories');

        $response->assertForbidden();
    }


    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_search_categories_by_english_name(): void
    {
        $admin = $this->createAdminWithPermission(
            'categories.view'
        );

        $matchingCategory = $this->createCategory([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف پخت‌وپز',
            ],
            'slug' => 'cookware',
        ]);

        $this->createCategory([
            'name' => [
                'en' => 'Tableware',
                'fa' => 'ظروف پذیرایی',
            ],
            'slug' => 'tableware',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/categories?search=Cookware'
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $matchingCategory->id
            );
    }


    public function test_admin_can_search_categories_by_persian_name(): void
    {
        $admin = $this->createAdminWithPermission(
            'categories.view'
        );

        $matchingCategory = $this->createCategory([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف پخت‌وپز',
            ],
            'slug' => 'cookware',
        ]);

        $this->createCategory([
            'name' => [
                'en' => 'Tableware',
                'fa' => 'ظروف پذیرایی',
            ],
            'slug' => 'tableware',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/categories?search=' .
                urlencode('ظروف پخت‌وپز')
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $matchingCategory->id
            );
    }


    public function test_admin_can_search_categories_by_slug(): void
    {
        $admin = $this->createAdminWithPermission(
            'categories.view'
        );

        $matchingCategory = $this->createCategory([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف پخت‌وپز',
            ],
            'slug' => 'cookware',
        ]);

        $this->createCategory([
            'name' => [
                'en' => 'Tableware',
                'fa' => 'ظروف پذیرایی',
            ],
            'slug' => 'tableware',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/categories?search=cookware'
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $matchingCategory->id
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Tree
    |--------------------------------------------------------------------------
    */

    public function test_user_with_categories_view_permission_can_view_category_tree(): void
    {
        $admin = $this->createAdminWithPermission(
            'categories.view'
        );

        $parent = $this->createCategory([
            'name' => [
                'en' => 'Kitchen',
                'fa' => 'آشپزخانه',
            ],
            'slug' => 'kitchen',
            'sort_order' => 1,
        ]);

        $child = $this->createCategory([
            'parent_id' => $parent->id,
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف پخت‌وپز',
            ],
            'slug' => 'cookware',
            'sort_order' => 1,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/categories/tree');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $parent->id
            )
            ->assertJsonPath(
                'data.0.name',
                'Kitchen'
            )
            ->assertJsonPath(
                'data.0.children.0.id',
                $child->id
            )
            ->assertJsonPath(
                'data.0.children.0.name',
                'Cookware'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_bilingual_category(): void
    {
        $admin = $this->createAdminWithPermission(
            'categories.create'
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/categories',
                [
                    'name' => [
                        'en' => 'Cookware',
                        'fa' => 'ظروف پخت‌وپز',
                    ],
                    'description' => [
                        'en' => 'Luxury cookware',
                        'fa' => 'ظروف پخت‌وپز لوکس',
                    ],
                    'slug' => 'cookware',
                    'sort_order' => 5,
                    'is_active' => true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.name',
                'Cookware'
            )
            ->assertJsonPath(
                'data.description',
                'Luxury cookware'
            )
            ->assertJsonPath(
                'data.slug',
                'cookware'
            );

        $this->assertDatabaseHas(
            'categories',
            [
                'slug' => 'cookware',
            ]
        );

        $category = Category::query()
            ->where('slug', 'cookware')
            ->firstOrFail();

        $translations = json_decode(
            $category->getRawOriginal('name'),
            true
        );

        $this->assertSame(
            'Cookware',
            $translations['en']
        );

        $this->assertSame(
            'ظروف پخت‌وپز',
            $translations['fa']
        );
    }


    public function test_category_create_requires_english_and_persian_names(): void
    {
        $admin = $this->createAdminWithPermission(
            'categories.create'
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/categories',
                [
                    'name' => [
                        'en' => 'Cookware',
                    ],
                    'description' => [
                        'en' => 'Luxury cookware',
                    ],
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name.fa',
            ]);
    }


    public function test_category_slug_is_generated_from_english_name(): void
    {
        $admin = $this->createAdminWithPermission(
            'categories.create'
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/categories',
                [
                    'name' => [
                        'en' => 'Luxury Cookware',
                        'fa' => 'ظروف پخت‌وپز لوکس',
                    ],
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.slug',
                'luxury-cookware'
            );

        $this->assertDatabaseHas(
            'categories',
            [
                'slug' => 'luxury-cookware',
            ]
        );
    }


    public function test_duplicate_category_slug_is_rejected(): void
    {
        $admin = $this->createAdminWithPermission(
            'categories.create'
        );

        $this->createCategory([
            'slug' => 'cookware',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/categories',
                [
                    'name' => [
                        'en' => 'Another Cookware',
                        'fa' => 'ظروف پخت‌وپز دیگر',
                    ],
                    'slug' => 'cookware',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_view_category_details(): void
    {
        $admin = $this->createAdminWithPermission(
            'categories.view'
        );

        $category = $this->createCategory([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف پخت‌وپز',
            ],
            'description' => [
                'en' => 'Luxury cookware',
                'fa' => 'ظروف پخت‌وپز لوکس',
            ],
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->withHeader(
                'Accept-Language',
                'fa'
            )
            ->getJson(
                "/api/admin/categories/{$category->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'ظروف پخت‌وپز'
            )
            ->assertJsonPath(
                'data.description',
                'ظروف پخت‌وپز لوکس'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_update_both_category_translations(): void
    {
        $admin = $this->createAdminWithPermission(
            'categories.update'
        );

        $category = $this->createCategory([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف پخت‌وپز',
            ],
            'description' => [
                'en' => 'Luxury cookware',
                'fa' => 'ظروف پخت‌وپز لوکس',
            ],
            'slug' => 'cookware',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/categories/{$category->id}",
                [
                    'name' => [
                        'en' => 'Premium Cookware',
                        'fa' => 'ظروف پخت‌وپز ممتاز',
                    ],
                    'description' => [
                        'en' => 'Premium cookware collection',
                        'fa' => 'مجموعه ظروف پخت‌وپز ممتاز',
                    ],
                    'slug' => 'premium-cookware',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Premium Cookware'
            )
            ->assertJsonPath(
                'data.description',
                'Premium cookware collection'
            )
            ->assertJsonPath(
                'data.slug',
                'premium-cookware'
            );

        $fresh = $category->fresh();

        $nameTranslations = json_decode(
            $fresh->getRawOriginal('name'),
            true
        );

        $descriptionTranslations = json_decode(
            $fresh->getRawOriginal('description'),
            true
        );

        $this->assertSame(
            'Premium Cookware',
            $nameTranslations['en']
        );

        $this->assertSame(
            'ظروف پخت‌وپز ممتاز',
            $nameTranslations['fa']
        );

        $this->assertSame(
            'Premium cookware collection',
            $descriptionTranslations['en']
        );

        $this->assertSame(
            'مجموعه ظروف پخت‌وپز ممتاز',
            $descriptionTranslations['fa']
        );
    }


    public function test_partial_name_translation_update_preserves_existing_translation(): void
    {
        $admin = $this->createAdminWithPermission(
            'categories.update'
        );

        $category = $this->createCategory([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف پخت‌وپز',
            ],
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/categories/{$category->id}",
                [
                    'name' => [
                        'fa' => 'ظروف پخت‌وپز مدرن',
                    ],
                ]
            );

        $response->assertOk();

        $fresh = $category->fresh();

        $translations = json_decode(
            $fresh->getRawOriginal('name'),
            true
        );

        $this->assertSame(
            'Cookware',
            $translations['en']
        );

        $this->assertSame(
            'ظروف پخت‌وپز مدرن',
            $translations['fa']
        );
    }


    public function test_partial_description_translation_update_preserves_existing_translation(): void
    {
        $admin = $this->createAdminWithPermission(
            'categories.update'
        );

        $category = $this->createCategory([
            'description' => [
                'en' => 'Luxury cookware',
                'fa' => 'ظروف پخت‌وپز لوکس',
            ],
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/categories/{$category->id}",
                [
                    'description' => [
                        'fa' => 'مجموعه لوکس ظروف پخت‌وپز',
                    ],
                ]
            );

        $response->assertOk();

        $fresh = $category->fresh();

        $translations = json_decode(
            $fresh->getRawOriginal('description'),
            true
        );

        $this->assertSame(
            'Luxury cookware',
            $translations['en']
        );

        $this->assertSame(
            'مجموعه لوکس ظروف پخت‌وپز',
            $translations['fa']
        );
    }


    public function test_changing_name_without_slug_regenerates_slug_from_english_translation(): void
    {
        $admin = $this->createAdminWithPermission(
            'categories.update'
        );

        $category = $this->createCategory([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف پخت‌وپز',
            ],
            'slug' => 'cookware',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/categories/{$category->id}",
                [
                    'name' => [
                        'en' => 'Premium Cookware',
                        'fa' => 'ظروف پخت‌وپز ممتاز',
                    ],
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.slug',
                'premium-cookware'
            );
    }


    public function test_partial_persian_name_update_does_not_destroy_english_slug_source(): void
    {
        $admin = $this->createAdminWithPermission(
            'categories.update'
        );

        $category = $this->createCategory([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف پخت‌وپز',
            ],
            'slug' => 'cookware',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/categories/{$category->id}",
                [
                    'name' => [
                        'fa' => 'ظروف پخت‌وپز مدرن',
                    ],
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.slug',
                'cookware'
            );

        $fresh = $category->fresh();

        $translations = json_decode(
            $fresh->getRawOriginal('name'),
            true
        );

        $this->assertSame(
            'Cookware',
            $translations['en']
        );

        $this->assertSame(
            'ظروف پخت‌وپز مدرن',
            $translations['fa']
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Parent Validation
    |--------------------------------------------------------------------------
    */

    public function test_category_cannot_be_its_own_parent(): void
    {
        $admin = $this->createAdminWithPermission(
            'categories.update'
        );

        $category = $this->createCategory();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/categories/{$category->id}",
                [
                    'parent_id' => $category->id,
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'parent_id',
            ]);
    }


    public function test_category_can_be_assigned_to_valid_parent(): void
    {
        $admin = $this->createAdminWithPermission(
            'categories.update'
        );

        $parent = $this->createCategory();

        $category = $this->createCategory([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف پخت‌وپز',
            ],
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/categories/{$category->id}",
                [
                    'parent_id' => $parent->id,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.parent_id',
                $parent->id
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_delete_empty_category(): void
    {
        $admin = $this->createAdminWithPermission(
            'categories.delete'
        );

        $category = $this->createCategory();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->deleteJson(
                "/api/admin/categories/{$category->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Category deleted successfully'
            );

        $this->assertDatabaseMissing(
            'categories',
            [
                'id' => $category->id,
            ]
        );
    }


    public function test_category_with_children_cannot_be_deleted(): void
    {
        $admin = $this->createAdminWithPermission(
            'categories.delete'
        );

        $parent = $this->createCategory();

        $this->createCategory([
            'parent_id' => $parent->id,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->deleteJson(
                "/api/admin/categories/{$parent->id}"
            );

        $response
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Cannot delete category with children'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_user_without_categories_create_permission_cannot_create_category(): void
    {
        $admin = $this->createAdmin();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/categories',
                [
                    'name' => [
                        'en' => 'Unauthorized',
                        'fa' => 'غیرمجاز',
                    ],
                ]
            );

        $response->assertForbidden();
    }


    public function test_guest_cannot_access_admin_categories(): void
    {
        $response = $this->getJson(
            '/api/admin/categories'
        );

        $response->assertUnauthorized();
    }


    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createAdminWithPermission(
        string $permissionSlug
    ): User {
        $admin = $this->createAdmin();

        $permission = Permission::firstOrCreate(
            [
                'slug' => $permissionSlug,
            ],
            [
                'name' => $permissionSlug,
                'description' => 'Test permission',
            ]
        );

        $role = $admin->roles()->firstOrFail();

        $role->permissions()->syncWithoutDetaching([
            $permission->id,
        ]);

        return $admin;
    }


    private function createAdmin(): User
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $role = Role::firstOrCreate(
            [
                'slug' => 'admin',
            ],
            [
                'name' => 'Admin',
                'description' => 'Test admin role',
            ]
        );

        $user->roles()->syncWithoutDetaching([
            $role->id,
        ]);

        return $user;
    }


    private function createCategory(
        array $attributes = []
    ): Category {
        return Category::create(
            array_merge(
                [
                    'parent_id' => null,

                    'name' => [
                        'en' => 'Test Category',
                        'fa' => 'دسته‌بندی تست',
                    ],

                    'slug' => 'test-category-' . uniqid(),

                    'description' => [
                        'en' => 'Test description',
                        'fa' => 'توضیحات تست',
                    ],

                    'image' => null,

                    'sort_order' => 0,

                    'is_active' => true,

                    'created_by' => null,

                    'updated_by' => null,
                ],
                $attributes
            )
        );
    }
}
