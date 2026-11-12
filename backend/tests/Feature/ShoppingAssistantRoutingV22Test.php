<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Services\ShoppingAssistant\ShoppingAssistantContextualFollowUp;
use App\Services\ShoppingAssistant\ShoppingAssistantLanguage;
use App\Services\ShoppingAssistant\ShoppingAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ShoppingAssistantRoutingV22Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('shopping_assistant.driver', 'mock');
        Http::preventStrayRequests();
    }

    public function test_finglish_is_persian_but_normal_english_stays_english(): void
    {
        $this->assertSame('fa', ShoppingAssistantLanguage::detect('salam ye ghahve saz khoob baraye khone mikham', 'fa'));
        $this->assertSame('fa', ShoppingAssistantLanguage::detect('lotfan farsi javab bede', 'en'));
        $this->assertSame('en', ShoppingAssistantLanguage::detect('Can you recommend a premium coffee machine?', 'fa'));
        $this->assertSame('fa', ShoppingAssistantLanguage::catalogSearchMessage('salam ye ghahve saz khoob baraye khone mikham') === 'قهوه ساز' ? 'fa' : 'bad');
    }

    public function test_product_choice_and_weight_question_continue_even_without_last_question(): void
    {
        $history = [
            ['role' => 'user', 'content' => 'برای چهار نفر قابلمه می‌خواهم'],
            ['role' => 'assistant', 'content' => 'دو محصول زویلینگ داریم.'],
        ];
        $this->assertTrue(ShoppingAssistantContextualFollowUp::isContinuation(
            'از بین دو گزینه اصلی یکی رو انتخاب کن و دلیل انتخابت رو بگو.', $history, [2, 3]
        ));
        $this->assertTrue(ShoppingAssistantContextualFollowUp::isContinuation(
            'وزن دقیقش چقدره؟ لطفاً چیزی رو حدس نزن.', $history, [2, 3]
        ));
        $this->assertTrue(ShoppingAssistantContextualFollowUp::isContinuation(
            'هم سبک بودن و هم دوام برام مهمه؛ حالا چی پیشنهاد می‌کنی؟', $history, [2, 3]
        ));
        $this->assertFalse(ShoppingAssistantContextualFollowUp::isContinuation('سلام', [], []));
        $this->assertFalse(ShoppingAssistantContextualFollowUp::isContinuation(
            'آدرس صفحه تماس با ما را بده', $history, [2, 3]
        ));
    }

    public function test_choice_response_preserves_two_real_products_in_memory(): void
    {
        $category = Category::factory()->create();
        $motion = Product::factory()->create([
            'category_id' => $category->id,
            'name' => ['fa' => 'سرویس ۵ پارچه زویلینگ موشن', 'en' => 'Zwilling Motion Set of 5'],
            'brand' => 'Zwilling',
            'status' => ProductStatus::ACTIVE,
        ]);
        $simplify = Product::factory()->create([
            'category_id' => $category->id,
            'name' => ['fa' => 'سرویس ۵ پارچه زویلینگ سیمپلیفای', 'en' => 'Zwilling Simplify Set of 5'],
            'brand' => 'Zwilling',
            'status' => ProductStatus::ACTIVE,
        ]);
        $ids = [$motion->id, $simplify->id];
        $history = [
            ['role' => 'user', 'content' => 'برای خانواده چهار نفره سرویس قابلمه می‌خوام'],
            ['role' => 'assistant', 'content' => 'زویلینگ موشن و سیمپلیفای را در نظر بگیریم.'],
        ];
        $result = app(ShoppingAssistantService::class)->respond(
            'از بین دو گزینه اصلی یکی رو انتخاب کن و دلیل انتخابت رو بگو.',
            'fa', $history, 'luna-v22-routing-test', $ids
        );
        $this->assertSame('conversation_follow_up', $result['tool']);
        $this->assertSame($ids, $result['remembered_product_ids']);
        $this->assertNotSame('می‌توانم در انتخاب محصولات آشپزخانه و خانه از کاتالوگ TKB کمک کنم. دنبال چه محصول یا کاربردی هستید؟', $result['content']);
        Http::assertSentCount(0);
    }

    public function test_electric_flat_reply_and_weight_question_do_not_restart_chat(): void
    {
        $history = [
            ['role' => 'user', 'content' => 'اجاقم برقیه'],
            ['role' => 'assistant', 'content' => 'اجاق شما القایی است یا صفحه‌ای؟'],
        ];
        $this->assertTrue(ShoppingAssistantContextualFollowUp::isContinuation(
            'از نوع صفحه‌ای معمولیه، القایی نیست.', $history
        ));
        $this->assertTrue(ShoppingAssistantContextualFollowUp::isContinuation(
            'وزن دقیقش چقدره؟ لطفاً چیزی رو حدس نزن.', $history, [1]
        ));
    }
}
