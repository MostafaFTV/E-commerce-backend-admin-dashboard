<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\ProductSearchRelevanceRanker;
use PHPUnit\Framework\TestCase;

class ProductSearchRelevanceRankerTest extends TestCase
{
    public function test_exact_match_ranks_before_prefix_and_contains(): void
    {
        $exact =
            $this->product(
                'Coffee Maker'
            );

        $prefix =
            $this->product(
                'Coffee Maker Pro'
            );

        $contains =
            $this->product(
                'Premium Coffee Maker Bundle'
            );

        $this->assertLessThan(
            ProductSearchRelevanceRanker::score(
                $prefix,
                'Coffee Maker'
            ),
            ProductSearchRelevanceRanker::score(
                $exact,
                'Coffee Maker'
            )
        );

        $this->assertLessThan(
            ProductSearchRelevanceRanker::score(
                $contains,
                'Coffee Maker'
            ),
            ProductSearchRelevanceRanker::score(
                $prefix,
                'Coffee Maker'
            )
        );
    }

    public function test_spacing_variants_can_rank_as_exact_matches(): void
    {
        $product = new Product();

        $product->setRawAttributes([
            'name' => json_encode(
                [
                    'en' => 'Tea Maker',
                    'fa' => 'چای‌ساز',
                ],
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            ),
            'brand' => null,
            'slug' => 'tea-maker',
        ]);

        $this->assertSame(
            0,
            ProductSearchRelevanceRanker::score(
                $product,
                'چای ساز'
            )
        );
    }

    public function test_fuzzy_match_ranks_below_direct_matches(): void
    {
        $direct =
            $this->product(
                'Electric Kettle'
            );

        $fuzzy =
            $this->product(
                'Electric Kettle'
            );

        $directScore =
            ProductSearchRelevanceRanker::score(
                $direct,
                'Electric Kettle'
            );

        $fuzzyScore =
            ProductSearchRelevanceRanker::score(
                $fuzzy,
                'Electirc Kettle'
            );

        $this->assertLessThan(
            $fuzzyScore,
            $directScore
        );

        $this->assertSame(
            100,
            $fuzzyScore
        );
    }

    public function test_unrelated_product_receives_no_match_score(): void
    {
        $product =
            $this->product(
                'Stand Mixer'
            );

        $this->assertSame(
            1000,
            ProductSearchRelevanceRanker::score(
                $product,
                'Kitchen Knife'
            )
        );
    }

    private function product(
        string $name
    ): Product {
        $product = new Product();

        $product->setRawAttributes([
            'name' => json_encode(
                [
                    'en' => $name,
                    'fa' => null,
                ],
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            ),
            'brand' => null,
            'slug' => strtolower(
                str_replace(
                    ' ',
                    '-',
                    $name
                )
            ),
        ]);

        return $product;
    }
}