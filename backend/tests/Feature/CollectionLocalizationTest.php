<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Product;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CollectionLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_collection_api_returns_english_translation(): void
    {
        $collection = Collection::create([
            'name' => [
                'en' => 'Featured Collection',
                'fa' => 'مجموعه منتخب',
            ],
            'slug' => 'featured-collection',
            'description' => [
                'en' => 'Featured products collection.',
                'fa' => 'مجموعه محصولات منتخب.',
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this
            ->withHeader(
                'Accept-Language',
                'en'
            )
            ->getJson(
                "/api/collections/{$collection->slug}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Featured Collection'
            )
            ->assertJsonPath(
                'data.description',
                'Featured products collection.'
            )
            ->assertJsonPath(
                'data.slug',
                'featured-collection'
            );
    }

    public function test_collection_api_returns_persian_translation(): void
    {
        $collection = Collection::create([
            'name' => [
                'en' => 'Featured Collection',
                'fa' => 'مجموعه منتخب',
            ],
            'slug' => 'featured-collection',
            'description' => [
                'en' => 'Featured products collection.',
                'fa' => 'مجموعه محصولات منتخب.',
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this
            ->withHeader(
                'Accept-Language',
                'fa'
            )
            ->getJson(
                "/api/collections/{$collection->slug}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'مجموعه منتخب'
            )
            ->assertJsonPath(
                'data.description',
                'مجموعه محصولات منتخب.'
            )
            ->assertJsonPath(
                'data.slug',
                'featured-collection'
            );
    }

    public function test_collection_api_falls_back_to_english_when_persian_translation_is_missing(): void
    {
        $collection = Collection::create([
            'name' => [
                'en' => 'Featured Collection',
            ],
            'slug' => 'featured-collection',
            'description' => [
                'en' => 'Featured products collection.',
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this
            ->withHeader(
                'Accept-Language',
                'fa'
            )
            ->getJson(
                "/api/collections/{$collection->slug}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Featured Collection'
            )
            ->assertJsonPath(
                'data.description',
                'Featured products collection.'
            );
    }

    public function test_collection_detail_returns_localized_products(): void
    {
        $collection = Collection::create([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف آشپزی',
            ],
            'slug' => 'cookware',
            'description' => [
                'en' => 'Cookware collection.',
                'fa' => 'مجموعه ظروف آشپزی.',
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $product = Product::factory()->create([
            'name' => [
                'en' => 'Kitchen Pan',
                'fa' => 'تابه آشپزخانه',
            ],
            'slug' => 'kitchen-pan',
        ]);

        $collection->products()->attach(
            $product->id
        );

        $this
            ->withHeader(
                'Accept-Language',
                'fa'
            )
            ->getJson(
                "/api/collections/{$collection->slug}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'ظروف آشپزی'
            )
            ->assertJsonPath(
                'data.products.0.name',
                'تابه آشپزخانه'
            );
    }

    public function test_collection_slug_is_language_independent(): void
    {
        $collection = Collection::create([
            'name' => [
                'en' => 'Luxury Cookware',
                'fa' => 'ظروف آشپزی لوکس',
            ],
            'slug' => 'luxury-cookware',
            'description' => [
                'en' => 'Premium cookware.',
                'fa' => 'ظروف آشپزی ممتاز.',
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this
            ->withHeader(
                'Accept-Language',
                'en'
            )
            ->getJson(
                "/api/collections/{$collection->slug}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.slug',
                'luxury-cookware'
            );

        $this
            ->withHeader(
                'Accept-Language',
                'fa'
            )
            ->getJson(
                "/api/collections/{$collection->slug}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.slug',
                'luxury-cookware'
            );
    }

    public function test_collection_index_returns_localized_collection_names(): void
    {
        Collection::create([
            'name' => [
                'en' => 'Featured Collection',
                'fa' => 'مجموعه منتخب',
            ],
            'slug' => 'featured-collection',
            'description' => [
                'en' => 'Featured products.',
                'fa' => 'محصولات منتخب.',
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this
            ->withHeader(
                'Accept-Language',
                'fa'
            )
            ->getJson('/api/collections')
            ->assertOk()
            ->assertJsonPath(
                'data.0.name',
                'مجموعه منتخب'
            )
            ->assertJsonPath(
                'data.0.slug',
                'featured-collection'
            );
    }
}
