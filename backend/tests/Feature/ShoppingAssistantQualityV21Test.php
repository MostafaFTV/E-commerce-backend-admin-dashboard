<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Services\ShoppingAssistant\GapGptShoppingAssistantProvider;
use App\Services\ShoppingAssistant\ShoppingAssistantConsultationPlanner;
use App\Services\ShoppingAssistant\ShoppingAssistantConsultationMemory;
use App\Services\ShoppingAssistant\ShoppingAssistantResponseContract;
use App\Services\ShoppingAssistant\ShoppingAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ShoppingAssistantQualityV21Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_real_production_style_prose_followed_by_json_returns_only_customer_text(): void
    {
        $message = 'برای خانوادهٔ ۴ نفره، این دو گزینه را بررسی می‌کنیم.';
        $raw = $message . "\n\n" . json_encode([
            'response_version' => 2,
            'message' => $message,
            'recommended_product_ids' => [357, 356],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $parsed = ShoppingAssistantResponseContract::parse($raw);
        $this->assertNotNull($parsed);
        $this->assertSame($message, $parsed['content']);
        $this->assertSame([357, 356], $parsed['recommended_product_ids']);
        $this->assertStringNotContainsString('response_version', $parsed['content']);
    }

    public function test_inconsistent_or_broken_embedded_contract_fails_closed(): void
    {
        $mixed = "گزینه اول بهتر است.\n" . json_encode([
            'response_version' => 2,
            'message' => 'گزینه دوم بهتر است.',
            'recommended_product_ids' => [356],
        ], JSON_UNESCAPED_UNICODE);
        $this->assertNull(ShoppingAssistantResponseContract::parse($mixed));
        $this->assertTrue(ShoppingAssistantResponseContract::looksLikeBrokenContract($mixed));

        $broken = "پیشنهاد من این است.\n{" . '"response_version":2,"message":"bad"';
        $this->assertNull(ShoppingAssistantResponseContract::parse($broken));
        $this->assertTrue(ShoppingAssistantResponseContract::looksLikeBrokenContract($broken));
        $this->assertFalse(ShoppingAssistantResponseContract::looksLikeBrokenContract('یک پاسخ معمولی درباره سرویس قابلمه'));
    }

    public function test_decision_stage_does_not_invent_product_evidence(): void
    {
        $planner = ShoppingAssistantConsultationPlanner::context([
            'stage' => 'clarify',
            'preferences' => ['usage' => 'daily'],
            'last_question' => 'سبک بودن یا دوام؟',
        ], 'هردوش برام مهمه چی رو پیشنهاد میکنی');
        $this->assertSame('decide', $planner['stage']);
        $this->assertTrue($planner['decision_requested']);
        $this->assertStringContainsString('Never invent', $planner['rule']);
        $this->assertSame('daily', $planner['explicit_customer_preferences']['usage']);
    }

    public function test_cookware_needs_survive_bounded_conversation_history(): void
    {
        $state = ShoppingAssistantConsultationMemory::observe([], 'ما خانواده ۴ نفره هستیم.', 'fa');
        $state = ShoppingAssistantConsultationMemory::observe($state, 'اجاق برقی', 'fa');
        $state = ShoppingAssistantConsultationMemory::observe($state, 'صفحه ای', 'fa');
        $state['last_question'] = 'سبک‌تر باشد یا دوام بیشتری داشته باشد؟';
        $state = ShoppingAssistantConsultationMemory::observe($state, 'هردوش برام مهمه', 'fa');

        $this->assertSame('4', $state['preferences']['household_size']);
        $this->assertSame('electric_flat', $state['preferences']['hob_type']);
        $this->assertSame('lightweight,durable', $state['preferences']['cookware_priorities']);
        $this->assertArrayNotHasKey('product_specifications', $state['preferences']);
    }

    public function test_provider_strips_embedded_json_and_requests_single_format(): void
    {
        config()->set([
            'shopping_assistant.api_key' => 'offline-only',
            'shopping_assistant.base_url' => 'https://api.gapgpt.app/v1',
            'shopping_assistant.endpoint' => 'chat_completions',
            'shopping_assistant.model' => 'gpt-6.1-terra',
            'shopping_assistant.timeout_seconds' => 15,
            'shopping_assistant.input_cost_per_million_tokens' => '0.10',
            'shopping_assistant.output_cost_per_million_tokens' => '0.50',
        ]);
        $message = 'بر اساس اطلاعات فعلی انتخاب قطعی ممکن نیست.';
        Http::fake(['api.gapgpt.app/*' => Http::response([
            'choices' => [[
                'finish_reason' => 'stop',
                'message' => ['content' => $message . "\n" . json_encode([
                    'response_version' => 2,
                    'message' => $message,
                    'recommended_product_ids' => [357],
                ], JSON_UNESCAPED_UNICODE)],
            ]],
            'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 80],
        ])]);

        $result = app(GapGptShoppingAssistantProvider::class)->complete(
            'fa', [['role' => 'user', 'content' => 'کدوم بهتره؟']],
            ['tool' => 'conversation_follow_up', 'products' => [['id' => 357, 'name' => 'Zwilling Simplify']]],
            900
        );
        $this->assertSame($message, $result['content']);
        $this->assertSame([357], $result['recommended_product_ids']);
        $this->assertSame(200, $result['input_tokens']);
        $this->assertSame(80, $result['output_tokens']);
        $this->assertSame(
            GapGptShoppingAssistantProvider::PROMPT_VERSION,
            $result['_provider_diagnostics']['prompt_version']
        );
        Http::assertSentCount(1);
        Http::assertSent(static function ($request): bool {
            $instructions = $request['messages'][0]['content'] ?? '';
            return str_contains($instructions, 'EXACTLY ONE output format')
                && str_contains($instructions, 'Buying decision policy')
                && str_contains($instructions, 'Product evidence policy')
                && ($request['max_tokens'] ?? null) === 900;
        });
    }

    public function test_short_follow_up_rehydrates_only_live_catalog_products(): void
    {
        config()->set('shopping_assistant.driver', 'mock');
        $category = Category::factory()->create();
        $first = Product::factory()->create([
            'category_id' => $category->id,
            'name' => ['fa' => 'زویلینگ موشن', 'en' => 'Zwilling Motion'],
            'brand' => 'Zwilling',
            'status' => ProductStatus::ACTIVE,
        ]);
        $second = Product::factory()->create([
            'category_id' => $category->id,
            'name' => ['fa' => 'زویلینگ سیمپلیفای', 'en' => 'Zwilling Simplify'],
            'brand' => 'Zwilling',
            'status' => ProductStatus::ACTIVE,
        ]);

        $result = app(ShoppingAssistantService::class)->respond(
            'هردوش برام مهمه',
            'fa',
            [['role' => 'assistant', 'content' => 'سبک بودن یا دوام براتون مهم‌تره؟']],
            'test-session-v21',
            [$first->id, $second->id]
        );
        $this->assertSame('conversation_follow_up', $result['tool']);
        $this->assertSame([$first->id, $second->id], $result['remembered_product_ids']);
        Http::assertSentCount(0);
    }

    public function test_model_supplied_ids_are_not_accepted_without_trusted_cards(): void
    {
        $this->assertSame([], ShoppingAssistantResponseContract::verifiedCards([357], []));
        $this->assertSame([], ShoppingAssistantResponseContract::verifiedCards(
            [357], [['id' => 356, 'name' => 'Zwilling Motion']]
        ));
    }
}
