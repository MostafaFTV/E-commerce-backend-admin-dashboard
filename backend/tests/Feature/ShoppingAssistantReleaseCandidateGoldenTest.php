<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ShoppingAssistant\GapGptShoppingAssistantProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class ShoppingAssistantReleaseCandidateGoldenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'shopping_assistant.enabled' => true,
            'shopping_assistant.driver' => 'gapgpt',
            'shopping_assistant.api_key' => 'offline-test-only-not-a-secret',
            'shopping_assistant.base_url' => 'https://api.gapgpt.app/v1',
            'shopping_assistant.endpoint' => 'chat_completions',
            'shopping_assistant.model' => 'gpt-6.1-terra',
            'shopping_assistant.timeout_seconds' => 15,
            'shopping_assistant.input_cost_per_million_tokens' => '0.10',
            'shopping_assistant.output_cost_per_million_tokens' => '0.50',
        ]);
        Http::preventStrayRequests();
    }

    public function test_finglish_prompt_policy_is_present_without_moving_customer_input_into_system_instructions(): void
    {
        $customerInput = 'ye ghahve saz koochik mikham';
        Http::fake(['api.gapgpt.app/*' => Http::response([
            'choices' => [[
                'message' => ['content' => 'برای قهوه‌ساز کوچک، نوع نوشیدنی مهم است.'],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 30, 'completion_tokens' => 12],
        ], 200)]);

        $result = app(GapGptShoppingAssistantProvider::class)->complete(
            'fa', [['role' => 'user', 'content' => $customerInput]],
            ['tool' => 'conversation_follow_up', 'products' => []], 300
        );
        $this->assertStringContainsString('قهوه‌ساز', $result['content']);
        Http::assertSent(function ($request) use ($customerInput): bool {
            $messages = $request->data()['messages'] ?? [];
            $systemInstructions = $messages[0]['content'] ?? '';
            return count($messages) === 2
                && ($messages[0]['role'] ?? '') === 'system'
                && str_contains($systemInstructions, 'reply in fluent Persian script')
                && str_contains($systemInstructions, 'Price-display boundary: prices are intentionally not displayed in the current catalog.')
                && ! str_contains($systemInstructions, 'GREET-001')
                && ! str_contains($systemInstructions, $customerInput)
                && ($messages[1]['content'] ?? '') === $customerInput;
        });
        Http::assertSentCount(1);
    }

    public function test_versions_are_observable_without_logging_customer_text_or_provider_secret(): void
    {
        $secretCanary = 'PRIVATE_CANARY_DO_NOT_LOG_5q72';
        Log::spy();
        Http::fake(['api.gapgpt.app/*' => Http::response([
            'choices' => [[
                'message' => ['content' => 'Here is general shopping guidance.'],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 45, 'completion_tokens' => 7],
        ], 200)]);

        $result = app(GapGptShoppingAssistantProvider::class)->complete(
            'en', [['role' => 'user', 'content' => $secretCanary]],
            ['tool' => 'conversation_follow_up', 'products' => []], 300
        );

        $this->assertSame(GapGptShoppingAssistantProvider::PROMPT_VERSION, $result['_provider_diagnostics']['prompt_version']);
        $this->assertSame(GapGptShoppingAssistantProvider::SCHEMA_VERSION, $result['_provider_diagnostics']['schema_version']);
        $this->assertSame(45, $result['input_tokens']);
        $this->assertSame(7, $result['output_tokens']);
        Log::shouldHaveReceived('log')->once()->withArgs(function ($level, $message, $context) use ($secretCanary): bool {
            $encoded = json_encode([$message, $context], JSON_UNESCAPED_UNICODE);
            return $level === 'debug'
                && ($context['prompt_version'] ?? null) === GapGptShoppingAssistantProvider::PROMPT_VERSION
                && ($context['schema_version'] ?? null) === GapGptShoppingAssistantProvider::SCHEMA_VERSION
                && ! str_contains($encoded, $secretCanary)
                && ! str_contains($encoded, 'offline-test-only-not-a-secret')
                && ! array_key_exists('session_token', $context)
                && ! array_key_exists('content', $context);
        });
        Http::assertSentCount(1);
    }

    public function test_end_to_end_chat_session_is_server_owned_and_persists_through_two_api_turns(): void
    {
        config()->set('shopping_assistant.driver', 'mock');
        $first = $this->postJson('/api/assistant/chat?locale=fa', [
            'message' => 'سلام، آشپزخانه کوچیکی دارم',
        ])->assertOk();
        $session = $first->json('data.conversation');
        $this->assertSame(60, $session['expires_in_minutes']);
        $this->assertNotEmpty($session['id']);
        $this->assertNotEmpty($session['session_token']);

        $second = $this->postJson('/api/assistant/chat?locale=fa', [
            'message' => 'قهوه',
            'conversation_id' => $session['id'],
            'session_token' => $session['session_token'],
        ])->assertOk();
        $this->assertSame($session['id'], $second->json('data.conversation.id'));
        $state = Cache::get('shopping-assistant:session:' . $session['id']);
        $this->assertIsArray($state);
        $this->assertSame('coffee', $state['consultation']['preferences']['drink_type']);
        $this->assertArrayNotHasKey('consultation', $second->json('data'));
        Http::assertSentCount(0);
    }

    public function test_provider_failure_keeps_existing_session_unchanged_without_automatic_retry(): void
    {
        config()->set('shopping_assistant.driver', 'mock');
        $first = $this->postJson('/api/assistant/chat?locale=en', ['message' => 'Find a kettle'])->assertOk();
        $session = $first->json('data.conversation');
        $key = 'shopping-assistant:session:' . $session['id'];
        $before = Cache::get($key);

        config()->set('shopping_assistant.driver', 'gapgpt');
        Http::fake(['api.gapgpt.app/*' => Http::response(['error' => ['message' => 'simulated outage']], 503)]);
        $this->postJson('/api/assistant/chat?locale=en', [
            'message' => 'Find a kettle',
            'conversation_id' => $session['id'],
            'session_token' => $session['session_token'],
        ])->assertStatus(503);
        $this->assertSame($before, Cache::get($key));
        Http::assertSentCount(1);
    }
}
