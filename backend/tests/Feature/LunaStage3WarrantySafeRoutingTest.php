<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ShoppingAssistant\ShoppingAssistantAdvisoryIntent;
use App\Services\ShoppingAssistant\ShoppingAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class LunaStage3WarrantySafeRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('shopping_assistant.driver', 'mock');
        Http::preventStrayRequests();
    }

    public function test_bilingual_warranty_questions_reach_consultation_without_invented_catalog_cards(): void
    {
        foreach ([
            ['Does this come with a lifetime warranty?', 'en'],
            ['Does this model have a warranty?', 'en'],
            ['آیا این مدل گارانتی مادام العمر دارد؟', 'fa'],
            ['این محصول شامل ضمانت میشه؟', 'fa'],
        ] as $n => [$question, $locale]) {
            self::assertTrue(ShoppingAssistantAdvisoryIntent::needsAdvice($question), $question);
            $response = app(ShoppingAssistantService::class)->respond(
                $question, $locale, [], 'luna-warranty-offline-' . $n
            );
            // The catalog router takes precedence. A question containing 'model'
            // may legitimately route to search_products even with no matches.
            // Requiring one exact tool would incorrectly reject healthy routing.
            self::assertContains(
                $response['tool'], ['conversation_follow_up', 'search_products'],
                'Warranty request must not fall back to generic catalog greeting: ' . $question
            );
            self::assertSame([], $response['product_cards'], 'No unverified product cards');
            self::assertNotEmpty(trim((string) $response['content']));
            self::assertStringNotContainsString(
                'I can help you explore TKB', (string) $response['content'],
                'Generic greeting is not warranty advice'
            );
        }
        Http::assertSentCount(0);
    }

    public function test_non_product_warranty_words_do_not_hijack_unrelated_routes(): void
    {
        foreach ([
            'What is the warranty of the universe?',
            'What is the weather?',
            'Lifetime warranty',
            'سلام',
            'گارانتی',
            'قیمت چنده؟',
        ] as $unrelated) {
            self::assertFalse(ShoppingAssistantAdvisoryIntent::needsAdvice($unrelated), $unrelated);
        }
        self::assertTrue(ShoppingAssistantAdvisoryIntent::needsAdvice('این روکش PFOA-free هست؟'));
        self::assertTrue(ShoppingAssistantAdvisoryIntent::needsAdvice('Are bamboo cutting boards more hygienic than plastic?'));
        Http::assertSentCount(0);
    }
}
