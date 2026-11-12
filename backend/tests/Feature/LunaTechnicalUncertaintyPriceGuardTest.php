<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ShoppingAssistant\ShoppingAssistantAdvisoryIntent;
use App\Services\ShoppingAssistant\ShoppingAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

final class LunaTechnicalUncertaintyPriceGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('shopping_assistant.driver', 'mock');
        Http::preventStrayRequests();
    }

    public function test_unverified_coating_claim_reaches_consultation_without_inventing_catalog_cards(): void
    {
        $message = 'این روکش PFOA-free هست؟';
        self::assertTrue(ShoppingAssistantAdvisoryIntent::needsAdvice($message));
        $r = $this->postJson('/api/assistant/chat?locale=fa', ['message' => $message]);
        $r->assertOk()->assertJsonPath('data.tool', 'conversation_follow_up');
        self::assertEmpty($r->json('data.product_cards'));
        Http::assertSentCount(0);
    }

    public function test_claim_routing_is_narrow_and_does_not_override_unrelated_messages(): void
    {
        foreach (['سلام', 'PFOA', 'نوشیدنی', 'دستور پخت املت'] as $message) {
            self::assertFalse(ShoppingAssistantAdvisoryIntent::needsAdvice($message));
        }
        self::assertTrue(ShoppingAssistantAdvisoryIntent::needsAdvice('آیا این تابه PFAS-free است؟'));
        self::assertTrue(ShoppingAssistantAdvisoryIntent::needsAdvice('Is this pan PTFE-free?'));
    }

    public function test_price_guard_preserves_technical_ranges_but_still_blocks_money(): void
    {
        $method = new ReflectionMethod(ShoppingAssistantService::class, 'containsPriceDisclosure');
        $service = app(ShoppingAssistantService::class);
        foreach ([
            'تیغه حدود ۱۸ تا ۲۰ سانتی‌متر مناسب است.',
            'حدود 1.5 لیتر گنجایش دارد.',
            'حدود ۲۰ سانتی‌متر طول دارد.',
            'برای بسیاری از کارها حدود ۱۸ تا ۲۰ سانتی‌متر کافی است.',
        ] as $safe) {
            self::assertFalse($method->invoke($service, $safe), $safe);
        }
        foreach ([
            'حدود ۵۰۰۰ تومان قیمت دارد.',
            'حدود 5,000.',
            'قیمت این کالا 5000 است.',
            'روی این محصول 20% تخفیف داریم.',
            'تیغه حدود ۱۸ تا ۲۰ سانتی‌متر است و قیمتش ۵۰۰۰ تومان است.',
        ] as $unsafe) {
            self::assertTrue($method->invoke($service, $unsafe), $unsafe);
        }
    }
}
