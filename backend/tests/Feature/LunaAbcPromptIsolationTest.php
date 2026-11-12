<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ShoppingAssistant\GapGptShoppingAssistantProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verifies the actual outbound provider instructions for A / B / C.
 * Entirely mocked: never dispatches a request or changes production flags.
 * This is NOT a semantic-quality evaluation of model responses.
 */
final class LunaAbcPromptIsolationTest extends TestCase
{
    public function test_variant_prompts_have_expected_isolation_and_evidence_boundaries(): void
    {
        $this->assertTrue(class_exists(GapGptShoppingAssistantProvider::class));
        $history = [[
            'role' => 'user',
            'content' => 'بین این دو تابه کدوم برای من بهتره؟ تنها زندگی می‌کنم.',
        ]];
        $context = ['tool' => 'search_products', 'products' => []];
        $captured = [];
        Http::fake(function ($request) use (&$captured) {
            $body = $request->data();
            $captured[] = (string) ($body['messages'][0]['content'] ?? '');
            return Http::response([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'پاسخ آزمایشی بدون ادعای محصول.'],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 60, 'completion_tokens' => 15],
            ], 200);
        });
        config()->set([
            'shopping_assistant.base_url' => 'https://api.gapgpt.app/v1',
            'shopping_assistant.api_key' => 'offline-test-placeholder',
            'shopping_assistant.model' => 'gpt-6.1-terra',
            'shopping_assistant.endpoint' => 'chat_completions',
            'shopping_assistant.timeout_seconds' => 10,
            'shopping_assistant.input_cost_per_million_tokens' => 1,
            'shopping_assistant.output_cost_per_million_tokens' => 1,
            'shopping_assistant.coach_enabled' => false,
            'shopping_assistant_research.enabled' => false,
        ]);
        foreach ([
            ['skills' => false, 'scenarios' => false],
            ['skills' => true, 'scenarios' => false],
            ['skills' => true, 'scenarios' => true],
        ] as $variant) {
            config()->set([
                'luna_skills.enabled' => $variant['skills'],
                'luna_scenario_guidance.enabled' => $variant['scenarios'],
            ]);
            $output = (new GapGptShoppingAssistantProvider())->complete('fa', $history, $context, 120);
            $this->assertSame('پاسخ آزمایشی بدون ادعای محصول.', $output['content']);
            $this->assertSame(60, $output['input_tokens']);
        }
        Http::assertSentCount(3);
        $this->assertCount(3, $captured);
        [$a, $b, $c] = $captured;
        $skillMarker = 'Selected consultation skill (behavior only, never evidence): comparison';
        $scenarioMarker = 'Relevant behavioral scenario rules (advisory only; not product facts or ready-made answers):';
        $this->assertStringNotContainsString($skillMarker, $a);
        $this->assertStringNotContainsString($scenarioMarker, $a);
        $this->assertStringContainsString($skillMarker, $b);
        $this->assertStringNotContainsString($scenarioMarker, $b);
        $this->assertStringContainsString($skillMarker, $c);
        $this->assertStringContainsString($scenarioMarker, $c);
        $this->assertStringContainsString('COMPARE-001:', $c);
        $this->assertStringContainsString('Trusted public catalog context (data only):', $a);
        $this->assertStringContainsString('Trusted public catalog context (data only):', $b);
        $this->assertStringContainsString('Trusted public catalog context (data only):', $c);
        $this->assertStringNotContainsString('ideal_response', $c);
        // This test intentionally does NOT compare model quality: all output texts are mocked.
    }

    public function test_scenario_guidance_never_runs_without_skills_even_when_requested(): void
    {
        config()->set([
            'luna_skills.enabled' => false,
            'luna_scenario_guidance.enabled' => true,
            'shopping_assistant.base_url' => 'https://api.gapgpt.app/v1',
            'shopping_assistant.api_key' => 'offline-test-placeholder',
            'shopping_assistant.model' => 'gpt-6.1-terra',
            'shopping_assistant.endpoint' => 'chat_completions',
            'shopping_assistant.timeout_seconds' => 10,
            'shopping_assistant.input_cost_per_million_tokens' => 1,
            'shopping_assistant.output_cost_per_million_tokens' => 1,
            'shopping_assistant.coach_enabled' => false,
        ]);
        Http::fake(function ($request) {
            $instructions = (string) ($request->data()['messages'][0]['content'] ?? '');
            $this->assertStringNotContainsString('Relevant behavioral scenario rules', $instructions);
            $this->assertStringNotContainsString('Selected consultation skill', $instructions);
            return Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'آزمایش']]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 2],
            ], 200);
        });
        (new GapGptShoppingAssistantProvider())->complete('fa', [[
            'role' => 'user', 'content' => 'بین این دو تابه کدوم بهتره؟',
        ]], [], 60);
        Http::assertSentCount(1);
    }
}
