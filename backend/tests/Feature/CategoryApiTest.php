<?php

namespace Tests\Feature;

use App\Enums\Locale;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    protected function setUp(): void
    {
        parent::setUp();

        $this->forgetCategoryTreeCache();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function forgetCategoryTreeCache(): void
    {
        Cache::store(config('cache.default'))->forget(
            'tkb:categories:tree:en'
        );

        Cache::store(config('cache.default'))->forget(
            'tkb:categories:tree:fa'
        );
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
                        'fa' => 'Ã˜Â¯Ã˜Â³Ã˜ÂªÃ™â€¡Ã¢â‚¬Å’Ã˜Â¨Ã™â€ Ã˜Â¯Ã›Å’ Ã˜ÂªÃ˜Â³Ã˜Âª',
                    ],

                    'slug' => 'test-category-' . uniqid(),

                    'description' => [
                        'en' => 'Test description',
                        'fa' => 'Ã˜ÂªÃ™Ë†Ã˜Â¶Ã›Å’Ã˜Â­Ã˜Â§Ã˜Âª Ã˜ÂªÃ˜Â³Ã˜Âª',
                    ],

                    'image' => null,

                    'is_active' => true,

                    'sort_order' => 0,

                    'created_by' => null,

                    'updated_by' => null,
                ],
                $attributes
            )
        );
    }

    private function categoryAdmin(
        array $permissions = [
            'categories.create',
            'categories.update',
            'categories.delete',
        ]
    ): User {
        $user = User::factory()->create([
            'status' =>
                UserStatus::ACTIVE,

            'email' =>
                'category-admin-'
                . uniqid()
                . '@tkb.test',
        ]);

        $role = Role::firstOrCreate(
            [
                'slug' => 'admin',
            ],
            [
                'name' => 'Admin',
            ]
        );

        $permissionIds = collect(
            $permissions
        )->map(
            fn (string $slug) =>
                Permission::firstOrCreate(
                    [
                        'slug' => $slug,
                    ],
                    [
                        'name' => Str::headline(
                            str_replace(
                                '.',
                                ' ',
                                $slug
                            )
                        ),
                    ]
                )->id
        );

        $role->permissions()->sync(
            $permissionIds
        );

        $user->roles()->sync([
            $role->id,
        ]);

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function test_public_category_index_returns_successfully(): void
    {
        $category = $this->createCategory([
            'slug' => 'cookware',
            'sort_order' => 1,
        ]);

        $response = $this->getJson(
            '/api/categories'
        );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'parent_id',
                        'locale',
                        'direction',
                        'name',
                        'slug',
                        'description',
                        'image',
                        'is_active',
                        'sort_order',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJsonPath(
                'data.0.id',
                $category->id
            );
    }

    public function test_public_category_index_returns_only_active_categories(): void
    {
        $active = $this->createCategory([
            'slug' => 'active-category',
            'is_active' => true,
        ]);

        $inactive = $this->createCategory([
            'slug' => 'inactive-category',
            'is_active' => false,
        ]);

        $response = $this->getJson(
            '/api/categories'
        );

        $response
            ->assertOk()
            ->assertJsonMissing([
                'id' => $inactive->id,
            ])
            ->assertJsonFragment([
                'id' => $active->id,
            ]);
    }

    public function test_public_category_index_is_ordered_by_sort_order(): void
    {
        $first = $this->createCategory([
            'slug' => 'first-category',
            'sort_order' => 1,
        ]);

        $second = $this->createCategory([
            'slug' => 'second-category',
            'sort_order' => 2,
        ]);

        $third = $this->createCategory([
            'slug' => 'third-category',
            'sort_order' => 3,
        ]);

        $response = $this->getJson(
            '/api/categories'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $first->id
            )
            ->assertJsonPath(
                'data.1.id',
                $second->id
            )
            ->assertJsonPath(
                'data.2.id',
                $third->id
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    */

    public function test_public_category_index_returns_english_translation(): void
    {
        $category = $this->createCategory([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'Ã˜Â¸Ã˜Â±Ã™Ë†Ã™ Ã˜Â¢Ã˜Â´Ã™Â¾Ã˜Â²Ã›Å’',
            ],
            'description' => [
                'en' => 'Luxury cookware',
                'fa' => 'Ã˜Â¸Ã˜Â±Ã™Ë†Ã™ Ã˜Â¢Ã˜Â´Ã™Â¾Ã˜Â²Ã›Å’ Ã™â€žÃ™Ë†ÃšÂ©Ã˜Â³',
            ],
        ]);

        $response = $this
            ->withHeader(
                'Accept-Language',
                Locale::EN->value
            )
            ->getJson('/api/categories');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $category->id
            )
            ->assertJsonPath(
                'data.0.locale',
                'en'
            )
            ->assertJsonPath(
                'data.0.direction',
                'ltr'
            )
            ->assertJsonPath(
                'data.0.name',
                'Cookware'
            )
            ->assertJsonPath(
                'data.0.description',
                'Luxury cookware'
            );
    }

    public function test_public_category_index_returns_persian_translation(): void
    {
        $category = $this->createCategory([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'Ã˜Â¸Ã˜Â±Ã™Ë†Ã™ Ã˜Â¢Ã˜Â´Ã™Â¾Ã˜Â²Ã›Å’',
            ],
            'description' => [
                'en' => 'Luxury cookware',
                'fa' => 'Ã˜Â¸Ã˜Â±Ã™Ë†Ã™ Ã˜Â¢Ã˜Â´Ã™Â¾Ã˜Â²Ã›Å’ Ã™â€žÃ™Ë†ÃšÂ©Ã˜Â³',
            ],
        ]);

        $response = $this
            ->withHeader(
                'Accept-Language',
                Locale::FA->value
            )
            ->getJson('/api/categories');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $category->id
            )
            ->assertJsonPath(
                'data.0.locale',
                'fa'
            )
            ->assertJsonPath(
                'data.0.direction',
                'rtl'
            )
            ->assertJsonPath(
                'data.0.name',
                'Ã˜Â¸Ã˜Â±Ã™Ë†Ã™ Ã˜Â¢Ã˜Â´Ã™Â¾Ã˜Â²Ã›Å’'
            )
            ->assertJsonPath(
                'data.0.description',
                'Ã˜Â¸Ã˜Â±Ã™Ë†Ã™ Ã˜Â¢Ã˜Â´Ã™Â¾Ã˜Â²Ã›Å’ Ã™â€žÃ™Ë†ÃšÂ©Ã˜Â³'
            );
    }

    public function test_public_category_index_falls_back_to_english_when_persian_is_missing(): void
    {
        $category = $this->createCategory([
            'name' => [
                'en' => 'Cookware',
            ],
            'description' => [
                'en' => 'Luxury cookware',
            ],
        ]);

        $response = $this
            ->withHeader(
                'Accept-Language',
                Locale::FA->value
            )
            ->getJson('/api/categories');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $category->id
            )
            ->assertJsonPath(
                'data.0.locale',
                'fa'
            )
            ->assertJsonPath(
                'data.0.direction',
                'rtl'
            )
            ->assertJsonPath(
                'data.0.name',
                'Cookware'
            )
            ->assertJsonPath(
                'data.0.description',
                'Luxury cookware'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Tree
    |--------------------------------------------------------------------------
    */

    public function test_public_category_tree_returns_active_root_categories(): void
    {
        $activeRoot = $this->createCategory([
            'slug' => 'active-root',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $inactiveRoot = $this->createCategory([
            'slug' => 'inactive-root',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $response = $this->getJson(
            '/api/categories/tree'
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $activeRoot->id,
            ])
            ->assertJsonMissing([
                'id' => $inactiveRoot->id,
            ]);
    }

    public function test_public_category_tree_returns_children_recursively(): void
    {
        $root = $this->createCategory([
            'name' => [
                'en' => 'Kitchen',
                'fa' => 'Ã˜Â¢Ã˜Â´Ã™Â¾Ã˜Â²Ã˜Â®Ã˜Â§Ã™â€ Ã™â€¡',
            ],
            'slug' => 'kitchen',
        ]);

        $child = $this->createCategory([
            'parent_id' => $root->id,
            'name' => [
                'en' => 'Cookware',
                'fa' => 'Ã˜Â¸Ã˜Â±Ã™Ë†Ã™ Ã˜Â¢Ã˜Â´Ã™Â¾Ã˜Â²Ã›Å’',
            ],
            'slug' => 'cookware',
        ]);

        $grandchild = $this->createCategory([
            'parent_id' => $child->id,
            'name' => [
                'en' => 'Pans',
                'fa' => 'Ã˜ÂªÃ˜Â§Ã˜Â¨Ã™â€¡Ã¢â‚¬Å’Ã™â€¡Ã˜Â§',
            ],
            'slug' => 'pans',
        ]);

        $response = $this->getJson(
            '/api/categories/tree'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $root->id
            )
            ->assertJsonPath(
                'data.0.children.0.id',
                $child->id
            )
            ->assertJsonPath(
                'data.0.children.0.children.0.id',
                $grandchild->id
            );
    }

    public function test_public_category_tree_returns_localized_recursive_children(): void
    {
        $root = $this->createCategory([
            'name' => [
                'en' => 'Kitchen',
                'fa' => 'Ã˜Â¢Ã˜Â´Ã™Â¾Ã˜Â²Ã˜Â®Ã˜Â§Ã™â€ Ã™â€¡',
            ],
            'slug' => 'kitchen',
        ]);

        $child = $this->createCategory([
            'parent_id' => $root->id,
            'name' => [
                'en' => 'Cookware',
                'fa' => 'Ã˜Â¸Ã˜Â±Ã™Ë†Ã™ Ã˜Â¢Ã˜Â´Ã™Â¾Ã˜Â²Ã›Å’',
            ],
            'slug' => 'cookware',
        ]);

        $response = $this
            ->withHeader(
                'Accept-Language',
                Locale::FA->value
            )
            ->getJson('/api/categories/tree');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.locale',
                'fa'
            )
            ->assertJsonPath(
                'data.0.direction',
                'rtl'
            )
            ->assertJsonPath(
                'data.0.name',
                'Ã˜Â¢Ã˜Â´Ã™Â¾Ã˜Â²Ã˜Â®Ã˜Â§Ã™â€ Ã™â€¡'
            )
            ->assertJsonPath(
                'data.0.children.0.locale',
                'fa'
            )
            ->assertJsonPath(
                'data.0.children.0.direction',
                'rtl'
            )
            ->assertJsonPath(
                'data.0.children.0.name',
                'Ã˜Â¸Ã˜Â±Ã™Ë†Ã™ Ã˜Â¢Ã˜Â´Ã™Â¾Ã˜Â²Ã›Å’'
            );
    }

    public function test_public_category_tree_should_not_expose_inactive_children(): void
    {
        $root = $this->createCategory([
            'slug' => 'kitchen',
            'is_active' => true,
        ]);

        $activeChild = $this->createCategory([
            'parent_id' => $root->id,
            'slug' => 'active-child',
            'is_active' => true,
        ]);

        $inactiveChild = $this->createCategory([
            'parent_id' => $root->id,
            'slug' => 'inactive-child',
            'is_active' => false,
        ]);

        $response = $this->getJson(
            '/api/categories/tree'
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $activeChild->id,
            ])
            ->assertJsonMissing([
                'id' => $inactiveChild->id,
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Locale-independent Fields
    |--------------------------------------------------------------------------
    */

    public function test_category_slug_is_not_translated(): void
    {
        $category = $this->createCategory([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'Ã˜Â¸Ã˜Â±Ã™Ë†Ã™ Ã˜Â¢Ã˜Â´Ã™Â¾Ã˜Â²Ã›Å’',
            ],
            'slug' => 'cookware',
        ]);

        $response = $this
            ->withHeader(
                'Accept-Language',
                Locale::FA->value
            )
            ->getJson('/api/categories');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.slug',
                'cookware'
            );
    }

    public function test_category_management_fields_are_locale_independent(): void
    {
        $category = $this->createCategory([
            'slug' => 'cookware',
            'is_active' => true,
            'sort_order' => 7,
        ]);

        $response = $this
            ->withHeader(
                'Accept-Language',
                Locale::FA->value
            )
            ->getJson('/api/categories');

        $response->assertOk();

        $responseCategory = collect(
            $response->json('data')
        )->firstWhere(
            'id',
            $category->id
        );

        $this->assertNotNull(
            $responseCategory
        );

        $this->assertSame(
            'cookware',
            $responseCategory['slug']
        );

        $this->assertTrue(
            $responseCategory['is_active']
        );

        $this->assertSame(
            7,
            $responseCategory['sort_order']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Activity Logging + Cache Invalidation
    |--------------------------------------------------------------------------
    */

    public function test_creating_category_creates_activity_log(): void
    {
        $user = $this->categoryAdmin();

        /*
         * Warm the public category tree cache first.
         */
        $this->getJson(
            '/api/categories/tree'
        )->assertOk();

        $this->assertTrue(
            Cache::store(config('cache.default'))->has(
                'tkb:categories:tree:en'
            )
        );

        Sanctum::actingAs($user);

        $response = $this->postJson(
            '/api/admin/categories',
            [
                'name' => [
                    'en' => 'Cookware',
                    'fa' => 'Cookware',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $response->assertSuccessful();

        /*
         * Creating a category must invalidate the cached tree.
         */
        $this->assertFalse(
            Cache::store(config('cache.default'))->has(
                'tkb:categories:tree:en'
            )
        );

        $categoryId = $response->json(
            'data.id'
        );

        $log = ActivityLog::query()
            ->where(
                'action',
                'categories.created'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull(
            $log
        );

        $this->assertSame(
            $user->id,
            $log->user_id
        );

        $this->assertSame(
            Category::class,
            $log->properties['subject']['type']
        );

        $this->assertSame(
            $categoryId,
            $log->properties['subject']['id']
        );

        $this->assertSame(
            $categoryId,
            $log->properties['category']['id']
        );
    }

    public function test_updating_category_creates_activity_log(): void
    {
        $user = $this->categoryAdmin();

        $category = $this->createCategory([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'Cookware',
            ],
        ]);

        /*
         * Warm the public category tree cache before the update.
         */
        $this->getJson(
            '/api/categories/tree'
        )->assertOk();

        $this->assertTrue(
            Cache::store(config('cache.default'))->has(
                'tkb:categories:tree:en'
            )
        );

        Sanctum::actingAs($user);

        $this->putJson(
            "/api/admin/categories/{$category->id}",
            [
                'name' => [
                    'en' => 'Luxury Cookware',
                ],
                'is_active' => false,
            ]
        )->assertSuccessful();

        /*
         * Updating a category must invalidate the cached tree.
         */
        $this->assertFalse(
            Cache::store(config('cache.default'))->has(
                'tkb:categories:tree:en'
            )
        );

        $log = ActivityLog::query()
            ->where(
                'action',
                'categories.updated'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull(
            $log
        );

        $this->assertSame(
            $user->id,
            $log->user_id
        );

        $this->assertSame(
            $category->id,
            $log->properties['subject']['id']
        );

        $this->assertContains(
            'name',
            $log->properties['changed_fields']
        );

        $this->assertContains(
            'is_active',
            $log->properties['changed_fields']
        );
    }

    public function test_deleting_category_creates_activity_log(): void
    {
        $user = $this->categoryAdmin();

        $category = $this->createCategory();

        /*
         * Warm the public category tree cache before deletion.
         */
        $this->getJson(
            '/api/categories/tree'
        )->assertOk();

        $this->assertTrue(
            Cache::store(config('cache.default'))->has(
                'tkb:categories:tree:en'
            )
        );

        Sanctum::actingAs($user);

        $this->deleteJson(
            "/api/admin/categories/{$category->id}"
        )->assertSuccessful();

        /*
         * Deleting a category must invalidate the cached tree.
         */
        $this->assertFalse(
            Cache::store(config('cache.default'))->has(
                'tkb:categories:tree:en'
            )
        );

        $log = ActivityLog::query()
            ->where(
                'action',
                'categories.deleted'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull(
            $log
        );

        $this->assertSame(
            $user->id,
            $log->user_id
        );

        $this->assertSame(
            Category::class,
            $log->properties['subject']['type']
        );

        $this->assertSame(
            $category->id,
            $log->properties['subject']['id']
        );

        $this->assertSame(
            $category->slug,
            $log->properties['category']['slug']
        );
    }
}