<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ShoppingAssistant\ShoppingAssistantContextualFollowUp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ShoppingAssistantConversationGoldenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('shopping_assistant.driver', 'mock');
        Http::preventStrayRequests();
    }

    public function test_short_reply_to_previous_question_is_not_generic_fallback(): void
    {
        $first = $this->postJson('/api/assistant/chat?locale=fa', [
            'message' => 'سلام',
        ])->assertOk();

        $conversation = $first->json('data.conversation');
        $this->assertIsArray($conversation);
        $this->assertSame(0, $first->json('data.usage.input_tokens'));

        $second = $this->postJson('/api/assistant/chat?locale=fa', [
            'message' => 'نوشیدنی',
            'conversation_id' => $conversation['id'],
            'session_token' => $conversation['session_token'],
        ])->assertOk()->assertJsonPath('data.tool', 'conversation_follow_up');

        $this->assertStringNotContainsString(
            'می‌توانم در انتخاب محصولات آشپزخانه',
            (string) $second->json('data.messages.1.content')
        );
        $this->assertSame('mock', $second->json('data.usage.provider'));
        Http::assertSentCount(0);
    }

    public function test_english_short_reply_continues_conversation(): void
    {
        $first = $this->postJson('/api/assistant/chat?locale=en', [
            'message' => 'Hello',
        ])->assertOk();

        $conversation = $first->json('data.conversation');
        $this->postJson('/api/assistant/chat?locale=en', [
            'message' => 'Coffee',
            'conversation_id' => $conversation['id'],
            'session_token' => $conversation['session_token'],
        ])->assertOk()->assertJsonPath('data.tool', 'conversation_follow_up');

        Http::assertSentCount(0);
    }

    public function test_first_message_is_not_treated_as_follow_up(): void
    {
        $response = $this->postJson('/api/assistant/chat?locale=fa', [
            'message' => 'نوشیدنی',
        ])->assertOk();

        $this->assertNull($response->json('data.tool'));
        Http::assertSentCount(0);
    }

    public function test_contextual_follow_up_reaches_real_provider_adapter_with_history(): void
    {
        $first = $this->postJson('/api/assistant/chat?locale=fa', [
            'message' => 'سلام',
        ])->assertOk();

        $conversation = $first->json('data.conversation');
        config()->set([
            'shopping_assistant.driver' => 'gapgpt',
            'shopping_assistant.api_key' => 'offline-test-key',
        ]);
        Http::fake(fn () => Http::response([
            'choices' => [[
                'message' => ['content' => 'متوجه شدم که دنبال وسیله‌ای برای نوشیدنی هستید. بیشتر چای می‌نوشید یا قهوه؟'],
            ]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 25],
        ]));

        $response = $this->postJson('/api/assistant/chat?locale=fa', [
            'message' => 'نوشیدنی',
            'conversation_id' => $conversation['id'],
            'session_token' => $conversation['session_token'],
        ])->assertOk()->assertJsonPath('data.tool', 'conversation_follow_up');

        $this->assertStringContainsString(
            'چای می‌نوشید یا قهوه',
            (string) $response->json('data.messages.1.content')
        );
        Http::assertSentCount(1);
        Http::assertSent(static function ($request): bool {
            $messages = $request['messages'] ?? [];
            $serialized = json_encode($messages, JSON_UNESCAPED_UNICODE);

            return is_string($serialized)
                && str_contains($serialized, 'نوشیدنی')
                && str_contains($serialized, 'دنبال چه محصول یا کاربردی');
        });
    }

    public function test_does_not_continue_without_an_actual_previous_question(): void
    {
        $this->assertFalse(ShoppingAssistantContextualFollowUp::isAnswerToLastQuestion(
            'نوشیدنی',
            [['role' => 'assistant', 'content' => 'این محصولات را ببینید.']]
        ));
        $this->assertFalse(ShoppingAssistantContextualFollowUp::isAnswerToLastQuestion(
            'نوشیدنی',
            []
        ));
        $this->assertFalse(ShoppingAssistantContextualFollowUp::isAnswerToLastQuestion(
            'قیمتش چنده؟',
            [['role' => 'assistant', 'content' => 'کدام محصول؟']]
        ));
    }

    public function test_does_not_override_explicit_page_navigation(): void
    {
        $first = $this->postJson('/api/assistant/chat?locale=fa', [
            'message' => 'سلام',
        ])->assertOk();

        $conversation = $first->json('data.conversation');
        $this->postJson('/api/assistant/chat?locale=fa', [
            'message' => 'لینک تماس با ما رو بده',
            'conversation_id' => $conversation['id'],
            'session_token' => $conversation['session_token'],
        ])->assertOk()->assertJsonPath('data.tool', null);

        Http::assertSentCount(0);
    }
}
