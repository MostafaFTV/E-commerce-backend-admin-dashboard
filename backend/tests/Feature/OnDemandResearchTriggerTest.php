<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Services\ShoppingAssistant\OnDemandResearchTrigger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnDemandResearchTriggerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'shopping_assistant_research.enabled' => true,
            'shopping_assistant.driver' => 'gapgpt',
        ]);
    }

    private function product(): Product
    {
        $category = Category::factory()->create([
            'is_active' => true,
        ]);

        return Product::factory()->create([
            'category_id' => $category->id,
            'name' => ['en' => 'Zwilling Joy Plus 24 cm'],
            'brand' => 'Zwilling',
            'slug' => 'zwilling-joy-plus-24-cm',
            'status' => ProductStatus::ACTIVE,
        ]);
    }

    public function test_first_technical_question_registers_once(): void
    {
        $product = $this->product();
        $context = ['products' => [['id' => $product->id]]];
        $trigger = app(OnDemandResearchTrigger::class);

        $this->assertTrue($trigger->registerIfNeeded(
            'What material is this product made of?',
            'get_product_details',
            $context
        ));

        $this->assertFalse($trigger->registerIfNeeded(
            'What is its capacity?',
            'get_product_details',
            $context
        ));

        $this->assertDatabaseCount(
            'shopping_assistant_research_requests',
            1
        );
    }

    public function test_broad_discovery_and_multiple_products_do_not_trigger(): void
    {
        $product = $this->product();
        $trigger = app(OnDemandResearchTrigger::class);

        $this->assertFalse($trigger->registerIfNeeded(
            'Show me kitchen products',
            'search_products',
            ['products' => [['id' => $product->id]]]
        ));

        $this->assertFalse($trigger->registerIfNeeded(
            'Compare the materials',
            'get_product_details',
            ['products' => [
                ['id' => $product->id],
                ['id' => 999999],
            ]]
        ));

        $this->assertDatabaseCount(
            'shopping_assistant_research_requests',
            0
        );
    }

    public function test_verified_knowledge_prevents_new_research(): void
    {
        $product = $this->product();

        $context = [
            'products' => [['id' => $product->id]],
            'verified_product_knowledge' => [[
                'product_id' => $product->id,
                'facts' => [[
                    'name' => 'Material',
                    'value' => 'Stainless steel',
                ]],
            ]],
        ];

        $this->assertFalse(
            app(OnDemandResearchTrigger::class)->registerIfNeeded(
                'What material does it use?',
                'get_product_details',
                $context
            )
        );

        $this->assertDatabaseCount(
            'shopping_assistant_research_requests',
            0
        );
    }

    public function test_persian_question_is_supported_but_disabled_research_is_not(): void
    {
        $product = $this->product();
        $trigger = app(OnDemandResearchTrigger::class);
        $context = ['products' => [['id' => $product->id]]];

        config()->set('shopping_assistant_research.enabled', false);

        $this->assertFalse($trigger->registerIfNeeded(
            'جنس این محصول چیست؟',
            'get_product_details',
            $context
        ));

        config()->set('shopping_assistant_research.enabled', true);

        $this->assertTrue($trigger->registerIfNeeded(
            'جنس این محصول چیست؟',
            'get_product_details',
            $context
        ));
    }

    public function test_first_explicit_search_question_registers_but_broad_search_does_not(): void
    {
        $product = $this->product();
        $trigger = app(OnDemandResearchTrigger::class);
        $context = ['products' => [[
            'id' => $product->id, 'name' => 'Zwilling Joy Plus 24 cm',
        ]]];
        $this->assertFalse($trigger->registerIfNeeded(
            'What is the material of the cookware?', 'search_products', $context
        ));
        $this->assertTrue($trigger->registerIfNeeded(
            'What is the material of Zwilling Joy Plus 24 cm?', 'search_products', $context
        ));
        $this->assertDatabaseCount('shopping_assistant_research_requests', 1);
    }
}