<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function test_public_pages_returns_only_published_pages(): void
    {
        $publishedPage = $this->createPage([
            'title' => [
                'en' => 'About Us',
                'fa' => 'درباره ما',
            ],
            'slug' => 'about-us',
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

        $response = $this->getJson(
            '/api/pages'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $publishedPage->id
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

    public function test_public_pages_does_not_require_authentication(): void
    {
        $this->createPage([
            'status' => PageStatus::PUBLISHED,
        ]);

        $response = $this->getJson(
            '/api/pages'
        );

        $response->assertOk();
    }

    public function test_public_pages_are_ordered_by_title(): void
    {
        $zebra = $this->createPage([
            'title' => [
                'en' => 'Zebra',
                'fa' => 'زِبرا',
            ],
            'slug' => 'zebra',
            'status' => PageStatus::PUBLISHED,
        ]);

        $alpha = $this->createPage([
            'title' => [
                'en' => 'Alpha',
                'fa' => 'آلفا',
            ],
            'slug' => 'alpha',
            'status' => PageStatus::PUBLISHED,
        ]);

        $middle = $this->createPage([
            'title' => [
                'en' => 'Middle',
                'fa' => 'میانی',
            ],
            'slug' => 'middle',
            'status' => PageStatus::PUBLISHED,
        ]);

        $response = $this->getJson(
            '/api/pages'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $alpha->id
            )
            ->assertJsonPath(
                'data.1.id',
                $middle->id
            )
            ->assertJsonPath(
                'data.2.id',
                $zebra->id
            );
    }

    public function test_public_pages_are_paginated(): void
    {
        foreach (range(1, 21) as $number) {
            $this->createPage([
                'title' => [
                    'en' => "Page {$number}",
                    'fa' => "صفحه {$number}",
                ],
                'slug' => "page-{$number}",
                'status' => PageStatus::PUBLISHED,
            ]);
        }

        $response = $this->getJson(
            '/api/pages'
        );

        $response
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ])
            ->assertJsonPath(
                'meta.per_page',
                20
            )
            ->assertJsonPath(
                'meta.total',
                21
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_public_can_view_published_page_by_slug(): void
    {
        $page = $this->createPage([
            'title' => [
                'en' => 'About Us',
                'fa' => 'درباره ما',
            ],
            'slug' => 'about-us',
            'content' => [
                'en' => '<h1>Welcome to TKB</h1>',
                'fa' => '<h1>به TKB خوش آمدید</h1>',
            ],
            'status' => PageStatus::PUBLISHED,
        ]);

        $response = $this->getJson(
            "/api/pages/{$page->slug}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $page->id
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
                'data.content',
                '<h1>Welcome to TKB</h1>'
            )
            ->assertJsonPath(
                'data.status',
                'published'
            )
            ->assertJsonMissing([
                'data.created_by',
            ])
            ->assertJsonMissing([
                'data.updated_by',
            ]);
    }

    public function test_public_cannot_view_draft_page_by_slug(): void
    {
        $page = $this->createPage([
            'title' => [
                'en' => 'Draft Page',
                'fa' => 'صفحه پیش‌نویس',
            ],
            'slug' => 'draft-page',
            'status' => PageStatus::DRAFT,
        ]);

        $response = $this->getJson(
            "/api/pages/{$page->slug}"
        );

        $response->assertNotFound();
    }

    public function test_public_cannot_view_non_existing_page(): void
    {
        $response = $this->getJson(
            '/api/pages/does-not-exist'
        );

        $response->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Data Exposure
    |--------------------------------------------------------------------------
    */

    public function test_public_page_response_contains_only_public_fields(): void
    {
        $page = $this->createPage([
            'title' => [
                'en' => 'Privacy Policy',
                'fa' => 'سیاست حفظ حریم خصوصی',
            ],
            'slug' => 'privacy-policy',
            'content' => [
                'en' => 'Privacy content',
                'fa' => 'محتوای حریم خصوصی',
            ],
            'status' => PageStatus::PUBLISHED,
        ]);

        $response = $this->getJson(
            "/api/pages/{$page->slug}"
        );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'locale',
                    'direction',
                    'title',
                    'slug',
                    'content',
                    'status',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_public_pages_do_not_include_draft_pages_even_with_other_published_pages(): void
    {
        $publishedOne = $this->createPage([
            'title' => [
                'en' => 'Published One',
                'fa' => 'منتشر شده یک',
            ],
            'slug' => 'published-one',
            'status' => PageStatus::PUBLISHED,
        ]);

        $publishedTwo = $this->createPage([
            'title' => [
                'en' => 'Published Two',
                'fa' => 'منتشر شده دو',
            ],
            'slug' => 'published-two',
            'status' => PageStatus::PUBLISHED,
        ]);

        $this->createPage([
            'title' => [
                'en' => 'Hidden Draft One',
                'fa' => 'پیش‌نویس مخفی یک',
            ],
            'slug' => 'hidden-draft-one',
            'status' => PageStatus::DRAFT,
        ]);

        $this->createPage([
            'title' => [
                'en' => 'Hidden Draft Two',
                'fa' => 'پیش‌نویس مخفی دو',
            ],
            'slug' => 'hidden-draft-two',
            'status' => PageStatus::DRAFT,
        ]);

        $response = $this->getJson(
            '/api/pages'
        );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath(
                'data.0.id',
                $publishedOne->id
            )
            ->assertJsonPath(
                'data.1.id',
                $publishedTwo->id
            );

        $response
            ->assertJsonMissing([
                'slug' => 'hidden-draft-one',
            ])
            ->assertJsonMissing([
                'slug' => 'hidden-draft-two',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

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

                    'slug' => 'test-page-' .
                        uniqid(),

                    'content' => [
                        'en' => 'Test page content.',
                        'fa' => 'محتوای صفحه تست.',
                    ],

                    'status' =>
                        PageStatus::PUBLISHED,

                    'created_by' => null,

                    'updated_by' => null,
                ],
                $attributes
            )
        );
    }
}
