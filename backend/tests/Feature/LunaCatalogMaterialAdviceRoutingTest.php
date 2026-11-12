<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ShoppingAssistant\ShoppingAssistantAdvisoryIntent;
use App\Services\ShoppingAssistant\ShoppingAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class LunaCatalogMaterialAdviceRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('shopping_assistant.driver', 'mock');
        Http::preventStrayRequests();
    }

    public function test_bilingual_material_questions_are_consultations_without_invented_catalog_results(): void
    {
        $cases = [
            ['Are bamboo cutting boards automatically more hygienic than plastic?', 'en'],
            ['Are wooden cutting boards safe to wash in a dishwasher?', 'en'],
            ['آیا تخته برش بامبو از تخته پلاستیکی بهداشتی‌تر است؟', 'fa'],
            ['تخته برش چوبی رو میشه داخل ماشین ظرفشویی شست؟', 'fa'],
        ];
        foreach ($cases as $index => [$message, $locale]) {
            $this->assertTrue(ShoppingAssistantAdvisoryIntent::needsAdvice($message));
            $result = app(ShoppingAssistantService::class)->respond(
                $message, $locale, [], 'luna-material-advice-' . $index
            );
            $this->assertNotNull($result['tool'], 'Unexpected generic fallback: ' . $message);
            $this->assertNotEmpty($result['content']);
            $this->assertSame([], $result['product_cards'], 'Do not invent catalog product cards.');
        }
        Http::assertSentCount(0);
    }

    public function test_irrelevant_requests_do_not_become_material_consultations(): void
    {
        foreach ([
            'What is the weather today?',
            'Tell me a joke about a wooden boat.',
            'سلام',
            'آب و هوای امروز چطوره؟',
            'Do you sell plastic cutting boards?',
            'قیمت تخته برش چوبی چنده؟',
        ] as $message) {
            $this->assertFalse(ShoppingAssistantAdvisoryIntent::needsAdvice($message), $message);
        }
    }

    public function test_prior_comparison_and_coating_guards_are_retained(): void
    {
        $this->assertTrue(ShoppingAssistantAdvisoryIntent::needsAdvice('برای املت تابه استیل بهتره یا نچسب؟'));
        $this->assertTrue(ShoppingAssistantAdvisoryIntent::needsAdvice('این روکش PFOA-free هست؟'));
        $this->assertTrue(ShoppingAssistantAdvisoryIntent::needsAdvice('Is this pan PFAS-free?'));
    }
}
