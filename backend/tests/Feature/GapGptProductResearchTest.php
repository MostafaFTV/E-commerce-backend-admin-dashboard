<?php

namespace Tests\Feature;

use App\Services\ShoppingAssistant\GapGptProductResearch;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GapGptProductResearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config()->set([
            'shopping_assistant_research.enabled' => true,
            'shopping_assistant.api_key' => 'offline-test-key',
            'shopping_assistant.base_url' => 'https://api.gapgpt.app/v1',
            'shopping_assistant.model' => 'gpt-6.1-terra',
        ]);
    }

    public function test_completed_web_search_returns_unverified_cited_evidence(): void
    {
        $url = 'https://www.example.com/products/model-123';

        Http::fake([
            'api.gapgpt.app/*' => Http::response([
                'status' => 'completed',
                'output' => [
                    [
                        'type' => 'web_search_call',
                        'status' => 'completed',
                    ],
                    [
                        'type' => 'message',
                        'status' => 'completed',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => 'The official document lists 4.8 L.',
                                'annotations' => [
                                    [
                                        'type' => 'url_citation',
                                        'url' => $url,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'usage' => [
                    'input_tokens' => 200,
                    'output_tokens' => 40,
                ],
            ]),
        ]);

        $result = app(GapGptProductResearch::class)
            ->research('Example', 'MODEL-123');

        $this->assertSame('unverified', $result['verification_status']);
        $this->assertSame([$url], $result['citation_urls']);
        $this->assertSame(200, $result['usage']['input_tokens']);

        Http::assertSent(fn ($request) =>
            $request->url() === 'https://api.gapgpt.app/v1/responses'
            && $request['tools'][0]['type'] === 'web_search'
            && $request['model'] === 'gpt-6.1-terra'
        );
    }

    public function test_disabled_research_does_not_send_requests(): void
    {
        config()->set('shopping_assistant_research.enabled', false);

        try {
            app(GapGptProductResearch::class)
                ->research('Example', 'MODEL-123');

            $this->fail('Disabled research must fail closed.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Product research is disabled.',
                $exception->getMessage()
            );
        }

        Http::assertSentCount(0);
    }

    public function test_uncited_answer_is_rejected(): void
    {
        Http::fake([
            'api.gapgpt.app/*' => Http::response([
                'status' => 'completed',
                'output' => [
                    [
                        'type' => 'web_search_call',
                        'status' => 'completed',
                    ],
                    [
                        'type' => 'message',
                        'status' => 'completed',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => 'Uncited technical claim.',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cited evidence');

        app(GapGptProductResearch::class)
            ->research('Example', 'MODEL-123');
    }
}