<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ShoppingAssistant\GapGptShoppingAssistantProvider;
use App\Services\ShoppingAssistant\ShoppingAssistantResponseContract;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ShoppingAssistantRecoveryGoldenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'shopping_assistant.api_key' => 'offline-test-key',
            'shopping_assistant.base_url' => 'https://api.gapgpt.app/v1',
            'shopping_assistant.model' => 'gpt-6.1-terra',
            'shopping_assistant.endpoint' => 'chat_completions',
            'shopping_assistant.timeout_seconds' => 15,
            'shopping_assistant.input_cost_per_million_tokens' => '0.10',
            'shopping_assistant.output_cost_per_million_tokens' => '0.50',
        ]);
        Http::preventStrayRequests();
    }

    public function test_malformed_v2_provider_response_is_recovered_without_another_dispatch(): void
    {
        Http::fake(['api.gapgpt.app/*' => Http::response([
            'choices' => [[
                'message' => ['content' => '{"response_version":2,"message":"oops","recommended_product_ids":['],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 77, 'completion_tokens' => 11],
        ], 200)]);

        $answer = app(GapGptShoppingAssistantProvider::class)->complete(
            'fa', [['role' => 'user', 'content' => 'دو محصول پیشنهاد بده']],
            ['tool' => 'search_products', 'products' => []], 200
        );

        $this->assertSame(77, $answer['input_tokens']);
        $this->assertSame(11, $answer['output_tokens']);
        $this->assertSame([], $answer['recommended_product_ids']);
        $this->assertStringNotContainsString('response_version', $answer['content']);
        $this->assertTrue($answer['_provider_diagnostics']['response_contract_recovered']);
        Http::assertSentCount(1);
    }

    public function test_plain_text_and_unrelated_json_are_not_misclassified(): void
    {
        $this->assertFalse(ShoppingAssistantResponseContract::looksLikeBrokenContract('A normal recommendation.'));
        $this->assertFalse(ShoppingAssistantResponseContract::looksLikeBrokenContract('{"example": true}'));
        $this->assertTrue(ShoppingAssistantResponseContract::looksLikeBrokenContract('{"response_version":2,"message":'));
    }

    public function test_bad_ids_do_not_become_trusted_cards(): void
    {
        $raw = '{"response_version":2,"message":"hi","recommended_product_ids":[999,"bad"]}';
        $this->assertNull(ShoppingAssistantResponseContract::parse($raw));
        $this->assertTrue(ShoppingAssistantResponseContract::looksLikeBrokenContract($raw));
        $this->assertSame([], ShoppingAssistantResponseContract::verifiedCards([999], [
            ['id' => 1, 'name' => 'Trusted'],
        ]));
    }
}
