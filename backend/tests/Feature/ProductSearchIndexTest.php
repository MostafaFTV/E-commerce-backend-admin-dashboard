<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_search_index_is_created_automatically_on_save(): void
    {
        $product = Product::factory()->create([
            'name' => [
                'en' => 'Electric Kettle',
                'fa' => 'کتری برقی',
            ],
            'brand' => 'Test Brand',
            'slug' => 'electric-kettle',
        ]);

        $product->refresh();

        $this->assertSame(
            'electric kettle کتری برقی test brand electric kettle',
            $product->getRawOriginal('search_text')
        );

        $this->assertSame(
            'electrickettleکتریبرقیtestbrandelectrickettle',
            $product->getRawOriginal('search_compact')
        );
    }

    public function test_product_search_index_is_rebuilt_when_searchable_fields_change(): void
    {
        $product = Product::factory()->create([
            'name' => [
                'en' => 'Tea Maker',
                'fa' => 'چای‌ساز',
            ],
            'brand' => 'Old Brand',
            'slug' => 'tea-maker',
        ]);

        $product->update([
            'name' => [
                'en' => 'Smart Tea Maker',
                'fa' => 'چای ساز هوشمند',
            ],
            'brand' => 'New Brand',
        ]);

        $product->refresh();

        $searchText =
            (string) $product->getRawOriginal(
                'search_text'
            );

        $searchCompact =
            (string) $product->getRawOriginal(
                'search_compact'
            );

        $this->assertStringContainsString(
            'smart tea maker',
            $searchText
        );

        $this->assertStringContainsString(
            'چای ساز هوشمند',
            $searchText
        );

        $this->assertStringContainsString(
            'new brand',
            $searchText
        );

        $this->assertStringContainsString(
            'چایسازهوشمند',
            $searchCompact
        );

        $this->assertStringNotContainsString(
            'old brand',
            $searchText
        );
    }
}