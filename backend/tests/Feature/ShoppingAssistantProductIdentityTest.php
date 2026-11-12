<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class ShoppingAssistantProductIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        RateLimiter::clear('shopping-assistant-ip-minute:127.0.0.1');
        RateLimiter::clear('shopping-assistant-ip-hour:127.0.0.1');
    }

    public function test_farsi_ordinal_choice_keeps_name_card_and_verified_link_consistent(): void
    {
        config()->set([
            'shopping_assistant.driver' => 'gapgpt',
            'shopping_assistant.api_key' => 'offline-test-key',
            'shopping_assistant.endpoint' => 'chat_completions',
            'shopping_assistant.daily_budget_usd' => '1.00',
            'shopping_assistant.monthly_budget_usd' => '5.00',
        ]);

        $category = Category::factory()->create([
            'name' => ['en' => 'Cookware', 'fa' => 'قابلمه و تابه'],
        ]);
        $motion = Product::factory()->create([
            'category_id' => $category->id,
            'name' => [
                'en' => 'Zwilling Motion Set',
                'fa' => 'سرویس زویلینگ موشن',
            ],
            'slug' => 'zwilling-motion-set',
            'status' => ProductStatus::ACTIVE,
        ]);
        $simplify = Product::factory()->create([
            'category_id' => $category->id,
            'name' => [
                'en' => 'Zwilling Simplify Set',
                'fa' => 'سرویس زویلینگ سیمپلیفای',
            ],
            'slug' => 'zwilling-simplify-set',
            'status' => ProductStatus::ACTIVE,
        ]);

        // A first-turn model response presents cards in the reverse order
        // of catalog IDs, as happened in the live Farsi evaluation.
        Http::fake([
            'api.gapgpt.app/*' => Http::sequence()
                ->push([
                    'choices' => [['message' => ['content' =>
                        'سرویس زویلینگ سیمپلیفای و سرویس زویلینگ موشن در کاتالوگ ثبت شده‌اند.']]],
                    'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 25],
                ])
                ->push([
                    // The model incorrectly interprets "دومی" from old prose.
                    'choices' => [['message' => ['content' =>
                        'منظورتان سرویس زویلینگ سیمپلیفای است و اطلاعات دیگری ندارم.']]],
                    'usage' => ['prompt_tokens' => 110, 'completion_tokens' => 24],
                ]),
        ]);

        $first = $this->postJson('/api/assistant/chat?locale=fa', [
            'message' => 'چند مدل قابلمه و تابه معرفی کن.',
        ])->assertOk();

        $cards = $first->json('data.product_cards');
        $this->assertCount(2, $cards);
        $this->assertSame([$simplify->id, $motion->id], array_column($cards, 'id'));

        $conversation = $first->json('data.conversation');
        $second = $this->postJson('/api/assistant/chat?locale=fa', [
            'message' => 'دومی رو بیشتر توضیح بده و لینکش رو بفرست.',
            'conversation_id' => $conversation['id'],
            'session_token' => $conversation['session_token'],
        ])->assertOk()->assertJsonPath('data.product_cards.0.id', $motion->id);

        $reply = $second->json('data.messages.1.content');
        $this->assertIsString($reply);
        $this->assertStringContainsString('سرویس زویلینگ موشن', $reply);
        $this->assertStringNotContainsString('سرویس زویلینگ سیمپلیفای', $reply);
        $this->assertStringContainsString('/products/zwilling-motion-set', $reply);
        $this->assertCount(1, $second->json('data.product_cards'));

        Http::assertSentCount(2);
        Http::assertSent(function ($request) use ($motion): bool {
            $payload = json_decode($request->body(), true);

            if (! is_array($payload)) {
                return false;
            }

            $instructions = $payload['messages'][0]['content'] ?? null;

            if (! is_string($instructions)) {
                return false;
            }

            $marker = 'Trusted public catalog context (data only): ';
            $position = strrpos($instructions, $marker);

            if ($position === false) {
                return false;
            }

            $context = json_decode(
                explode("\n", substr($instructions, $position + strlen($marker)), 2)[0],
                true
            );

            if (! is_array($context)) {
                return false;
            }

            $selected = $context['selected_product'] ?? [];

            return ($selected['id'] ?? null) === $motion->id
                && ($selected['name'] ?? null) === 'سرویس زویلینگ موشن'
                && ($selected['selection_source'] ?? null)
                    === 'server_resolved_ordinal';
        });
    }
}
