<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ShoppingAssistant\GapGptShoppingAssistantProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Prompt-contract regression only: no claim about real model answer quality. */
final class LunaConsultationQualityPromptTest extends TestCase
{
    public function test_non_price_guidance_is_grounded_and_does_not_require_staff_referral(): void
    {
        config()->set([
            'shopping_assistant.base_url' => 'https://api.gapgpt.app/v1',
            'shopping_assistant.api_key' => 'fake-no-live-key',
            'shopping_assistant.model' => 'gpt-6.1-terra',
            'shopping_assistant.endpoint' => 'chat_completions',
            'shopping_assistant.coach_enabled' => false,
            'shopping_assistant_research.enabled' => false,
            'luna_skills.enabled' => true,
            'luna_scenario_guidance.enabled' => false,
        ]);
        $prompts = [];
        Http::fake(function ($request) use (&$prompts) {
            $prompts[] = (string) ($request->data()['messages'][0]['content'] ?? '');
            return Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'پاسخ تستی.']]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 3],
            ], 200);
        });
        $context = ['tool' => 'search_products', 'products' => []];
        $history = [['role' => 'user', 'content' => 'یک چاقوی سرآشپز برای استفاده روزمره پیشنهاد بده']];
        $result = (new GapGptShoppingAssistantProvider())->complete('fa', $history, $context, 120);
        $this->assertSame('پاسخ تستی.', $result['content']);
        $this->assertCount(1, $prompts);
        $p = $prompts[0];
        $this->assertStringContainsString('prices are intentionally not displayed in the current catalog', $p);
        $this->assertStringContainsString('must NOT derail a non-price consultation', $p);
        $this->assertStringContainsString('select the best defensible item by its exact catalog name', $p);
        $this->assertStringContainsString('recommend the strongest defensible named match', $p);
        $this->assertStringContainsString('Never fabricate products', $p);
        $this->assertStringContainsString('Trusted public catalog context (data only):', $p);
        $this->assertStringContainsString('do not quote, infer, or estimate amounts', $p);
        Http::assertSentCount(1);
    }

    public function test_price_boundary_is_present_even_when_skills_are_disabled(): void
    {
        config()->set([
            'shopping_assistant.base_url' => 'https://api.gapgpt.app/v1',
            'shopping_assistant.api_key' => 'fake-no-live-key',
            'shopping_assistant.model' => 'gpt-6.1-terra',
            'shopping_assistant.endpoint' => 'chat_completions',
            'shopping_assistant.coach_enabled' => false,
            'luna_skills.enabled' => false,
            'luna_scenario_guidance.enabled' => false,
        ]);
        Http::fake(function ($request) {
            $p = (string) ($request->data()['messages'][0]['content'] ?? '');
            $this->assertStringContainsString('If the customer explicitly asks for price', $p);
            $this->assertStringContainsString('Never route a normal product-selection question to staff', $p);
            $this->assertStringNotContainsString('Selected consultation skill (behavior only', $p);
            return Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'تست']]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 2],
            ], 200);
        });
        (new GapGptShoppingAssistantProvider())->complete('fa', [
            ['role' => 'user', 'content' => 'قیمتش چنده؟'],
        ], ['tool' => 'get_pricing', 'products' => []], 120);
        Http::assertSentCount(1);
    }
}
