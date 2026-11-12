<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSeoTest extends TestCase
{
    use RefreshDatabase;

    private function createSeoProduct(): Product
    {
        $category = Category::factory()->create([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف آشپزی',
            ],
        ]);

        return Product::factory()->create([
            'category_id' => $category->id,
            'slug' => 'seo-test-pan',
            'name' => [
                'en' => 'Steel Pan',
                'fa' => 'تابه استیل',
            ],
            'seo_title' => [
                'en' => 'Premium Steel Pan',
                'fa' => 'تابه استیل لوکس',
            ],
            'seo_description' => [
                'en' => 'Discover premium steel pans.',
                'fa' => 'خرید انواع تابه استیل لوکس.',
            ],
            'seo_image' => '/images/seo/steel-pan.webp',
        ]);
    }

    public function test_seo_translations_are_stored_in_database(): void
    {
        $product = $this->createSeoProduct();

        $this->assertSame(
            [
                'en' => 'Premium Steel Pan',
                'fa' => 'تابه استیل لوکس',
            ],
            json_decode(
                $product->getRawOriginal('seo_title'),
                true
            )
        );

        $this->assertSame(
            [
                'en' => 'Discover premium steel pans.',
                'fa' => 'خرید انواع تابه استیل لوکس.',
            ],
            json_decode(
                $product->getRawOriginal('seo_description'),
                true
            )
        );

        $this->assertSame(
            '/images/seo/steel-pan.webp',
            $product->seo_image
        );
    }

    public function test_public_api_returns_english_seo_metadata(): void
    {
        $product = $this->createSeoProduct();

        $this
            ->withHeader('Accept-Language', 'en')
            ->getJson("/api/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath(
                'data.seo_title',
                'Premium Steel Pan'
            )
            ->assertJsonPath(
                'data.seo_description',
                'Discover premium steel pans.'
            )
            ->assertJsonPath(
                'data.seo_image',
                '/images/seo/steel-pan.webp'
            );
    }

    public function test_public_api_returns_persian_seo_metadata(): void
    {
        $product = $this->createSeoProduct();

        $this
            ->withHeader('Accept-Language', 'fa')
            ->getJson("/api/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath(
                'data.seo_title',
                'تابه استیل لوکس'
            )
            ->assertJsonPath(
                'data.seo_description',
                'خرید انواع تابه استیل لوکس.'
            )
            ->assertJsonPath(
                'data.seo_image',
                '/images/seo/steel-pan.webp'
            );
    }
}