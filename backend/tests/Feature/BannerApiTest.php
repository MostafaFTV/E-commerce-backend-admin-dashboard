<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Banner;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannerApiTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Basic CRUD
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_list_banners(): void
    {
        $admin = $this->adminUser();

        Banner::create([
            'title' => [
                'en' => 'Summer Collection',
                'fa' => 'کالکشن تابستانه',
            ],
            'image' =>
                '/storage/banners/summer.webp',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/banners')
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.title',
                'Summer Collection'
            )
            ->assertJsonPath(
                'data.0.is_active',
                true
            );
    }

    public function test_admin_can_create_banner(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/banners', [
                'title' => [
                    'en' => 'Summer Collection',
                    'fa' => 'کالکشن تابستانه',
                ],
                'subtitle' => [
                    'en' =>
                        'Discover our latest collection',
                    'fa' =>
                        'جدیدترین کالکشن ما را ببینید',
                ],
                'image' =>
                    '/storage/banners/summer.webp',
                'mobile_image' =>
                    '/storage/banners/summer-mobile.webp',
                'link' =>
                    '/collections/summer',
                'button_text' => [
                    'en' => 'Shop Now',
                    'fa' => 'خرید کنید',
                ],
                'sort_order' => 1,
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.title',
                'Summer Collection'
            )
            ->assertJsonPath(
                'data.is_currently_active',
                true
            );

        $this->assertDatabaseHas(
            'banners',
            [
                'image' =>
                    '/storage/banners/summer.webp',
                'sort_order' => 1,
                'is_active' => 1,
            ]
        );

        $this->assertDatabaseCount(
            'banners',
            1
        );
    }

    public function test_admin_can_show_banner(): void
    {
        $admin = $this->adminUser();

        $banner = Banner::create([
            'title' => [
                'en' => 'Hero Banner',
                'fa' => 'بنر اصلی',
            ],
            'image' =>
                '/storage/banners/hero.webp',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->getJson(
                "/api/admin/banners/{$banner->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $banner->id
            )
            ->assertJsonPath(
                'data.title',
                'Hero Banner'
            );
    }

    public function test_admin_can_update_banner_and_localized_values_are_merged(): void
    {
        $admin = $this->adminUser();

        $banner = Banner::create([
            'title' => [
                'en' => 'Summer Collection',
                'fa' => 'کالکشن تابستانه',
            ],
            'subtitle' => [
                'en' => 'Original subtitle',
                'fa' => 'زیرعنوان اصلی',
            ],
            'image' =>
                '/storage/banners/summer.webp',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($admin)
            ->patchJson(
                "/api/admin/banners/{$banner->id}",
                [
                    'title' => [
                        'fa' =>
                            'کالکشن منتخب تابستانه',
                    ],
                    'sort_order' => 2,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.title',
                'Summer Collection'
            )
            ->assertJsonPath(
                'data.sort_order',
                2
            );

        $stored = $banner->fresh();

        $this->assertSame(
            'Summer Collection',
            $this->decodeTranslation(
                $stored->getRawOriginal('title'),
                'en'
            )
        );

        $this->assertSame(
            'کالکشن منتخب تابستانه',
            $this->decodeTranslation(
                $stored->getRawOriginal('title'),
                'fa'
            )
        );
    }

    public function test_admin_can_delete_banner(): void
    {
        $admin = $this->adminUser();

        $banner = Banner::create([
            'title' => [
                'en' => 'Temporary Banner',
                'fa' => 'بنر موقت',
            ],
            'image' =>
                '/storage/banners/temp.webp',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->deleteJson(
                "/api/admin/banners/{$banner->id}"
            )
            ->assertOk()
            ->assertJson([
                'message' =>
                    'Banner deleted successfully.',
            ]);

        $this->assertDatabaseMissing(
            'banners',
            [
                'id' => $banner->id,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Public API
    |--------------------------------------------------------------------------
    */

    public function test_public_api_returns_currently_active_banners_only(): void
    {
        Banner::create([
            'title' => [
                'en' => 'Active Banner',
                'fa' => 'بنر فعال',
            ],
            'image' =>
                '/storage/banners/active.webp',
            'is_active' => true,
            'starts_at' =>
                now()->subDay(),
            'ends_at' =>
                now()->addDay(),
            'sort_order' => 1,
        ]);

        Banner::create([
            'title' => [
                'en' => 'Inactive Banner',
                'fa' => 'بنر غیرفعال',
            ],
            'image' =>
                '/storage/banners/inactive.webp',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        Banner::create([
            'title' => [
                'en' => 'Future Banner',
                'fa' => 'بنر آینده',
            ],
            'image' =>
                '/storage/banners/future.webp',
            'is_active' => true,
            'starts_at' =>
                now()->addDay(),
            'ends_at' =>
                now()->addDays(2),
            'sort_order' => 3,
        ]);

        Banner::create([
            'title' => [
                'en' => 'Expired Banner',
                'fa' => 'بنر منقضی',
            ],
            'image' =>
                '/storage/banners/expired.webp',
            'is_active' => true,
            'starts_at' =>
                now()->subDays(2),
            'ends_at' =>
                now()->subDay(),
            'sort_order' => 4,
        ]);

        $this->getJson('/api/banners')
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.title',
                'Active Banner'
            )
            ->assertJsonPath(
                'data.0.is_currently_active',
                true
            );
    }

    public function test_public_api_allows_active_banner_without_schedule(): void
    {
        Banner::create([
            'title' => [
                'en' => 'Always Active',
                'fa' => 'همیشه فعال',
            ],
            'image' =>
                '/storage/banners/always.webp',
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            'sort_order' => 1,
        ]);

        $this->getJson('/api/banners')
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.title',
                'Always Active'
            );
    }

    public function test_admin_can_filter_banners_by_active_status(): void
    {
        $admin = $this->adminUser();

        Banner::create([
            'title' => [
                'en' => 'Active',
                'fa' => 'فعال',
            ],
            'image' =>
                '/storage/banners/active.webp',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Banner::create([
            'title' => [
                'en' => 'Inactive',
                'fa' => 'غیرفعال',
            ],
            'image' =>
                '/storage/banners/inactive.webp',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $this->actingAs($admin)
            ->getJson(
                '/api/admin/banners?is_active=1'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.title',
                'Active'
            );

        $this->actingAs($admin)
            ->getJson(
                '/api/admin/banners?is_active=0'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.title',
                'Inactive'
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
            'status' => UserStatus::ACTIVE,
        ]);

        $this->actingAs($user)
            ->getJson(
                '/api/admin/banners'
            )
            ->assertForbidden();
    }

    public function test_guest_cannot_access_admin_banners(): void
    {
        $this->getJson(
            '/api/admin/banners'
        )->assertUnauthorized();
    }

    public function test_public_banner_endpoint_does_not_require_authentication(): void
    {
        $this->getJson(
            '/api/banners'
        )->assertOk();
    }

    public function test_admin_without_permission_cannot_create_banner(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $this->actingAs($user)
            ->postJson(
                '/api/admin/banners',
                [
                    'image' =>
                        '/storage/banners/test.webp',
                    'is_active' => true,
                ]
            )
            ->assertForbidden();
    }

    public function test_admin_without_permission_cannot_update_banner(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $banner = Banner::create([
            'title' => [
                'en' => 'Original',
            ],
            'image' =>
                '/storage/banners/original.webp',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->patchJson(
                "/api/admin/banners/{$banner->id}",
                [
                    'sort_order' => 10,
                ]
            )
            ->assertForbidden();
    }

    public function test_admin_without_permission_cannot_delete_banner(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $banner = Banner::create([
            'title' => [
                'en' => 'Original',
            ],
            'image' =>
                '/storage/banners/original.webp',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->deleteJson(
                "/api/admin/banners/{$banner->id}"
            )
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Mass Assignment
    |--------------------------------------------------------------------------
    */

    public function test_banner_store_ignores_unvalidated_sensitive_fields(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson(
                '/api/admin/banners',
                [
                    'title' => [
                        'en' => 'Safe Banner',
                    ],
                    'image' =>
                        '/storage/banners/safe.webp',
                    'is_active' => true,
                    'sort_order' => 1,
                    'id' => 9999,
                    'created_at' =>
                        '2000-01-01 00:00:00',
                    'updated_at' =>
                        '2000-01-01 00:00:00',
                ]
            );

        $response->assertCreated();

        $banner = Banner::query()
            ->latest('id')
            ->firstOrFail();

        $this->assertNotSame(
            9999,
            $banner->id
        );

        $this->assertNotSame(
            '2000-01-01 00:00:00',
            $banner->created_at
                ?->format(
                    'Y-m-d H:i:s'
                )
        );
    }

    public function test_banner_update_ignores_unvalidated_sensitive_fields(): void
    {
        $admin = $this->adminUser();

        $banner = Banner::create([
            'title' => [
                'en' => 'Original Banner',
            ],
            'image' =>
                '/storage/banners/original.webp',
            'is_active' => true,
        ]);

        $originalCreatedAt =
            $banner->created_at
                ?->format(
                    'Y-m-d H:i:s'
                );

        $this->actingAs($admin)
            ->patchJson(
                "/api/admin/banners/{$banner->id}",
                [
                    'sort_order' => 5,
                    'id' => 9999,
                    'created_at' =>
                        '2000-01-01 00:00:00',
                    'updated_at' =>
                        '2000-01-01 00:00:00',
                ]
            )
            ->assertOk();

        $updated = $banner->fresh();

        $this->assertSame(
            $banner->id,
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
            5,
            $updated->sort_order
        );
    }

    /*
    |--------------------------------------------------------------------------
    | XSS
    |--------------------------------------------------------------------------
    */

    public function test_banner_title_removes_script_tags(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson(
                '/api/admin/banners',
                [
                    'title' => [
                        'en' =>
                            '<script>alert("xss")</script>Summer Collection',
                    ],
                    'image' =>
                        '/storage/banners/xss.webp',
                    'is_active' => true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.title',
                'Summer Collection'
            );

        $banner = Banner::query()
            ->firstOrFail();

        $this->assertSame(
            'Summer Collection',
            $this->decodeTranslation(
                $banner->getRawOriginal(
                    'title'
                ),
                'en'
            )
        );
    }

    public function test_banner_title_removes_event_handler_markup(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson(
                '/api/admin/banners',
                [
                    'title' => [
                        'en' =>
                            '<img src="x" onerror="alert(1)">Summer',
                    ],
                    'image' =>
                        '/storage/banners/xss.webp',
                    'is_active' => true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.title',
                'Summer'
            );
    }

    public function test_banner_subtitle_removes_event_handler_attributes(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson(
                '/api/admin/banners',
                [
                    'title' => [
                        'en' => 'Safe Title',
                    ],
                    'subtitle' => [
                        'en' =>
                            '<img src="x" onerror="alert(1)">Discover',
                    ],
                    'image' =>
                        '/storage/banners/xss.webp',
                    'is_active' => true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.subtitle',
                'Discover'
            );

        $banner = Banner::query()
            ->firstOrFail();

        $stored = $this->decodeTranslation(
            $banner->getRawOriginal(
                'subtitle'
            ),
            'en'
        );

        $this->assertSame(
            'Discover',
            $stored
        );
    }

    public function test_banner_button_text_is_sanitized(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson(
                '/api/admin/banners',
                [
                    'title' => [
                        'en' => 'Summer',
                    ],
                    'button_text' => [
                        'en' =>
                            '<b>Shop Now</b><script>alert(1)</script>',
                    ],
                    'image' =>
                        '/storage/banners/summer.webp',
                    'is_active' => true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.button_text',
                'Shop Now'
            );
    }

    public function test_banner_update_sanitizes_localized_values(): void
    {
        $admin = $this->adminUser();

        $banner = Banner::create([
            'title' => [
                'en' => 'Original',
                'fa' => 'اصلی',
            ],
            'image' =>
                '/storage/banners/original.webp',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patchJson(
                "/api/admin/banners/{$banner->id}",
                [
                    'title' => [
                        'fa' =>
                            '<script>alert(1)</script>جدید',
                    ],
                ]
            )
            ->assertOk();

        $stored = $banner->fresh();

        $this->assertSame(
            'جدید',
            $this->decodeTranslation(
                $stored->getRawOriginal(
                    'title'
                ),
                'fa'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | URL Security
    |--------------------------------------------------------------------------
    */

    public function test_banner_rejects_javascript_links(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/banners',
                [
                    'title' => [
                        'en' => 'Unsafe Link',
                    ],
                    'image' =>
                        '/storage/banners/unsafe.webp',
                    'link' =>
                        'javascript:alert(1)',
                    'is_active' => true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'link',
            ]);
    }

    public function test_banner_rejects_case_insensitive_javascript_links(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/banners',
                [
                    'title' => [
                        'en' => 'Unsafe Link',
                    ],
                    'image' =>
                        '/storage/banners/unsafe.webp',
                    'link' =>
                        'JaVaScRiPt:alert(1)',
                    'is_active' => true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'link',
            ]);
    }

    public function test_banner_rejects_data_links(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/banners',
                [
                    'title' => [
                        'en' => 'Unsafe Link',
                    ],
                    'image' =>
                        '/storage/banners/unsafe.webp',
                    'link' =>
                        'data:text/html,<script>alert(1)</script>',
                    'is_active' => true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'link',
            ]);
    }

    public function test_banner_rejects_protocol_relative_links(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/banners',
                [
                    'title' => [
                        'en' => 'Unsafe Link',
                    ],
                    'image' =>
                        '/storage/banners/unsafe.webp',
                    'link' =>
                        '//evil.example.com/phishing',
                    'is_active' => true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'link',
            ]);
    }

    public function test_banner_rejects_invalid_relative_link(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/banners',
                [
                    'title' => [
                        'en' => 'Unsafe Link',
                    ],
                    'image' =>
                        '/storage/banners/unsafe.webp',
                    'is_active' => true,
                    'link' =>
                        'javascript-test',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'link',
            ]);
    }

    public function test_banner_accepts_internal_links(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/banners',
                [
                    'title' => [
                        'en' => 'Internal Link',
                    ],
                    'image' =>
                        '/storage/banners/internal.webp',
                    'link' =>
                        '/collections/summer',
                    'is_active' => true,
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.link',
                '/collections/summer'
            );
    }

    public function test_banner_accepts_internal_links_with_query_string(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/banners',
                [
                    'title' => [
                        'en' => 'Internal Link',
                    ],
                    'image' =>
                        '/storage/banners/internal.webp',
                    'link' =>
                        '/collections/summer?sort=popular',
                    'is_active' => true,
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.link',
                '/collections/summer?sort=popular'
            );
    }

    public function test_banner_accepts_https_links(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/banners',
                [
                    'title' => [
                        'en' => 'External Link',
                    ],
                    'image' =>
                        '/storage/banners/external.webp',
                    'link' =>
                        'https://example.com/collections/summer',
                    'is_active' => true,
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.link',
                'https://example.com/collections/summer'
            );
    }

    public function test_banner_accepts_http_links(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/banners',
                [
                    'title' => [
                        'en' => 'External Link',
                    ],
                    'image' =>
                        '/storage/banners/external.webp',
                    'link' =>
                        'http://example.com/collections/summer',
                    'is_active' => true,
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.link',
                'http://example.com/collections/summer'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    public function test_admin_rejects_missing_required_banner_fields(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/banners',
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'image',
                'is_active',
            ]);
    }

    public function test_banner_rejects_invalid_localized_title_shape(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/banners',
                [
                    'title' =>
                        'Summer Collection',
                    'image' =>
                        '/storage/banners/test.webp',
                    'is_active' => true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title',
            ]);
    }

    public function test_banner_rejects_invalid_boolean_is_active(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/banners',
                [
                    'title' => [
                        'en' => 'Test',
                    ],
                    'image' =>
                        '/storage/banners/test.webp',
                    'is_active' =>
                        'not-a-boolean',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'is_active',
            ]);
    }

    public function test_banner_rejects_negative_sort_order(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/banners',
                [
                    'title' => [
                        'en' => 'Test',
                    ],
                    'image' =>
                        '/storage/banners/test.webp',
                    'sort_order' => -1,
                    'is_active' => true,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'sort_order',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Scheduling
    |--------------------------------------------------------------------------
    */

    public function test_banner_requires_end_date_after_or_equal_to_start_date(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(
                '/api/admin/banners',
                [
                    'title' => [
                        'en' =>
                            'Invalid Schedule',
                        'fa' =>
                            'زمان‌بندی نامعتبر',
                    ],
                    'image' =>
                        '/storage/banners/invalid.webp',
                    'is_active' => true,
                    'starts_at' =>
                        '2030-12-31 00:00:00',
                    'ends_at' =>
                        '2030-01-01 00:00:00',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'ends_at',
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
            'status' => UserStatus::ACTIVE,
        ]);

        $role = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
        ]);

        $permission = Permission::create([
            'name' => 'CMS Manage',
            'slug' => 'cms.manage',
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

        return isset($decoded[$locale])
            ? (string) $decoded[$locale]
            : null;
    }
}
