<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Services\ShoppingAssistant\OnDemandProductResearchRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OnDemandProductResearchRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('shopping_assistant_research.enabled', true);
    }

    private function product(): Product
    {
        $category = Category::factory()->create([
            'is_active' => true,
        ]);

        return Product::factory()->create([
            'category_id' => $category->id,
            'brand' => 'Zwilling',
            'name' => ['en' => 'Zwilling Joy Plus 24 cm'],
            'slug' => 'zwilling-joy-plus-24-cm',
            'status' => ProductStatus::ACTIVE,
        ]);
    }

    public function test_first_registration_only_creates_one_request(): void
    {
        $product = $this->product();
        $registry = app(OnDemandProductResearchRegistry::class);

        $this->assertTrue($registry->register($product));
        $this->assertFalse($registry->register($product));

        $this->assertDatabaseCount(
            'shopping_assistant_research_requests',
            1
        );

        $this->assertDatabaseHas(
            'shopping_assistant_research_requests',
            [
                'product_id' => $product->id,
                'status' => 'pending',
                'attempts' => 0,
            ]
        );
    }

    public function test_completed_or_failed_research_is_not_automatically_requeued(): void
    {
        $product = $this->product();
        $registry = app(OnDemandProductResearchRegistry::class);

        $this->assertTrue($registry->register($product));

        foreach (['completed', 'failed', 'needs_review'] as $status) {
            DB::table('shopping_assistant_research_requests')
                ->where('product_id', $product->id)
                ->update(['status' => $status]);

            $this->assertFalse($registry->register($product));

            $this->assertDatabaseHas(
                'shopping_assistant_research_requests',
                [
                    'product_id' => $product->id,
                    'status' => $status,
                ]
            );
        }

        $this->assertDatabaseCount(
            'shopping_assistant_research_requests',
            1
        );
    }

    public function test_disabled_or_unpublished_products_are_not_registered(): void
    {
        $product = $this->product();
        $registry = app(OnDemandProductResearchRegistry::class);

        config()->set('shopping_assistant_research.enabled', false);

        $this->assertFalse($registry->register($product));

        config()->set('shopping_assistant_research.enabled', true);

        $product->update(['status' => ProductStatus::INACTIVE]);

        $this->assertFalse($registry->register($product));

        $this->assertDatabaseCount(
            'shopping_assistant_research_requests',
            0
        );
    }
}