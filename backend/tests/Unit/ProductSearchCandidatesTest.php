<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\ProductSearchIndexer;
use PHPUnit\Framework\TestCase;

class ProductSearchCandidatesTest extends TestCase
{
    public function test_it_returns_each_searchable_product_value_separately(): void
    {
        $product = new Product();

        $product->setRawAttributes([
            'name' => json_encode(
                [
                    'en' => 'Electric Kettle',
                    'fa' => 'کتری برقی',
                ],
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            ),
            'brand' => 'Example Brand',
            'slug' => 'electric-kettle',
        ]);

        $this->assertSame(
            [
                'Electric Kettle',
                'کتری برقی',
                'Example Brand',
                'electric-kettle',
            ],
            ProductSearchIndexer::candidates(
                $product
            )
        );
    }

    public function test_it_ignores_empty_searchable_values(): void
    {
        $product = new Product();

        $product->setRawAttributes([
            'name' => json_encode(
                [
                    'en' => 'Cookware Set',
                    'fa' => null,
                ],
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            ),
            'brand' => null,
            'slug' => 'cookware-set',
        ]);

        $this->assertSame(
            [
                'Cookware Set',
                'cookware-set',
            ],
            ProductSearchIndexer::candidates(
                $product
            )
        );
    }
}