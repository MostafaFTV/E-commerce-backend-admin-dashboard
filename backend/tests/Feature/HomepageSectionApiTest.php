<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Collection;
use App\Models\HomepageSection;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageSectionApiTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Basic CRUD
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_list_homepage_sections(): void
    {
        $admin = $this->adminUser();

        HomepageSection::create([
            'section_key' => 'hero',
            'title' => [
                'en' => 'Hero',
                'fa' => 'هیرو',
            ],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/homepage-sections')
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.section_key',
                'hero'
            )
            ->assertJsonPath(
                'data.0.title',
                'Hero'
            );
    }

    public function test_admin_can_create_homepage_section(): void
    {
        $admin = $this->adminUser();

        $category = Category::factory()->create([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف پخت و پز',
            ],
        ]);

        $response = $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'featured_categories',

                    'title' => [
                        'en' =>
                            'Featured Categories',
                        'fa' =>
                            'دسته‌بندی‌های ویژه',
                    ],

                    'subtitle' => [
                        'en' =>
                            'Explore our curated categories',
                        'fa' =>
                            'دسته‌بندی‌های منتخب ما را ببینید',
                    ],

                    'content' => [
                        'en' =>
                            'Discover our selected categories.',
                        'fa' =>
                            'دسته‌بندی‌های منتخب ما را کشف کنید.',
                    ],

                    'image' =>
                        '/storage/homepage/categories.webp',

                    'mobile_image' =>
                        '/storage/homepage/categories-mobile.webp',

                    'link' =>
                        '/categories',

                    'button_text' => [
                        'en' => 'Explore',
                        'fa' => 'مشاهده',
                    ],

                    'category_id' =>
                        $category->id,

                    'is_active' => true,

                    'sort_order' => 1,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.section_key',
                'featured_categories'
            )
            ->assertJsonPath(
                'data.title',
                'Featured Categories'
            )
            ->assertJsonPath(
                'data.category.id',
                $category->id
            )
            ->assertJsonPath(
                'data.category.name',
                'Cookware'
            );

        $this->assertDatabaseHas(
            'homepage_sections',
            [
                'id' =>
                    $response->json(
                        'data.id'
                    ),

                'section_key' =>
                    'featured_categories',

                'category_id' =>
                    $category->id,

                'is_active' => 1,

                'sort_order' => 1,
            ]
        );
    }

    public function test_admin_can_show_homepage_section(): void
    {
        $admin = $this->adminUser();

        $category = Category::factory()->create([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف پخت و پز',
            ],
        ]);

        $section = HomepageSection::create([
            'section_key' =>
                'featured_categories',

            'title' => [
                'en' => 'Featured Categories',
                'fa' => 'دسته‌بندی‌های ویژه',
            ],

            'category_id' =>
                $category->id,

            'is_active' => true,

            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->getJson(
                "/api/admin/homepage-sections/{$section->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $section->id
            )
            ->assertJsonPath(
                'data.section_key',
                'featured_categories'
            )
            ->assertJsonPath(
                'data.category.id',
                $category->id
            );
    }

    public function test_admin_can_update_homepage_section_and_merge_localized_values(): void
    {
        $admin = $this->adminUser();

        $section = HomepageSection::create([
            'section_key' => 'hero',

            'title' => [
                'en' => 'Hero',
                'fa' => 'هیرو',
            ],

            'subtitle' => [
                'en' =>
                    'Original subtitle',
                'fa' =>
                    'زیرعنوان اصلی',
            ],

            'content' => [
                'en' =>
                    'Original content',
                'fa' =>
                    'محتوای اصلی',
            ],

            'is_active' => true,

            'sort_order' => 0,
        ]);

        $response = $this->actingAs($admin)
            ->patchJson(
                "/api/admin/homepage-sections/{$section->id}",
                [
                    'title' => [
                        'fa' =>
                            'هیروی جدید',
                    ],

                    'sort_order' => 2,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.title',
                'Hero'
            )
            ->assertJsonPath(
                'data.sort_order',
                2
            );

        $stored = $section->fresh();

        $translations = json_decode(
            $stored->getRawOriginal('title'),
            true
        );

        $this->assertSame(
            'Hero',
            $translations['en']
        );

        $this->assertSame(
            'هیروی جدید',
            $translations['fa']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Section Key
    |--------------------------------------------------------------------------
    */

    public function test_section_key_is_immutable_on_update(): void
    {
        $admin = $this->adminUser();

        $section = HomepageSection::create([
            'section_key' => 'hero',

            'title' => [
                'en' => 'Hero',
                'fa' => 'هیرو',
            ],

            'is_active' => true,

            'sort_order' => 0,
        ]);

        $this->actingAs($admin)
            ->patchJson(
                "/api/admin/homepage-sections/{$section->id}",
                [
                    'section_key' => 'new_key',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'section_key',
            ]);

        $this->assertDatabaseHas(
            'homepage_sections',
            [
                'id' =>
                    $section->id,

                'section_key' =>
                    'hero',
            ]
        );
    }

    public function test_admin_rejects_duplicate_section_key(): void
    {
        $admin = $this->adminUser();

        HomepageSection::create([
            'section_key' => 'hero',

            'title' => [
                'en' => 'Hero',
                'fa' => 'هیرو',
            ],

            'is_active' => true,

            'sort_order' => 0,
        ]);

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'hero',

                    'title' => [
                        'en' =>
                            'Another Hero',
                        'fa' =>
                            'هیروی دیگر',
                    ],

                    'is_active' => true,

                    'sort_order' => 1,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'section_key',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function test_homepage_section_can_reference_a_category(): void
    {
        $admin = $this->adminUser();

        $category = Category::factory()->create([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف پخت و پز',
            ],
        ]);

        $response = $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'category_highlight',

                    'title' => [
                        'en' =>
                            'Category Highlight',
                        'fa' =>
                            'دسته‌بندی منتخب',
                    ],

                    'category_id' =>
                        $category->id,

                    'is_active' => true,

                    'sort_order' => 1,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.category.id',
                $category->id
            )
            ->assertJsonPath(
                'data.category.name',
                'Cookware'
            );
    }

    public function test_homepage_section_can_reference_a_product(): void
    {
        $admin = $this->adminUser();

        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,

            'name' => [
                'en' => 'Kitchen Pan',
                'fa' => 'تابه آشپزخانه',
            ],
        ]);

        $response = $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'product_highlight',

                    'title' => [
                        'en' =>
                            'Product Highlight',
                        'fa' =>
                            'محصول منتخب',
                    ],

                    'product_id' =>
                        $product->id,

                    'is_active' => true,

                    'sort_order' => 2,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.product.id',
                $product->id
            )
            ->assertJsonPath(
                'data.product.slug',
                $product->slug
            );
    }

    public function test_homepage_section_can_reference_a_collection(): void
    {
        $admin = $this->adminUser();

        $collection = Collection::create([
            'name' => [
                'en' => 'Featured',
                'fa' => 'منتخب',
            ],

            'slug' =>
                'featured',

            'is_active' =>
                true,

            'sort_order' =>
                0,
        ]);

        $response = $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'collection_highlight',

                    'title' => [
                        'en' =>
                            'Collection Highlight',
                        'fa' =>
                            'کالکشن منتخب',
                    ],

                    'collection_id' =>
                        $collection->id,

                    'is_active' => true,

                    'sort_order' => 3,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.collection.id',
                $collection->id
            )
            ->assertJsonPath(
                'data.collection.slug',
                'featured'
            );
    }

    public function test_admin_rejects_multiple_target_relations(): void
    {
        $admin = $this->adminUser();

        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' =>
                $category->id,
        ]);

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'invalid_section',

                    'title' => [
                        'en' =>
                            'Invalid Section',
                        'fa' =>
                            'بخش نامعتبر',
                    ],

                    'category_id' =>
                        $category->id,

                    'product_id' =>
                        $product->id,

                    'is_active' => true,

                    'sort_order' => 10,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'section',
            ]);
    }

    public function test_admin_rejects_invalid_relation_ids(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'invalid_relation',

                    'title' => [
                        'en' =>
                            'Invalid Relation',
                        'fa' =>
                            'ارتباط نامعتبر',
                    ],

                    'category_id' =>
                        999999,

                    'is_active' => true,

                    'sort_order' => 1,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'category_id',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Public API
    |--------------------------------------------------------------------------
    */

    public function test_public_api_returns_only_active_homepage_sections(): void
    {
        HomepageSection::create([
            'section_key' => 'hero',

            'title' => [
                'en' => 'Hero',
                'fa' => 'هیرو',
            ],

            'is_active' => true,

            'sort_order' => 0,
        ]);

        HomepageSection::create([
            'section_key' =>
                'hidden_section',

            'title' => [
                'en' =>
                    'Hidden Section',
                'fa' =>
                    'بخش مخفی',
            ],

            'is_active' => false,

            'sort_order' => 1,
        ]);

        $this->getJson(
            '/api/homepage-sections'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.section_key',
                'hero'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_delete_homepage_section(): void
    {
        $admin = $this->adminUser();

        $section = HomepageSection::create([
            'section_key' =>
                'temporary_section',

            'title' => [
                'en' =>
                    'Temporary',
                'fa' =>
                    'موقت',
            ],

            'is_active' => true,

            'sort_order' => 10,
        ]);

        $this->actingAs($admin)
            ->deleteJson(
                "/api/admin/homepage-sections/{$section->id}"
            )
            ->assertOk()
            ->assertJson([
                'message' =>
                    'Homepage section deleted successfully.',
            ]);

        $this->assertDatabaseMissing(
            'homepage_sections',
            [
                'id' =>
                    $section->id,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_admin_requires_cms_permission(): void
    {
        $user = User::factory()->create([
            'status' =>
                UserStatus::ACTIVE,
        ]);

        $this->actingAs($user)
            ->getJson(
                '/api/admin/homepage-sections'
            )
            ->assertForbidden();
    }

    public function test_guest_cannot_access_admin_homepage_sections(): void
    {
        $this->getJson(
            '/api/admin/homepage-sections'
        )
            ->assertUnauthorized();
    }

    public function test_public_homepage_sections_do_not_require_authentication(): void
    {
        $this->getJson(
            '/api/homepage-sections'
        )
            ->assertOk();
    }

    public function test_admin_without_permission_cannot_create_homepage_section(): void
    {
        $user = User::factory()->create([
            'status' =>
                UserStatus::ACTIVE,
        ]);

        $this->actingAs($user)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'test_section',

                    'is_active' =>
                        true,
                ]
            )
            ->assertForbidden();
    }

    public function test_admin_without_permission_cannot_update_homepage_section(): void
    {
        $user = User::factory()->create([
            'status' =>
                UserStatus::ACTIVE,
        ]);

        $section = HomepageSection::create([
            'section_key' =>
                'hero',

            'title' => [
                'en' =>
                    'Hero',
            ],

            'is_active' =>
                true,
        ]);

        $this->actingAs($user)
            ->patchJson(
                "/api/admin/homepage-sections/{$section->id}",
                [
                    'sort_order' =>
                        10,
                ]
            )
            ->assertForbidden();
    }

    public function test_admin_without_permission_cannot_delete_homepage_section(): void
    {
        $user = User::factory()->create([
            'status' =>
                UserStatus::ACTIVE,
        ]);

        $section = HomepageSection::create([
            'section_key' =>
                'hero',

            'title' => [
                'en' =>
                    'Hero',
            ],

            'is_active' =>
                true,
        ]);

        $this->actingAs($user)
            ->deleteJson(
                "/api/admin/homepage-sections/{$section->id}"
            )
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Mass Assignment
    |--------------------------------------------------------------------------
    */

    public function test_homepage_section_store_ignores_unvalidated_sensitive_fields(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'mass_assignment_test',

                    'title' => [
                        'en' =>
                            'Safe Section',
                    ],

                    'is_active' =>
                        true,

                    'sort_order' =>
                        1,

                    'id' =>
                        9999,

                    'created_at' =>
                        '2000-01-01 00:00:00',

                    'updated_at' =>
                        '2000-01-01 00:00:00',
                ]
            );

        $response
            ->assertCreated();

        $section = HomepageSection::query()
            ->latest('id')
            ->firstOrFail();

        $this->assertNotSame(
            9999,
            $section->id
        );

        $this->assertNotSame(
            '2000-01-01 00:00:00',
            $section->created_at
                ?->format(
                    'Y-m-d H:i:s'
                )
        );
    }

    public function test_homepage_section_update_ignores_unvalidated_sensitive_fields(): void
    {
        $admin = $this->adminUser();

        $section = HomepageSection::create([
            'section_key' =>
                'mass_assignment_update',

            'title' => [
                'en' =>
                    'Original',
            ],

            'is_active' =>
                true,
        ]);

        $originalCreatedAt =
            $section->created_at
                ?->format(
                    'Y-m-d H:i:s'
                );

        $this->actingAs($admin)
            ->patchJson(
                "/api/admin/homepage-sections/{$section->id}",
                [
                    'sort_order' =>
                        5,

                    'id' =>
                        9999,

                    'section_key' =>
                        'changed_key',

                    'created_at' =>
                        '2000-01-01 00:00:00',

                    'updated_at' =>
                        '2000-01-01 00:00:00',
                ]
            )
            ->assertUnprocessable();

        $updated = $section->fresh();

        $this->assertSame(
            $section->id,
            $updated->id
        );

        $this->assertSame(
            $originalCreatedAt,
            $updated->created_at
                ?->format(
                    'Y-m-d H:i:s'
                )
        );

        $this->assertSame(
            'mass_assignment_update',
            $updated->section_key
        );
    }

    /*
    |--------------------------------------------------------------------------
    | XSS - Plain Text Fields
    |--------------------------------------------------------------------------
    */

    public function test_homepage_title_removes_script_tags(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'title_xss_test',

                    'title' => [
                        'en' =>
                            '<script>alert("xss")</script>Hero',
                    ],

                    'is_active' =>
                        true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.title',
                'Hero'
            );

        $section = HomepageSection::query()
            ->firstOrFail();

        $this->assertSame(
            'Hero',
            $this->decodeTranslation(
                $section->getRawOriginal(
                    'title'
                ),
                'en'
            )
        );
    }

    public function test_homepage_subtitle_removes_event_handler_markup(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'subtitle_xss_test',

                    'title' => [
                        'en' =>
                            'Safe',
                    ],

                    'subtitle' => [
                        'en' =>
                            '<img src="x" onerror="alert(1)">Discover',
                    ],

                    'is_active' =>
                        true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.subtitle',
                'Discover'
            );
    }

    public function test_homepage_button_text_is_sanitized(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'button_xss_test',

                    'title' => [
                        'en' =>
                            'Summer',
                    ],

                    'button_text' => [
                        'en' =>
                            '<b>Shop Now</b><script>alert(1)</script>',
                    ],

                    'is_active' =>
                        true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.button_text',
                'Shop Now'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | XSS - Rich Content
    |--------------------------------------------------------------------------
    */

    public function test_homepage_content_removes_script_tags(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'content_xss_test',

                    'title' => [
                        'en' =>
                            'Content Test',
                    ],

                    'content' => [
                        'en' =>
                            '<p>Safe content</p><script>alert(1)</script>',
                    ],

                    'is_active' =>
                        true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.content',
                '<p>Safe content</p>'
            );

        $section = HomepageSection::query()
            ->firstOrFail();

        $stored =
            $this->decodeTranslation(
                $section->getRawOriginal(
                    'content'
                ),
                'en'
            );

        $this->assertSame(
            '<p>Safe content</p>',
            $stored
        );
    }

    public function test_homepage_content_removes_event_handler_attributes(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'content_event_test',

                    'title' => [
                        'en' =>
                            'Content Test',
                    ],

                    'content' => [
                        'en' =>
                            '<p onclick="alert(1)">Safe content</p>',
                    ],

                    'is_active' =>
                        true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.content',
                '<p>Safe content</p>'
            );
    }

    public function test_homepage_content_preserves_allowed_html(): void
    {
        $admin = $this->adminUser();

        $allowedHtml =
            '<p><strong>Luxury</strong> kitchen products</p>';

        $response = $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'content_allowed_html',

                    'title' => [
                        'en' =>
                            'Allowed HTML',
                    ],

                    'content' => [
                        'en' =>
                            $allowedHtml,
                    ],

                    'is_active' =>
                        true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.content',
                $allowedHtml
            );
    }

    public function test_homepage_content_removes_javascript_urls(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'content_url_test',

                    'title' => [
                        'en' =>
                            'Content URL Test',
                    ],

                    'content' => [
                        'en' =>
                            '<a href="javascript:alert(1)">Click</a>',
                    ],

                    'is_active' =>
                        true,
                ]
            );

        $response->assertCreated();

        $content =
            $response->json(
                'data.content'
            );

        $this->assertStringNotContainsString(
            'javascript:',
            strtolower(
                (string) $content
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | URL Security
    |--------------------------------------------------------------------------
    */

    public function test_homepage_section_rejects_javascript_links(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'unsafe_javascript_link',

                    'title' => [
                        'en' =>
                            'Unsafe Link',
                    ],

                    'link' =>
                        'javascript:alert(1)',

                    'is_active' =>
                        true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'link',
            ]);
    }

    public function test_homepage_section_rejects_case_insensitive_javascript_links(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'unsafe_case_link',

                    'title' => [
                        'en' =>
                            'Unsafe Link',
                    ],

                    'link' =>
                        'JaVaScRiPt:alert(1)',

                    'is_active' =>
                        true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'link',
            ]);
    }

    public function test_homepage_section_rejects_data_links(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'unsafe_data_link',

                    'title' => [
                        'en' =>
                            'Unsafe Link',
                    ],

                    'link' =>
                        'data:text/html,<script>alert(1)</script>',

                    'is_active' =>
                        true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'link',
            ]);
    }

    public function test_homepage_section_rejects_protocol_relative_links(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'unsafe_protocol_link',

                    'title' => [
                        'en' =>
                            'Unsafe Link',
                    ],

                    'link' =>
                        '//evil.example.com/phishing',

                    'is_active' =>
                        true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'link',
            ]);
    }

    public function test_homepage_section_accepts_internal_links(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'safe_internal_link',

                    'title' => [
                        'en' =>
                            'Internal Link',
                    ],

                    'link' =>
                        '/collections/summer',

                    'is_active' =>
                        true,
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.link',
                '/collections/summer'
            );
    }

    public function test_homepage_section_accepts_internal_link_with_query_string(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'safe_internal_query',

                    'title' => [
                        'en' =>
                            'Internal Link',
                    ],

                    'link' =>
                        '/collections/summer?sort=popular',

                    'is_active' =>
                        true,
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.link',
                '/collections/summer?sort=popular'
            );
    }

    public function test_homepage_section_accepts_https_links(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'safe_https_link',

                    'title' => [
                        'en' =>
                            'External Link',
                    ],

                    'link' =>
                        'https://example.com/summer',

                    'is_active' =>
                        true,
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.link',
                'https://example.com/summer'
            );
    }

    public function test_homepage_section_accepts_http_links(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'safe_http_link',

                    'title' => [
                        'en' =>
                            'External Link',
                    ],

                    'link' =>
                        'http://example.com/summer',

                    'is_active' =>
                        true,
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.link',
                'http://example.com/summer'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    public function test_admin_rejects_invalid_section_key_format(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/homepage-sections',
                [
                    'section_key' =>
                        'Invalid Section',

                    'title' => [
                        'en' =>
                            'Invalid',
                    ],

                    'is_active' =>
                        true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'section_key',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function adminUser(): User
    {
        $user = User::factory()->create([
            'status' =>
                UserStatus::ACTIVE,
        ]);

        $role = Role::create([
            'name' =>
                'Admin',

            'slug' =>
                'admin',
        ]);

        $permission = Permission::create([
            'name' =>
                'CMS Manage',

            'slug' =>
                'cms.manage',
        ]);

        $role->permissions()->sync([
            $permission->id,
        ]);

        $user->roles()->attach(
            $role->id
        );

        return $user;
    }

    private function decodeTranslation(
        mixed $value,
        string $locale
    ): ?string {
        if (
            ! is_string($value)
            || trim($value) === ''
        ) {
            return null;
        }

        $decoded = json_decode(
            $value,
            true
        );

        if (! is_array($decoded)) {
            return null;
        }

        return isset(
            $decoded[$locale]
        )
            ? (string) $decoded[$locale]
            : null;
    }
}
