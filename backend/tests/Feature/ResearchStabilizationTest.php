<?php

namespace Tests\Feature;

use App\Services\ShoppingAssistant\GapGptProductResearch;
use App\Services\ShoppingAssistant\ResearchFailureClassifier;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ResearchStabilizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config()->set([
            'shopping_assistant_research.enabled' => true,
            'shopping_assistant.api_key' => 'offline-key',
            'shopping_assistant.base_url' => 'https://api.gapgpt.app/v1',
            'shopping_assistant.model' => 'gpt-6.1-terra',
        ]);
    }

    public function test_provider_usage_with_compatible_field_names_is_recorded(): void
    {
        Http::fake(['api.gapgpt.app/*' => Http::response([
            'status' => 'completed',
            'output' => [
                ['type' => 'web_search_call', 'status' => 'completed'],
                ['type' => 'message', 'status' => 'completed', 'content' => [[
                    'type' => 'output_text',
                    'text' => '{"model":"A-100","facts":[]}',
                    'annotations' => [['type' => 'url_citation', 'url' => 'https://manufacturer.example/a100']],
                ]]],
            ],
            'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 45],
        ])]);
        $result = app(GapGptProductResearch::class)->research('Example', 'A-100');
        $this->assertSame(120, $result['usage']['input_tokens']);
        $this->assertSame(45, $result['usage']['output_tokens']);
        $this->assertSame('unverified', $result['verification_status']);
        Http::assertSentCount(1);
    }

    public static function stages(): array
    {
        return [
            ['Research provider failed: HTTP 401', 'provider_transport'],
            ['Research provider failed: HTTP 429', 'provider_transport'],
            ['Research response is incomplete.', 'provider_response'],
            ['Research lacks completed search and cited evidence.', 'provider_response'],
            ['Invalid manufacturer or model.', 'product_identity'],
            ['unknown sensitive text', 'research_execution'],
        ];
    }

    #[DataProvider('stages')]
    public function test_safe_failure_stage(string $error, string $expected): void
    {
        $this->assertSame($expected, ResearchFailureClassifier::stage(new RuntimeException($error)));
    }

    public function test_bad_http_status_does_not_leak_provider_body(): void
    {
        Http::fake(['api.gapgpt.app/*' => Http::response(['error' => 'secret-provider-content'], 429)]);
        try {
            app(GapGptProductResearch::class)->research('Example', 'A-100');
            $this->fail('Expected research to fail closed.');
        } catch (RuntimeException $e) {
            $this->assertSame('provider_rate_limited', ResearchFailureClassifier::classify($e));
            $this->assertStringNotContainsString('secret-provider-content', $e->getMessage());
        }
    }
}