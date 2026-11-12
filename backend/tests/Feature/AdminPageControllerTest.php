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

class AdminPageControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function test_user_with_cms_permission_can_view_pages(): void
    {
        $admin = $this->createUserWithPermission(
            'cms.manage'
        );

        $page = $this->createPage([
            'title' => [
                'en' => 'About Us',
                'fa' => 'درباره ما',
            ],
            'slug' => 'about-us',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/pages');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $page->id
            )
            ->assertJsonPath(
                'data.0.title',
                'About Us'
            )
            ->assertJsonPath(
                'data.0.slug',
                'about-us'
            )
            ->assertJsonPath(
                'data.0.status',
                'published'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_search_pages_by_title(): void
    {
        $admin = $this->createUserWithPermission(
            'cms.manage'
        );

        $matchingPage = $this->createPage([
            'title' => [
                'en' => 'Privacy Policy',
                'fa' => 'سیاست حفظ حریم خصوصی',
            ],
            'slug' => 'privacy-policy',
        ]);

        $this->createPage([
            'title' => [
                'en' => 'About Us',
                'fa' => 'درباره ما',
            ],
            'slug' => 'about-us',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/pages?search=Privacy'
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $matchingPage->id
            );
    }

    public function test_admin_can_search_pages_by_slug(): void
    {
        $admin = $this->createUserWithPermission(
            'cms.manage'
        );

        $matchingPage = $this->createPage([
            'title' => [
                'en' => 'Shipping Information',
                'fa' => 'اطلاعات ارسال',
            ],
            'slug' => 'shipping-information',
        ]);

        $this->createPage([
            'title' => [
                'en' => 'About Us',
                'fa' => 'درباره ما',
            ],
            'slug' => 'about-us',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/pages?search=shipping-information'
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $matchingPage->id
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Status Filter
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_filter_pages_by_status(): void
    {
        $admin = $this->createUserWithPermission(
            'cms.manage'
        );

        $publishedPage = $this->createPage([
            'title' => [
                'en' => 'Published Page',
                'fa' => 'صفحه منتشر شده',
            ],
            'slug' => 'published-page',
            'status' => PageStatus::PUBLISHED,
        ]);

        $this->createPage([
            'title' => [
                'en' => 'Draft Page',
                'fa' => 'صفحه پیش‌نویس',
            ],
            'slug' => 'draft-page',
            'status' => PageStatus::DRAFT,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                '/api/admin/pages?status=published'
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $publishedPage->id
            )
            ->assertJsonPath(
                'data.0.status',
                'published'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_view_page_details(): void
    {
        $admin = $this->createUserWithPermission(
            'cms.manage'
        );

        $page = $this->createPage([
            'title' => [
                'en' => 'Terms and Conditions',
                'fa' => 'قوانین و شرایط',
            ],
            'slug' => 'terms-and-conditions',
            'content' => [
                'en' => '<h1>Terms</h1>',
                'fa' => '<h1>قوانین</h1>',
            ],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson(
                "/api/admin/pages/{$page->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $page->id
            )
            ->assertJsonPath(
                'data.title',
                'Terms and Conditions'
            )
            ->assertJsonPath(
                'data.slug',
                'terms-and-conditions'
            )
            ->assertJsonPath(
                'data.content',
                '<h1>Terms</h1>'
            )
            ->assertJsonPath(
                'data.created_by.id',
                $admin->id
            )
            ->assertJsonPath(
                'data.updated_by.id',
                $admin->id
            );
    }

    public function test_user_without_cms_permission_cannot_view_page_details(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $page = $this->createPage();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/admin/pages/{$page->id}"
            );

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Forbidden: Missing permission'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_page(): void
    {
        $admin = $this->createUserWithPermission(
            'cms.manage'
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/pages',
                [
                    'title' => [
                        'en' => 'About Us',
                        'fa' => 'درباره ما',
                    ],
                    'slug' => 'about-us',
                    'content' => [
                        'en' => '<h1>About Us</h1>',
                        'fa' => '<h1>درباره ما</h1>',
                    ],
                    'status' => 'published',
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Page created successfully.'
            )
            ->assertJsonPath(
                'data.title',
                'About Us'
            )
            ->assertJsonPath(
                'data.slug',
                'about-us'
            )
            ->assertJsonPath(
                'data.status',
                'published'
            )
            ->assertJsonPath(
                'data.created_by.id',
                $admin->id
            )
            ->assertJsonPath(
                'data.updated_by.id',
                $admin->id
            );

        $page = Page::query()
            ->where(
                'slug',
                'about-us'
            )
            ->firstOrFail();

        $this->assertSame(
            [
                'en' => 'About Us',
                'fa' => 'درباره ما',
            ],
            json_decode(
                $page->getRawOriginal('title'),
                true
            )
        );

        $this->assertSame(
            [
                'en' => '<h1>About Us</h1>',
                'fa' => '<h1>درباره ما</h1>',
            ],
            json_decode(
                $page->getRawOriginal('content'),
                true
            )
        );
    }

    public function test_page_creation_cannot_spoof_created_by_or_updated_by(): void
    {
        $admin = $this->createUserWithPermission(
            'cms.manage'
        );

        $attacker = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/pages',
                [
                    'title' => [
                        'en' => 'Security Test',
                        'fa' => 'تست امنیتی',
                    ],
                    'slug' => 'security-test',
                    'content' => [
                        'en' => 'Security content',
                        'fa' => 'محتوای امنیتی',
                    ],
                    'status' => 'draft',

                    'created_by' => $attacker->id,
                    'updated_by' => $attacker->id,
                    'id' => 999999,
                ]
            );

        $response->assertCreated();

        $page = Page::query()
            ->where(
                'slug',
                'security-test'
            )
            ->firstOrFail();

        $this->assertSame(
            $admin->id,
            $page->created_by
        );

        $this->assertSame(
            $admin->id,
            $page->updated_by
        );

        $this->assertNotSame(
            999999,
            $page->id
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_update_page(): void
    {
        $admin = $this->createUserWithPermission(
            'cms.manage'
        );

        $page = $this->createPage([
            'title' => [
                'en' => 'Old Title',
                'fa' => 'عنوان قدیمی',
            ],
            'slug' => 'old-title',
            'content' => [
                'en' => 'Old content',
                'fa' => 'محتوای قدیمی',
            ],
            'status' => PageStatus::DRAFT,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/pages/{$page->id}",
                [
                    'title' => [
                        'en' => 'New Title',
                        'fa' => 'عنوان جدید',
                    ],
                    'slug' => 'new-title',
                    'content' => [
                        'en' => 'New content',
                        'fa' => 'محتوای جدید',
                    ],
                    'status' => 'published',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Page updated successfully.'
            )
            ->assertJsonPath(
                'data.title',
                'New Title'
            )
            ->assertJsonPath(
                'data.slug',
                'new-title'
            )
            ->assertJsonPath(
                'data.content',
                'New content'
            )
            ->assertJsonPath(
                'data.status',
                'published'
            )
            ->assertJsonPath(
                'data.updated_by.id',
                $admin->id
            );

        $page->refresh();

        $this->assertSame(
            [
                'en' => 'New Title',
                'fa' => 'عنوان جدید',
            ],
            json_decode(
                $page->getRawOriginal('title'),
                true
            )
        );

        $this->assertSame(
            [
                'en' => 'New content',
                'fa' => 'محتوای جدید',
            ],
            json_decode(
                $page->getRawOriginal('content'),
                true
            )
        );

        $this->assertSame(
            'new-title',
            $page->slug
        );

        $this->assertSame(
            PageStatus::PUBLISHED,
            $page->status
        );
    }

    public function test_admin_can_partially_update_page(): void
    {
        $admin = $this->createUserWithPermission(
            'cms.manage'
        );

        $page = $this->createPage([
            'title' => [
                'en' => 'Original Title',
                'fa' => 'عنوان اصلی',
            ],
            'slug' => 'original-title',
            'content' => [
                'en' => 'Original content',
                'fa' => 'محتوای اصلی',
            ],
            'status' => PageStatus::DRAFT,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/pages/{$page->id}",
                [
                    'title' => [
                        'fa' => 'عنوان فارسی جدید',
                    ],
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.title',
                'Original Title'
            )
            ->assertJsonPath(
                'data.slug',
                'original-title'
            )
            ->assertJsonPath(
                'data.content',
                'Original content'
            )
            ->assertJsonPath(
                'data.status',
                'draft'
            );

        $page->refresh();

        $this->assertSame(
            [
                'en' => 'Original Title',
                'fa' => 'عنوان فارسی جدید',
            ],
            json_decode(
                $page->getRawOriginal('title'),
                true
            )
        );

        $this->assertSame(
            [
                'en' => 'Original content',
                'fa' => 'محتوای اصلی',
            ],
            json_decode(
                $page->getRawOriginal('content'),
                true
            )
        );
    }

    public function test_user_without_cms_permission_cannot_update_page(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $page = $this->createPage([
            'title' => [
                'en' => 'Original Title',
                'fa' => 'عنوان اصلی',
            ],
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->patchJson(
                "/api/admin/pages/{$page->id}",
                [
                    'title' => [
                        'en' => 'Hacked Title',
                    ],
                ]
            );

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Forbidden: Missing permission'
            );

        $page->refresh();

        $this->assertSame(
            [
                'en' => 'Original Title',
                'fa' => 'عنوان اصلی',
            ],
            json_decode(
                $page->getRawOriginal('title'),
                true
            )
        );
    }

    public function test_page_update_cannot_spoof_created_by_or_updated_by(): void
    {
        $admin = $this->createUserWithPermission(
            'cms.manage'
        );

        $attacker = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $page = $this->createPage([
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $originalCreatedBy = $page->created_by;

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson(
                "/api/admin/pages/{$page->id}",
                [
                    'title' => [
                        'en' => 'Updated Secure Page',
                    ],

                    'created_by' => $attacker->id,
                    'updated_by' => $attacker->id,
                    'id' => 999999,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.title',
                'Updated Secure Page'
            );

        $page->refresh();

        $this->assertSame(
            $originalCreatedBy,
            $page->created_by
        );

        $this->assertSame(
            $admin->id,
            $page->updated_by
        );

        $this->assertSame(
            $page->id,
            $response->json('data.id')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    public function test_duplicate_page_slug_is_rejected(): void
    {
        $admin = $this->createUserWithPermission(
            'cms.manage'
        );

        $this->createPage([
            'slug' => 'about-us',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/pages',
                [
                    'title' => [
                        'en' => 'Another Page',
                        'fa' => 'صفحه دیگر',
                    ],
                    'slug' => 'about-us',
                    'content' => [
                        'en' => 'Content',
                        'fa' => 'محتوا',
                    ],
                    'status' => 'draft',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);
    }

    public function test_invalid_page_slug_is_rejected(): void
    {
        $admin = $this->createUserWithPermission(
            'cms.manage'
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/pages',
                [
                    'title' => [
                        'en' => 'Invalid Slug',
                        'fa' => 'اسلاگ نامعتبر',
                    ],
                    'slug' => 'Invalid Slug',
                    'content' => [
                        'en' => 'Content',
                        'fa' => 'محتوا',
                    ],
                    'status' => 'draft',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);
    }

    public function test_invalid_page_status_is_rejected(): void
    {
        $admin = $this->createUserWithPermission(
            'cms.manage'
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/pages',
                [
                    'title' => [
                        'en' => 'Invalid Status',
                        'fa' => 'وضعیت نامعتبر',
                    ],
                    'slug' => 'invalid-status',
                    'content' => [
                        'en' => 'Content',
                        'fa' => 'محتوا',
                    ],
                    'status' => 'archived',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);
    }

    public function test_page_creation_requires_required_fields(): void
    {
        $admin = $this->createUserWithPermission(
            'cms.manage'
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/pages',
                []
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title',
                'title.en',
                'title.fa',
                'content',
                'content.en',
                'content.fa',
                'status',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | XSS Security
    |--------------------------------------------------------------------------
    */

    public function test_page_content_removes_script_tags(): void
    {
        $admin = $this->createUserWithPermission(
            'cms.manage'
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/pages',
                [
                    'title' => [
                        'en' => 'XSS Test',
                        'fa' => 'تست XSS',
                    ],
                    'slug' => 'xss-script-test',
                    'content' => [
                        'en' =>
                            '<p>Hello</p>'
                            . '<script>alert("XSS")</script>'
                            . '<p>World</p>',

                        'fa' =>
                            '<p>سلام</p>'
                            . '<script>alert("XSS")</script>',
                    ],
                    'status' => 'published',
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.content',
                '<p>Hello</p><p>World</p>'
            );

        $page = Page::query()
            ->where(
                'slug',
                'xss-script-test'
            )
            ->firstOrFail();

        $storedContent = json_decode(
            $page->getRawOriginal('content'),
            true
        );

        $this->assertSame(
            '<p>Hello</p><p>World</p>',
            $storedContent['en']
        );

        $this->assertSame(
            '<p>سلام</p>',
            $storedContent['fa']
        );

        $this->assertStringNotContainsString(
            '<script',
            strtolower($storedContent['en'])
        );

        $this->assertStringNotContainsString(
            '<script',
            strtolower($storedContent['fa'])
        );
    }

    public function test_page_content_removes_event_handler_attributes(): void
    {
        $admin = $this->createUserWithPermission(
            'cms.manage'
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/pages',
                [
                    'title' => [
                        'en' => 'XSS Event Test',
                        'fa' => 'تست رویداد XSS',
                    ],
                    'slug' => 'xss-event-test',
                    'content' => [
                        'en' =>
                            '<p onclick="alert(\'XSS\')" '
                            . 'onmouseover="alert(\'XSS\')">Hello</p>'
                            . '<img src="x" onerror="alert(\'XSS\')">',

                        'fa' =>
                            '<p onload="alert(\'XSS\')">سلام</p>',
                    ],
                    'status' => 'published',
                ]
            );

        $response->assertCreated();

        $page = Page::query()
            ->where(
                'slug',
                'xss-event-test'
            )
            ->firstOrFail();

        $storedContent = json_decode(
            $page->getRawOriginal('content'),
            true
        );

        $this->assertStringNotContainsString(
            'onclick',
            strtolower($storedContent['en'])
        );

        $this->assertStringNotContainsString(
            'onmouseover',
            strtolower($storedContent['en'])
        );

        $this->assertStringNotContainsString(
            'onerror',
            strtolower($storedContent['en'])
        );

        $this->assertStringNotContainsString(
            'onload',
            strtolower($storedContent['fa'])
        );

        $this->assertStringNotContainsString(
            'alert(',
            strtolower($storedContent['en'])
        );

        $this->assertStringNotContainsString(
            'alert(',
            strtolower($storedContent['fa'])
        );
    }

    public function test_page_content_removes_javascript_urls(): void
    {
        $admin = $this->createUserWithPermission(
            'cms.manage'
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/pages',
                [
                    'title' => [
                        'en' => 'XSS URL Test',
                        'fa' => 'تست آدرس XSS',
                    ],
                    'slug' => 'xss-url-test',
                    'content' => [
                        'en' =>
                            '<p>Safe text</p>'
                            . '<a href="javascript:alert(\'XSS\')">'
                            . 'Click me'
                            . '</a>',

                        'fa' =>
                            '<a href="javascript:alert(\'XSS\')">'
                            . 'کلیک کنید'
                            . '</a>',
                    ],
                    'status' => 'published',
                ]
            );

        $response->assertCreated();

        $page = Page::query()
            ->where(
                'slug',
                'xss-url-test'
            )
            ->firstOrFail();

        $storedContent = json_decode(
            $page->getRawOriginal('content'),
            true
        );

        $this->assertStringNotContainsString(
            'javascript:',
            strtolower($storedContent['en'])
        );

        $this->assertStringNotContainsString(
            'javascript:',
            strtolower($storedContent['fa'])
        );
    }

    public function test_page_content_preserves_allowed_html(): void
    {
        $admin = $this->createUserWithPermission(
            'cms.manage'
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson(
                '/api/admin/pages',
                [
                    'title' => [
                        'en' => 'Allowed HTML Test',
                        'fa' => 'تست HTML مجاز',
                    ],
                    'slug' => 'allowed-html-test',
                    'content' => [
                        'en' =>
                            '<h1>Title</h1>'
                            . '<p><strong>Important</strong></p>'
                            . '<a href="https://example.com" '
                            . 'title="Example">Link</a>',

                        'fa' =>
                            '<h2>عنوان</h2>'
                            . '<p><em>متن</em></p>'
                            . '<ul><li>مورد اول</li></ul>',
                    ],
                    'status' => 'published',
                ]
            );

        $response->assertCreated();

        $content = $response->json(
            'data.content'
        );

        $this->assertStringContainsString(
            '<h1>Title</h1>',
            $content
        );

        $this->assertStringContainsString(
            '<p><strong>Important</strong></p>',
            $content
        );

        $this->assertStringContainsString(
            '<a href="https://example.com"',
            $content
        );

        $page = Page::query()
            ->where(
                'slug',
                'allowed-html-test'
            )
            ->firstOrFail();

        $storedContent = json_decode(
            $page->getRawOriginal('content'),
            true
        );

        $this->assertStringContainsString(
            '<h1>Title</h1>',
            $storedContent['en']
        );

        $this->assertStringContainsString(
            '<strong>Important</strong>',
            $storedContent['en']
        );

        $this->assertStringContainsString(
            'https://example.com',
            $storedContent['en']
        );

        $this->assertStringContainsString(
            '<h2>عنوان</h2>',
            $storedContent['fa']
        );

        $this->assertStringContainsString(
            '<em>متن</em>',
            $storedContent['fa']
        );

        $this->assertStringContainsString(
            '<ul><li>مورد اول</li></ul>',
            $storedContent['fa']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_delete_page(): void
    {
        $admin = $this->createUserWithPermission(
            'cms.manage'
        );

        $page = $this->createPage();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->deleteJson(
                "/api/admin/pages/{$page->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Page deleted successfully.'
            );

        $this->assertDatabaseMissing(
            'pages',
            [
                'id' => $page->id,
            ]
        );
    }

    public function test_user_without_cms_permission_cannot_delete_page(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $page = $this->createPage();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->deleteJson(
                "/api/admin/pages/{$page->id}"
            );

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Forbidden: Missing permission'
            );

        $this->assertDatabaseHas(
            'pages',
            [
                'id' => $page->id,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_user_without_cms_permission_cannot_view_pages(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/admin/pages');

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Forbidden: Missing permission'
            );
    }

    public function test_user_without_cms_permission_cannot_create_page(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                '/api/admin/pages',
                [
                    'title' => [
                        'en' => 'Unauthorized',
                        'fa' => 'غیرمجاز',
                    ],
                    'slug' => 'unauthorized',
                    'content' => [
                        'en' => 'Content',
                        'fa' => 'محتوا',
                    ],
                    'status' => 'draft',
                ]
            );

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Forbidden: Missing permission'
            );

        $this->assertDatabaseMissing(
            'pages',
            [
                'slug' => 'unauthorized',
            ]
        );
    }

    public function test_guest_cannot_access_page_management(): void
    {
        $response = $this->getJson(
            '/api/admin/pages'
        );

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createUserWithPermission(
        string $permissionSlug
    ): User {
        $user = User::factory()->create([
            'email' =>
                'page-admin-' .
                uniqid() .
                '@tkb.test',

            'status' =>
                UserStatus::ACTIVE,
        ]);

        $role = Role::create([
            'name' => 'Page Admin',

            'slug' =>
                'page-admin-' .
                uniqid(),

            'description' =>
                'Test page admin role',
        ]);

        $permission = Permission::create([
            'name' => 'CMS Manage',
            'slug' => $permissionSlug,
            'description' => 'Test CMS permission',
        ]);

        $role->permissions()->sync([
            $permission->id,
        ]);

        $user->roles()->attach(
            $role->id
        );

        return $user;
    }

    private function createPage(
        array $attributes = []
    ): Page {
        return Page::create(
            array_merge(
                [
                    'title' => [
                        'en' => 'Test Page',
                        'fa' => 'صفحه تست',
                    ],

                    'slug' =>
                        'test-page-' .
                        uniqid(),

                    'content' => [
                        'en' =>
                            'Test page content.',

                        'fa' =>
                            'محتوای صفحه تست.',
                    ],

                    'status' =>
                        PageStatus::PUBLISHED,

                    'created_by' =>
                        null,

                    'updated_by' =>
                        null,
                ],
                $attributes
            )
        );
    }
}
