<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShoppingAssistantLiveQualityV22Test extends TestCase
{
    use RefreshDatabase;

    public function test_real_six_turn_laravel_gapgpt_consultation(): void
    {
        if (getenv('LUNA_V22_LIVE_TEST') !== '1') {
            $this->markTestSkipped('Live test explicitly opt-in.');
        }
        $key = getenv('LUNA_V22_EVAL_KEY');
        $this->assertTrue(is_string($key) && strlen($key) >= 10, 'Separate evaluation-only API key is required');

        config()->set([
            'shopping_assistant.enabled' => true,
            'shopping_assistant.driver' => 'gapgpt',
            'shopping_assistant.api_key' => $key,
            'shopping_assistant.base_url' => 'https://api.gapgpt.app/v1',
            'shopping_assistant.endpoint' => 'chat_completions',
            'shopping_assistant.model' => 'gpt-6.1-terra',
            'shopping_assistant.max_output_tokens' => 900,
            'shopping_assistant.timeout_seconds' => 30,
            'shopping_assistant.daily_budget_usd' => null,
            'shopping_assistant.monthly_budget_usd' => null,
            'shopping_assistant.local_test_budget_usd' => null,
        ]);
        $category = Category::factory()->create();
        foreach ([
            ['fa' => 'سرویس ۵ پارچه زویلینگ موشن', 'en' => 'Zwilling Motion Set of 5'],
            ['fa' => 'سرویس ۵ پارچه زویلینگ سیمپلیفای', 'en' => 'Zwilling Simplify Set of 5'],
        ] as $name) {
            Product::factory()->create([
                'category_id' => $category->id,
                'name' => $name,
                'brand' => 'Zwilling',
                'description' => null,
                'status' => ProductStatus::ACTIVE,
            ]);
        }
        $conversation = null;
        $report = [];
        $messages = [
            'برای آشپزی روزمره یه سرویس قابلمه خوب می‌خوام.',
            'خانواده ما چهار نفره است.',
            'اجاقم برقیه؛ از نوع صفحه‌ای معمولیه، القایی نیست.',
            'هم سبک بودن و هم دوام برام مهمه؛ حالا چی پیشنهاد می‌کنی؟',
            'از بین دو گزینه اصلی یکی رو انتخاب کن و دلیل انتخابت رو بگو.',
            'وزن دقیقش چقدره؟ لطفاً چیزی رو حدس نزن.',
        ];
        foreach ($messages as $index => $message) {
            $payload = ['message' => $message, 'locale' => 'fa'];
            if ($conversation !== null) {
                $payload['conversation_id'] = $conversation['id'];
                $payload['session_token'] = $conversation['session_token'];
            }
            $response = $this->postJson('/api/assistant/chat?locale=fa', $payload);
            $data = $response->json('data');
            $messagesList = is_array($data['messages'] ?? null) ? $data['messages'] : [];
            $last = $messagesList === [] ? null : end($messagesList);
            $answer = is_array($last) ? (string) ($last['content'] ?? '') : '';
            $report[] = [
                'turn' => $index + 1,
                'customer' => $message,
                'http_status' => $response->status(),
                'failure_message' => ($response->status() >= 200 && $response->status() < 300) ? null : $response->json('message'),
                'assistant' => $answer,
                'tool' => $data['tool'] ?? null,
                'cards' => array_map(static fn (array $card): array => [
                    'id' => $card['id'] ?? null, 'name' => $card['name'] ?? null,
                ], array_values(array_filter($data['product_cards'] ?? [], 'is_array'))),
                'tokens' => [
                    'in' => $data['usage']['input_tokens'] ?? null,
                    'out' => $data['usage']['output_tokens'] ?? null,
                    'provider' => $data['usage']['provider'] ?? null,
                ],
            ];
            $path = getenv('LUNA_V22_LIVE_REPORT');
            if (is_string($path) && $path !== '') {
                file_put_contents($path, json_encode(['turns' => $report], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            $this->assertSame(200, $response->status(), 'Turn ' . ($index + 1) . ' failed. See redacted transcript for reason.');
            $this->assertNotSame('', trim($answer));
            $this->assertStringNotContainsString('"response_version"', $answer);
            $this->assertStringNotContainsString('"recommended_product_ids"', $answer);
            $this->assertStringNotContainsString('می‌توانم در انتخاب محصولات آشپزخانه و خانه از کاتالوگ TKB کمک کنم.', $answer);
            if ($conversation === null) {
                $conversation = $data['conversation'] ?? null;
                $this->assertIsArray($conversation);
            } else {
                $this->assertSame($conversation['id'], $data['conversation']['id'] ?? null);
            }
        }
    }
}
