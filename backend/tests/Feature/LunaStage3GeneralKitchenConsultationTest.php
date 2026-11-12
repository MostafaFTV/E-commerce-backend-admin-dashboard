<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ShoppingAssistant\ShoppingAssistantAdvisoryIntent;
use App\Services\ShoppingAssistant\ShoppingAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class LunaStage3GeneralKitchenConsultationTest extends TestCase
{
    use RefreshDatabase;

    public function test_bilingual_kitchen_questions_do_not_restart_with_generic_catalog_intro(): void
    {
        config()->set('shopping_assistant.driver', 'mock');
        Http::preventStrayRequests();
        $cases = [
            ['مخلوط‌کن کوچیک برای اسموتی گاه‌به‌گاه کافیه یا مدل بزرگ بگیرم؟', 'fa'],
            ['برای خرد کردن پیاز و سبزی هر روز وقت زیادی می‌گیره؛ غذاساز به درد می‌خوره؟', 'fa'],
            ['پلوپز برای دو نفر چی بگیرم؟', 'fa'],
            ['استیل یعنی هیچ‌وقت زنگ نمی‌زنه؟', 'fa'],
            ['I need help choosing a kitchen appliance for simple cooking.', 'en'],
            ['Which bowl should I get for microwave cooking?', 'en'],
        ];
        foreach ($cases as $i => [$message, $locale]) {
            self::assertTrue(ShoppingAssistantAdvisoryIntent::needsAdvice($message), $message);
            $response = app(ShoppingAssistantService::class)->respond(
                $message, $locale, [], 'luna-general-advice-' . $i
            );
            self::assertContains($response['tool'], ['search_products', 'conversation_follow_up']);
            self::assertSame([], $response['product_cards']);
            self::assertStringNotContainsString('I can help you explore TKB', (string) $response['content']);
        }
        Http::assertSentCount(0);
    }

    public function test_greetings_and_unrelated_questions_do_not_become_kitchen_advice(): void
    {
        foreach (['سلام', 'hello', 'What is the weather?', 'What is the warranty of the universe?', 'فقط دارم نگاه می‌کنم، ممنون'] as $message) {
            self::assertFalse(ShoppingAssistantAdvisoryIntent::needsAdvice($message), $message);
        }
    }
}
