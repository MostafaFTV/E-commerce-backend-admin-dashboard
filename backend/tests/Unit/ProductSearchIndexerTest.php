<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\ProductSearchIndexer;
use PHPUnit\Framework\TestCase;

class ProductSearchIndexerTest extends TestCase
{
    public function test_it_builds_generic_search_index_from_all_product_names_brand_and_slug(): void
    {
        $product = new Product();

        $product->setRawAttributes([
            'name' => json_encode(
                [
                    'en' => 'Coffee Maker',
                    'fa' => 'قهوه‌ساز',
                ],
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            ),
            'brand' => 'DeLonghi',
            'slug' => 'delonghi-coffee-maker',
        ]);

        $index =
            ProductSearchIndexer::build(
                $product
            );

        $this->assertSame(
            'coffee maker قهوه ساز delonghi delonghi coffee maker',
            $index['search_text']
        );

        $this->assertSame(
            'coffeemakerقهوهسازdelonghidelonghicoffeemaker',
            $index['search_compact']
        );
    }

    public function test_it_normalizes_arabic_persian_variants_generically(): void
    {
        $product = new Product();

        $product->setRawAttributes([
            'name' => json_encode(
                [
                    'en' => 'Kitchen Tool',
                    'fa' => 'كالاي يک',
                ],
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            ),
            'brand' => null,
            'slug' => 'kitchen-tool',
        ]);

        $index =
            ProductSearchIndexer::build(
                $product
            );

        $this->assertStringContainsString(
            'کالای یک',
            $index['search_text']
        );

        $this->assertStringContainsString(
            'کالاییک',
            $index['search_compact']
        );
    }
}