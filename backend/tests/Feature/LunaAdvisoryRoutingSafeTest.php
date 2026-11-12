<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ShoppingAssistant\ShoppingAssistantAdvisoryIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class LunaAdvisoryRoutingSafeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('shopping_assistant.driver', 'mock');
        Http::preventStrayRequests();
    }

    public function test_cookware_comparison_reaches_consultation(): void
    {
        $message = 'برای املت کدوم راحت‌تره، استیل یا روکش‌دار؟';
        $this->assertTrue(ShoppingAssistantAdvisoryIntent::needsAdvice($message));
        $r = $this->postJson('/api/assistant/chat?locale=fa', ['message' => $message]);
        $r->assertOk()->assertJsonPath('data.tool', 'conversation_follow_up');
        $this->assertNotSame('می‌توانم در انتخاب محصولات آشپزخانه و خانه از کاتالوگ TKB کمک کنم. دنبال چه محصول یا کاربردی هستید؟', $r->json('data.messages.1.content'));
        $this->assertEmpty($r->json('data.product_cards'));
        Http::assertSentCount(0);
    }

    public function test_purchase_regret_reaches_consultation(): void
    {
        $message = 'می‌ترسم بخرم بعد پشیمون شم';
        $this->assertTrue(ShoppingAssistantAdvisoryIntent::needsAdvice($message));
        $r = $this->postJson('/api/assistant/chat?locale=fa', ['message' => $message]);
        $r->assertOk()->assertJsonPath('data.tool', 'conversation_follow_up');
        $this->assertEmpty($r->json('data.product_cards'));
        Http::assertSentCount(0);
    }

    public function test_unrelated_greetings_keep_original_fallback(): void
    {
        foreach (['سلام', 'نوشیدنی', 'آدرس صفحه تماس با ما را بده', 'هوا چطوره'] as $message) {
            $this->assertFalse(ShoppingAssistantAdvisoryIntent::needsAdvice($message));
        }
        $r = $this->postJson('/api/assistant/chat?locale=fa', ['message' => 'نوشیدنی']);
        $r->assertOk();
        $this->assertNull($r->json('data.tool'));
        Http::assertSentCount(0);
    }
}
