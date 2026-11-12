<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Services\ShoppingAssistant\OnDemandProductResearchRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OnDemandResearchWorkerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config()->set([
            'shopping_assistant_research.enabled' => true,
            'shopping_assistant_research.max_daily_requests' => 10,
            'shopping_assistant.api_key' => 'fake-test-key',
            'shopping_assistant.model' => 'gpt-6.1-terra',
            'shopping_assistant.base_url' => 'https://api.gapgpt.app/v1',
        ]);
    }

    private function queueProduct(): Product
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => ['en' => 'Zwilling Joy Plus 24 cm'],
            'brand' => 'Zwilling',
            'status' => ProductStatus::ACTIVE,
        ]);
        $this->assertTrue(app(OnDemandProductResearchRegistry::class)->register($product));
        return $product;
    }

    public function test_research_is_cached_without_automatically_approving_specs(): void
    {
        $product = $this->queueProduct();
        Http::fake(['api.gapgpt.app/*' => Http::response([
            'status' => 'completed', 'output' => [
                ['type' => 'web_search_call', 'status' => 'completed'],
                ['type' => 'message', 'status' => 'completed', 'content' => [[
                    'type' => 'output_text',
                    'text' => 'Zwilling Joy Plus 24 cm is described in a listing.',
                    'annotations' => [['type' => 'url_citation', 'url' => 'https://example.com/joy-plus-24']],
                ]]],
            ], 'usage' => ['input_tokens' => 50, 'output_tokens' => 20],
        ])]);
        $this->artisan('assistant:research-pending', ['--once' => true])->assertSuccessful();
        $this->assertDatabaseHas('shopping_assistant_research_requests', [
            'product_id' => $product->id, 'status' => 'needs_review', 'attempts' => 1,
        ]);
        $record = DB::table('shopping_assistant_research_requests')->where('product_id', $product->id)->first();
        $result = json_decode($record->result, true);
        $this->assertSame(50, $result['research']['usage']['input_tokens']);
        $this->assertDatabaseCount('shopping_assistant_product_knowledge', 0);
        $this->artisan('assistant:research-pending', ['--once' => true])->assertSuccessful();
        Http::assertSentCount(1);
    }

    public function test_disabled_worker_does_not_research(): void
    {
        $product = $this->queueProduct();
        config()->set('shopping_assistant_research.enabled', false);
        $this->artisan('assistant:research-pending')->assertSuccessful();
        Http::assertSentCount(0);
        $this->assertDatabaseHas('shopping_assistant_research_requests', [
            'product_id' => $product->id, 'status' => 'pending', 'attempts' => 0,
        ]);
    }

    public function test_no_daily_cap_fails_closed_without_claim_or_request(): void
    {
        $product = $this->queueProduct();
        config()->set('shopping_assistant_research.max_daily_requests', 0);
        $this->artisan('assistant:research-pending')->assertSuccessful();
        Http::assertSentCount(0);
        $this->assertDatabaseHas('shopping_assistant_research_requests', [
            'product_id' => $product->id, 'status' => 'pending', 'attempts' => 0,
        ]);
    }

    public function test_daily_cap_stops_second_request_even_if_first_provider_fails(): void
    {
        config()->set('shopping_assistant_research.max_daily_requests', 1);
        $first = $this->queueProduct();
        $category = Category::factory()->create(['is_active' => true]);
        $second = Product::factory()->create([
            'category_id' => $category->id,
            'name' => ['en' => 'Zwilling Test Second 18 cm'],
            'brand' => 'Zwilling',
            'status' => ProductStatus::ACTIVE,
        ]);
        $this->assertTrue(app(OnDemandProductResearchRegistry::class)->register($second));
        Http::fake(['api.gapgpt.app/*' => Http::response([], 503)]);

        $this->artisan('assistant:research-pending')->assertSuccessful();
        $this->artisan('assistant:research-pending')->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertDatabaseHas('shopping_assistant_research_requests', [
            'product_id' => $first->id, 'status' => 'failed', 'attempts' => 1,
        ]);
        $this->assertDatabaseHas('shopping_assistant_research_requests', [
            'product_id' => $second->id, 'status' => 'pending', 'attempts' => 0,
        ]);
    }

    public function test_daily_cap_counts_previous_attempts_not_only_completions(): void
    {
        $product = $this->queueProduct();
        config()->set('shopping_assistant_research.max_daily_requests', 1);
        DB::table('shopping_assistant_research_requests')
            ->where('product_id', $product->id)
            ->update(['status' => 'failed', 'attempts' => 1, 'started_at' => now()]);
        DB::table('shopping_assistant_usage_logs')->insert([
            'conversation_fingerprint' => hash('sha256', 'research:test-previous'),
            'provider' => 'gapgpt_research',
            'model' => 'gpt-6.1-terra',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'estimated_cost_usd' => null,
            'status' => 'attempted',
            'created_at' => now(),
        ]);
        $another = $this->queueProduct();
        $this->artisan('assistant:research-pending')->assertSuccessful();
        Http::assertSentCount(0);
        $this->assertDatabaseHas('shopping_assistant_research_requests', [
            'product_id' => $another->id, 'status' => 'pending', 'attempts' => 0,
        ]);
    }

    public function test_quota_ledger_survives_product_deletion(): void
    {
        config()->set('shopping_assistant_research.max_daily_requests', 1);
        $first = $this->queueProduct();
        Http::fake(['api.gapgpt.app/*' => Http::response([], 503)]);
        $this->artisan('assistant:research-pending', ['--once' => true])->assertSuccessful();
        $this->assertDatabaseHas('shopping_assistant_usage_logs', [
            'provider' => 'gapgpt_research', 'status' => 'attempted',
            'estimated_cost_usd' => null,
        ]);
        $first->forceDelete();
        $second = $this->queueProduct();
        $this->artisan('assistant:research-pending')->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertDatabaseHas('shopping_assistant_research_requests', [
            'product_id' => $second->id, 'status' => 'pending', 'attempts' => 0,
        ]);
    }

    public function test_provider_failure_is_recorded_without_automatic_retry(): void
    {
        $product = $this->queueProduct();
        Http::fake(['api.gapgpt.app/*' => Http::response([], 503)]);
        $this->artisan('assistant:research-pending', ['--once' => true])->assertSuccessful();
        $this->assertDatabaseHas('shopping_assistant_research_requests', [
            'product_id' => $product->id, 'status' => 'failed', 'attempts' => 1,
        ]);
        $this->artisan('assistant:research-pending', ['--once' => true])->assertSuccessful();
        Http::assertSentCount(1);
    }
}