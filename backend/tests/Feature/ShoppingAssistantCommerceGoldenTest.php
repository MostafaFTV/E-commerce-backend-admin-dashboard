<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ShoppingAssistant\ShoppingCatalogTools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShoppingAssistantCommerceGoldenTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(Category $category, string $name, string $brand, bool $featured = false): Product
    {
        return Product::factory()->create([
            'category_id' => $category->id,
            'name' => ['en' => $name, 'fa' => $name],
            'brand' => $brand,
            'status' => ProductStatus::ACTIVE,
            'is_featured' => $featured,
        ]);
    }

    public function test_persian_and_english_brand_aliases_return_verified_brand_products(): void
    {
        $category = Category::factory()->create();
        $first = $this->makeProduct($category, 'Zwilling Motion Knife', 'Zwilling');
        $second = $this->makeProduct($category, 'Zwilling Gourmet Knife', 'Zwilling');
        $this->makeProduct($category, 'Other Knife', 'Other');
        $tools = app(ShoppingCatalogTools::class);

        foreach (['محصولات زویلینگ', 'Zwilling products'] as $message) {
            $result = $tools->answerContext($message, 'fa');
            // Separate SQL/catalog retrieval from the card-display decision.
            $this->assertEqualsCanonicalizing(
                [$first->id, $second->id],
                array_column($result['context']['products'], 'id'),
                'The verified brand products must first be found in catalog context.'
            );
            $ids = array_column($result['product_cards'], 'id');
            $this->assertEqualsCanonicalizing([$first->id, $second->id], $ids);
            $this->assertSame('search_products', $result['tool']);
        }
    }

    public function test_brand_matching_happens_before_top_one_hundred_candidate_cap(): void
    {
        $category = Category::factory()->create();
        $target = $this->makeProduct($category, 'Zwilling Pro Knife', 'Zwilling');
        for ($i = 0; $i < 105; $i++) {
            $this->makeProduct($category, 'Other Item ' . $i, 'Other', true);
        }

        $result = app(ShoppingCatalogTools::class)->answerContext('محصولات زویلینگ', 'fa');
        $this->assertSame([$target->id], array_column($result['product_cards'], 'id'));
    }

    public function test_first_brand_discovery_limits_display_to_three_without_losing_brand_identity(): void
    {
        $category = Category::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->makeProduct($category, 'Zwilling Item ' . $i, 'Zwilling');
        }

        $result = app(ShoppingCatalogTools::class)->answerContext('Zwilling products', 'en');
        $this->assertCount(3, $result['product_cards']);
        foreach ($result['product_cards'] as $card) {
            $this->assertSame('Zwilling', $card['brand']);
            $this->assertArrayNotHasKey('price', $card);
        }
    }

    public function test_made_to_order_remains_orderable_and_inactive_products_are_hidden(): void
    {
        $category = Category::factory()->create();
        $mto = $this->makeProduct($category, 'Zwilling Special Edition', 'Zwilling');
        ProductVariant::factory()->create([
            'product_id' => $mto->id,
            'status' => VariantStatus::MADE_TO_ORDER,
        ]);
        $hidden = $this->makeProduct($category, 'Zwilling Hidden', 'Zwilling');
        $hidden->update(['status' => ProductStatus::INACTIVE]);

        $result = app(ShoppingCatalogTools::class)->answerContext('Zwilling products', 'en');
        $this->assertSame([$mto->id], array_column($result['product_cards'], 'id'));
        $this->assertSame('made_to_order', $result['product_cards'][0]['availability']);
        $this->assertTrue($result['product_cards'][0]['available']);
    }

    public function test_previous_product_order_is_preserved_for_comparison_context(): void
    {
        $category = Category::factory()->create();
        $a = $this->makeProduct($category, 'Zwilling Alpha', 'Zwilling');
        $b = $this->makeProduct($category, 'Zwilling Beta', 'Zwilling');
        $result = app(ShoppingCatalogTools::class)->answerContext(
            'compare these products', 'en', [$b->id, $a->id]
        );
        $this->assertSame([$b->id, $a->id], array_column($result['context']['products'], 'id'));
    }

    public function test_standalone_known_brand_shows_cards_without_generic_recommendation_keywords(): void
    {
        $category = Category::factory()->create();
        $product = $this->makeProduct($category, 'Zwilling Standalone Knife', 'Zwilling');

        $result = app(ShoppingCatalogTools::class)->answerContext('Zwilling', 'en');

        $this->assertSame('search_products', $result['tool']);
        $this->assertSame([$product->id], array_column($result['context']['products'], 'id'));
        $this->assertSame([$product->id], array_column($result['product_cards'], 'id'));
    }

    public function test_unknown_brand_word_does_not_force_catalog_cards(): void
    {
        $category = Category::factory()->create();
        $this->makeProduct($category, 'Zwilling Test Item', 'Zwilling');

        $result = app(ShoppingCatalogTools::class)->answerContext('UnknownBrandInvented', 'en');

        $this->assertNull($result['tool']);
        $this->assertSame([], $result['product_cards']);
    }
}
