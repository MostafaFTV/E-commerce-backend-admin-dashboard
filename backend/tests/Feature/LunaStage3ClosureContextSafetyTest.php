<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ShoppingAssistant\ShoppingAssistantAdvisoryIntent;
use App\Services\ShoppingAssistant\ShoppingAssistantStage3ContextBridge;
use App\Services\ShoppingAssistant\ShoppingAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class LunaStage3ClosureContextSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_specific_everyday_kitchen_questions_are_advice_without_catalog_invention(): void
    {
        config()->set('shopping_assistant.driver', 'mock');
        Http::preventStrayRequests();
        $questions = [
            ['همزن دستی برای خمیر نون سنگین جواب می‌ده؟', 'fa'],
            ['یه قهوه‌ساز برای خودم می‌خوام، صبح‌ها خیلی عجله دارم', 'fa'],
            ['برای رفت‌وآمد روزانه فلاسک می‌خوام، موقع انتخاب چی مهمه؟', 'fa'],
            ['Does a larger kettle make sense if it is just me?', 'en'],
        ];
        foreach ($questions as $i => [$message, $locale]) {
            self::assertTrue(ShoppingAssistantAdvisoryIntent::needsAdvice($message), $message);
            $reply = app(ShoppingAssistantService::class)->respond($message, $locale, [], 'stage3-closure-advice-' . $i);
            self::assertNotNull($reply['tool'], $message);
            self::assertSame([], $reply['product_cards'], 'No made-up catalog card');
        }
        Http::assertSentCount(0);
    }

    public function test_explicit_context_corrections_do_not_reset_and_unrelated_history_does_not_hijack(): void
    {
        config()->set('shopping_assistant.driver', 'mock');
        Http::preventStrayRequests();
        $history = [
            ['role' => 'user', 'content' => 'قابلمه برای اجاق گازی می‌خواستم.'],
            ['role' => 'assistant', 'content' => 'چه نوع اجاقی دارید؟'],
        ];
        self::assertTrue(ShoppingAssistantStage3ContextBridge::needsContinuation('راستی اجاقم رو عوض کردم، الان القاییه نه گازی', $history));
        $reply = app(ShoppingAssistantService::class)->respond('راستی اجاقم رو عوض کردم، الان القاییه نه گازی', 'fa', $history, 'stage3-closure-context');
        self::assertSame('conversation_follow_up', $reply['tool']);
        self::assertSame([], $reply['product_cards']);
        self::assertFalse(ShoppingAssistantStage3ContextBridge::needsContinuation('نه، ممنون', $history));
        self::assertFalse(ShoppingAssistantStage3ContextBridge::needsContinuation('Actually, forget the color', []));
        self::assertFalse(ShoppingAssistantStage3ContextBridge::needsContinuation('the other one', [['role'=>'assistant','content'=>'Hello there.']]));
        self::assertFalse(ShoppingAssistantAdvisoryIntent::needsAdvice('سلام، حالت چطوره؟'));
        Http::assertSentCount(0);
    }
}
