<?php

namespace Tests\Feature;

use App\Enums\Locale;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryLocalizationTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function test_category_api_returns_english_translation_for_english_locale(): void
    {
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

    public function test_category_api_returns_persian_translation_for_persian_locale(): void
    {
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
                'ظروف پخت‌وپز'
            )
            ->assertJsonPath(
                'data.0.description',
                'ظروف پخت‌وپز لوکس'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Fallback
    |--------------------------------------------------------------------------
    */

    public function test_category_api_falls_back_to_english_when_persian_translation_is_missing(): void
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
                'data.0.name',
                'Cookware'
            )
            ->assertJsonPath(
                'data.0.description',
                'Luxury cookware'
            )
            ->assertJsonPath(
                'data.0.locale',
                'fa'
            )
            ->assertJsonPath(
                'data.0.direction',
                'rtl'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Tree
    |--------------------------------------------------------------------------
    */

    public function test_category_tree_returns_translated_root_and_children(): void
    {
        $parent = $this->createCategory([
            'name' => [
                'en' => 'Kitchen',
                'fa' => 'آشپزخانه',
            ],
            'slug' => 'kitchen',
        ]);

        $child = $this->createCategory([
            'parent_id' => $parent->id,
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف پخت‌وپز',
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
                'data.0.id',
                $parent->id
            )
            ->assertJsonPath(
                'data.0.name',
                'آشپزخانه'
            )
            ->assertJsonPath(
                'data.0.children.0.id',
                $child->id
            )
            ->assertJsonPath(
                'data.0.children.0.name',
                'ظروف پخت‌وپز'
            )
            ->assertJsonPath(
                'data.0.children.0.direction',
                'rtl'
            );
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
                'fa' => 'ظروف پخت‌وپز',
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
                'data.0.id',
                $category->id
            )
            ->assertJsonPath(
                'data.0.slug',
                'cookware'
            );
    }

    public function test_category_management_fields_remain_locale_independent(): void
    {
        $category = $this->createCategory([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف پخت‌وپز',
            ],
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
    | Parent Translation
    |--------------------------------------------------------------------------
    */

    public function test_category_parent_name_is_translated(): void
    {
        $parent = $this->createCategory([
            'name' => [
                'en' => 'Kitchen',
                'fa' => 'آشپزخانه',
            ],
            'slug' => 'kitchen',
        ]);

        $child = $this->createCategory([
            'parent_id' => $parent->id,
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف پخت‌وپز',
            ],
            'slug' => 'cookware',
        ]);

        $category = Category::query()
            ->with('parent')
            ->findOrFail($child->id);

        $response = $this
            ->withHeader(
                'Accept-Language',
                Locale::FA->value
            )
            ->getJson('/api/categories');

        /*
        |--------------------------------------------------------------------------
        | The public index does not currently eager-load parent.
        | This test therefore verifies the Resource behavior directly.
        |--------------------------------------------------------------------------
        */

        $resource = new \App\Http\Resources\CategoryResource(
            $category
        );

        $array = $resource->toArray(
            request()
        );

        $this->assertSame(
            'آشپزخانه',
            $array['parent']['name']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

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
                    'is_active' => true,
                    'sort_order' => 0,
                    'created_by' => null,
                    'updated_by' => null,
                ],
                $attributes
            )
        );
    }
}
