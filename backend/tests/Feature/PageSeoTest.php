<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Enums\UserStatus;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageSeoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $role = Role::create([
            'name' => 'SEO Page Admin',
            'slug' => 'seo-page-admin-' . uniqid(),
            'description' => 'SEO page test role',
        ]);

        $permission = Permission::firstOrCreate(
            ['slug' => 'cms.manage'],
            [
                'name' => 'CMS Manage',
                'description' => 'CMS permission',
            ]
        );

        $role->permissions()->syncWithoutDetaching([
            $permission->id,
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }

    private function page(array $attributes = []): Page
    {
        return Page::create(array_merge([
            'title' => [
                'en' => 'About Us',
                'fa' => 'درباره ما',
            ],
            'slug' => 'seo-page-' . uniqid(),
            'content' => [
                'en' => 'Original English content',
                'fa' => 'محتوای اصلی فارسی',
            ],
            'status' => PageStatus::PUBLISHED,
            'created_by' => null,
            'updated_by' => null,
        ], $attributes));
    }

    private function seoPage(array $attributes = []): Page
    {
        return $this->page(array_merge([
            'seo_title' => [
                'en' => 'Luxury About Us',
                'fa' => 'درباره ما لوکس',
            ],
            'seo_description' => [
                'en' => 'Learn about our luxury brand.',
                'fa' => 'با برند لوکس ما آشنا شوید.',
            ],
            'seo_image' => '/images/seo/about.webp',
        ], $attributes));
    }

    private function translations(
        Page $page,
        string $field
    ): ?array {
        $page->refresh();

        $raw = $page->getRawOriginal($field);

        return $raw === null
            ? null
            : json_decode($raw, true);
    }

    public function test_admin_can_create_page_with_bilingual_seo(): void
    {
        $admin = $this->admin();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/pages', [
                'title' => [
                    'en' => 'Our Story',
                    'fa' => 'داستان ما',
                ],
                'slug' => 'our-story-seo',
                'content' => [
                    'en' => '<p>Our story</p>',
                    'fa' => '<p>داستان ما</p>',
                ],
                'status' => 'published',
                'seo_title' => [
                    'en' => 'Our Luxury Story',
                    'fa' => 'داستان لوکس ما',
                ],
                'seo_description' => [
                    'en' => 'Discover our story.',
                    'fa' => 'داستان برند ما را بخوانید.',
                ],
                'seo_image' => '/images/seo/story.webp',
            ]);

        $response->assertCreated();

        $page = Page::where(
            'slug',
            'our-story-seo'
        )->firstOrFail();

        $this->assertSame(
            [
                'en' => 'Our Luxury Story',
                'fa' => 'داستان لوکس ما',
            ],
            $this->translations($page, 'seo_title')
        );

        $this->assertSame(
            [
                'en' => 'Discover our story.',
                'fa' => 'داستان برند ما را بخوانید.',
            ],
            $this->translations($page, 'seo_description')
        );

        $this->assertSame(
            '/images/seo/story.webp',
            $page->refresh()->seo_image
        );

        $this->assertSame(
            $admin->id,
            $page->created_by
        );

        $this->assertSame(
            $admin->id,
            $page->updated_by
        );
    }

    public function test_persian_seo_update_preserves_english(): void
    {
        $admin = $this->admin();
        $page = $this->seoPage();

        $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/pages/{$page->id}",
                [
                    'seo_title' => [
                        'fa' => 'عنوان جدید فارسی',
                    ],
                    'seo_description' => [
                        'fa' => 'توضیحات جدید فارسی',
                    ],
                ]
            )
            ->assertOk();

        $this->assertSame(
            [
                'en' => 'Luxury About Us',
                'fa' => 'عنوان جدید فارسی',
            ],
            $this->translations($page, 'seo_title')
        );

        $this->assertSame(
            [
                'en' => 'Learn about our luxury brand.',
                'fa' => 'توضیحات جدید فارسی',
            ],
            $this->translations($page, 'seo_description')
        );
    }

    public function test_english_seo_update_preserves_persian(): void
    {
        $admin = $this->admin();
        $page = $this->seoPage();

        $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/pages/{$page->id}",
                [
                    'seo_title' => [
                        'en' => 'Updated English SEO',
                    ],
                ]
            )
            ->assertOk();

        $this->assertSame(
            [
                'en' => 'Updated English SEO',
                'fa' => 'درباره ما لوکس',
            ],
            $this->translations($page, 'seo_title')
        );
    }

    public function test_admin_can_clear_all_seo_fields(): void
    {
        $admin = $this->admin();
        $page = $this->seoPage();

        $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/pages/{$page->id}",
                [
                    'seo_title' => null,
                    'seo_description' => null,
                    'seo_image' => null,
                ]
            )
            ->assertOk();

        $page->refresh();

        $this->assertNull(
            $page->getRawOriginal('seo_title')
        );

        $this->assertNull(
            $page->getRawOriginal('seo_description')
        );

        $this->assertNull($page->seo_image);
    }

    public function test_omitted_seo_fields_remain_unchanged(): void
    {
        $admin = $this->admin();
        $page = $this->seoPage();

        $originalTitle = $this->translations(
            $page,
            'seo_title'
        );

        $originalDescription = $this->translations(
            $page,
            'seo_description'
        );

        $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/pages/{$page->id}",
                [
                    'status' => 'draft',
                ]
            )
            ->assertOk();

        $this->assertSame(
            $originalTitle,
            $this->translations($page, 'seo_title')
        );

        $this->assertSame(
            $originalDescription,
            $this->translations($page, 'seo_description')
        );

        $this->assertSame(
            '/images/seo/about.webp',
            $page->refresh()->seo_image
        );

        $this->assertSame(
            PageStatus::DRAFT,
            $page->status
        );
    }

    public function test_invalid_seo_payload_is_rejected(): void
    {
        $admin = $this->admin();

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/pages', [
                'title' => [
                    'en' => 'Invalid SEO Page',
                    'fa' => 'صفحه نامعتبر',
                ],
                'slug' => 'invalid-page-seo',
                'content' => [
                    'en' => 'Test content',
                    'fa' => 'محتوای تست',
                ],
                'status' => 'published',
                'seo_title' => [
                    'de' => 'Unsupported language',
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

        $this->assertDatabaseMissing('pages', [
            'slug' => 'invalid-page-seo',
        ]);
    }

    public function test_public_api_returns_english_seo(): void
    {
        $page = $this->seoPage();

        $this
            ->withHeader('Accept-Language', 'en')
            ->getJson("/api/pages/{$page->slug}")
            ->assertOk()
            ->assertJsonPath(
                'data.seo_title',
                'Luxury About Us'
            )
            ->assertJsonPath(
                'data.seo_description',
                'Learn about our luxury brand.'
            )
            ->assertJsonPath(
                'data.seo_image',
                '/images/seo/about.webp'
            );
    }

    public function test_public_api_returns_persian_seo(): void
    {
        $page = $this->seoPage();

        $this
            ->withHeader('Accept-Language', 'fa')
            ->getJson("/api/pages/{$page->slug}")
            ->assertOk()
            ->assertJsonPath(
                'data.seo_title',
                'درباره ما لوکس'
            )
            ->assertJsonPath(
                'data.seo_description',
                'با برند لوکس ما آشنا شوید.'
            )
            ->assertJsonPath(
                'data.seo_image',
                '/images/seo/about.webp'
            );
    }

    public function test_admin_can_read_all_seo_translations(): void
    {
        $admin = $this->admin();
        $page = $this->seoPage();

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/pages/{$page->id}")
            ->assertOk()
            ->assertJsonPath(
                'data.seo_title_translations.en',
                'Luxury About Us'
            )
            ->assertJsonPath(
                'data.seo_title_translations.fa',
                'درباره ما لوکس'
            )
            ->assertJsonPath(
                'data.seo_description_translations.en',
                'Learn about our luxury brand.'
            )
            ->assertJsonPath(
                'data.seo_description_translations.fa',
                'با برند لوکس ما آشنا شوید.'
            );
    }

    public function test_user_without_permission_cannot_change_seo(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $page = $this->seoPage();

        $this
            ->actingAs($user, 'sanctum')
            ->patchJson(
                "/api/admin/pages/{$page->id}",
                [
                    'seo_title' => [
                        'en' => 'Unauthorized SEO',
                    ],
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            'Luxury About Us',
            $this->translations($page, 'seo_title')['en']
        );
    }

    public function test_seo_update_preserves_page_content_and_status(): void
    {
        $admin = $this->admin();

        $page = $this->seoPage([
            'slug' => 'stable-page-seo',
            'status' => PageStatus::DRAFT,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $originalTitle = json_decode(
            $page->getRawOriginal('title'),
            true
        );

        $originalContent = json_decode(
            $page->getRawOriginal('content'),
            true
        );

        $originalCreatedBy = $page->created_by;

        $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/pages/{$page->id}",
                [
                    'seo_title' => [
                        'fa' => 'سئوی جدید فارسی',
                    ],
                ]
            )
            ->assertOk();

        $page->refresh();

        $this->assertSame(
            'stable-page-seo',
            $page->slug
        );

        $this->assertSame(
            PageStatus::DRAFT,
            $page->status
        );

        $this->assertSame(
            $originalTitle,
            json_decode(
                $page->getRawOriginal('title'),
                true
            )
        );

        $this->assertSame(
            $originalContent,
            json_decode(
                $page->getRawOriginal('content'),
                true
            )
        );

        $this->assertSame(
            $originalCreatedBy,
            $page->created_by
        );

        $this->assertSame(
            $admin->id,
            $page->updated_by
        );
    }

    public function test_seo_update_cannot_spoof_author_fields(): void
    {
        $admin = $this->admin();

        $attacker = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $page = $this->seoPage([
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/pages/{$page->id}",
                [
                    'seo_title' => [
                        'en' => 'Secure SEO Update',
                    ],
                    'created_by' => $attacker->id,
                    'updated_by' => $attacker->id,
                ]
            )
            ->assertOk();

        $page->refresh();

        $this->assertSame(
            $admin->id,
            $page->created_by
        );

        $this->assertSame(
            $admin->id,
            $page->updated_by
        );

        $this->assertSame(
            'Secure SEO Update',
            $this->translations($page, 'seo_title')['en']
        );
    }
}