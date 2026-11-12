<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_api_returns_english_translation(): void
    {
        $category = Category::factory()->create([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف آشپزی',
            ],
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,

            'name' => [
                'en' => 'Stainless Steel Pan',
                'fa' => 'تابه استیل',
            ],

            'short_description' => [
                'en' => 'Premium stainless steel pan',
                'fa' => 'تابه استیل باکیفیت',
            ],

            'description' => [
                'en' => 'Designed for modern kitchens.',
                'fa' => 'طراحی شده برای آشپزخانه‌های مدرن.',
            ],

            'slug' => 'stainless-steel-pan',
        ]);

        $this->withHeader(
            'Accept-Language',
            'en'
        )
            ->getJson(
                "/api/products/{$product->slug}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Stainless Steel Pan'
            )
            ->assertJsonPath(
                'data.short_description',
                'Premium stainless steel pan'
            )
            ->assertJsonPath(
                'data.description',
                'Designed for modern kitchens.'
            );
    }

    public function test_product_api_returns_persian_translation(): void
    {
        $category = Category::factory()->create([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف آشپزی',
            ],
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,

            'name' => [
                'en' => 'Stainless Steel Pan',
                'fa' => 'تابه استیل',
            ],

            'short_description' => [
                'en' => 'Premium stainless steel pan',
                'fa' => 'تابه استیل باکیفیت',
            ],

            'description' => [
                'en' => 'Designed for modern kitchens.',
                'fa' => 'طراحی شده برای آشپزخانه‌های مدرن.',
            ],

            'slug' => 'stainless-steel-pan',
        ]);

        $this->withHeader(
            'Accept-Language',
            'fa'
        )
            ->getJson(
                "/api/products/{$product->slug}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'تابه استیل'
            )
            ->assertJsonPath(
                'data.short_description',
                'تابه استیل باکیفیت'
            )
            ->assertJsonPath(
                'data.description',
                'طراحی شده برای آشپزخانه‌های مدرن.'
            );
    }

    public function test_product_api_falls_back_to_english_when_persian_translation_is_missing(): void
    {
        $product = Product::factory()->create([
            'name' => [
                'en' => 'Stainless Steel Pan',
            ],

            'short_description' => [
                'en' => 'Premium stainless steel pan',
            ],

            'description' => [
                'en' => 'Designed for modern kitchens.',
            ],

            'slug' => 'fallback-pan',
        ]);

        $this->withHeader(
            'Accept-Language',
            'fa'
        )
            ->getJson(
                "/api/products/{$product->slug}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Stainless Steel Pan'
            )
            ->assertJsonPath(
                'data.short_description',
                'Premium stainless steel pan'
            )
            ->assertJsonPath(
                'data.description',
                'Designed for modern kitchens.'
            );
    }

    public function test_product_detail_returns_localized_category_name(): void
    {
        $category = Category::factory()->create([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف آشپزی',
            ],
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,

            'name' => [
                'en' => 'Stainless Steel Pan',
                'fa' => 'تابه استیل',
            ],

            'slug' => 'localized-category-pan',
        ]);

        $this->withHeader(
            'Accept-Language',
            'en'
        )
            ->getJson(
                "/api/products/{$product->slug}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.category.name',
                'Cookware'
            );

        $this->withHeader(
            'Accept-Language',
            'fa'
        )
            ->getJson(
                "/api/products/{$product->slug}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.category.name',
                'ظروف آشپزی'
            );
    }

    public function test_product_slug_remains_language_independent(): void
    {
        $product = Product::factory()->create([
            'name' => [
                'en' => 'Stainless Steel Pan',
                'fa' => 'تابه استیل',
            ],

            'slug' => 'stainless-steel-pan',
        ]);

        $this->withHeader(
            'Accept-Language',
            'en'
        )
            ->getJson(
                "/api/products/{$product->slug}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.slug',
                'stainless-steel-pan'
            );

        $this->withHeader(
            'Accept-Language',
            'fa'
        )
            ->getJson(
                "/api/products/{$product->slug}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.slug',
                'stainless-steel-pan'
            );
    }
}
