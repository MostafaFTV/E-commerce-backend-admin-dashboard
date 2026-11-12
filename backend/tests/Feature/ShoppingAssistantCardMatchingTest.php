<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Services\ShoppingAssistant\ShoppingAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

final class ShoppingAssistantCardMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_short_catalog_names_match_and_preserve_response_order(): void
    {
        $category = Category::factory()->create();
        $motion = $this->product($category->id, 'سرویس ۵ پارچه زویلینگ موشن', 'Zwilling Motion Set of 5');
        $simplify = $this->product($category->id, 'سرویس ۵ پارچه زویلینگ سیمپلیفای', 'Zwilling Simplify Set of 5');
        $cards = [['id' => $motion->id], ['id' => $simplify->id]];

        $result = $this->cards('زویلینگ سیمپلیفای و زویلینگ موشن را بررسی کنیم.', $cards);
        $this->assertSame([$simplify->id, $motion->id], array_column($result, 'id'));

        $result = $this->cards('Zwilling Motion is an option.', $cards);
        $this->assertSame([$motion->id], array_column($result, 'id'));
    }

    public function test_no_arbitrary_card_for_generic_or_unmentioned_name(): void
    {
        $category = Category::factory()->create();
        $motion = $this->product($category->id, 'سرویس ۵ پارچه زویلینگ موشن', 'Zwilling Motion Set of 5');
        $simplify = $this->product($category->id, 'سرویس ۵ پارچه زویلینگ سیمپلیفای', 'Zwilling Simplify Set of 5');
        $cards = [['id' => $motion->id], ['id' => $simplify->id]];

        $this->assertSame([], $this->cards('هر دو مدل نیاز به بررسی دارند.', $cards));
        $this->assertSame([], $this->cards('سرویس پنج پارچه برای چهار نفر مناسب است.', $cards));
        $this->assertSame([], $this->cards('زویلینگ موشن را ببینیم.', [['id' => 999999]]));
    }

    public function test_ambiguous_short_alias_fails_closed(): void
    {
        $category = Category::factory()->create();
        $a = $this->product($category->id, 'سرویس ۵ پارچه زویلینگ موشن', 'Zwilling Motion Set of 5');
        $b = $this->product($category->id, 'سرویس ۶ پارچه زویلینگ موشن', 'Zwilling Motion Set of 6');
        $this->assertSame([], $this->cards('زویلینگ موشن را بررسی کنیم.', [
            ['id' => $a->id], ['id' => $b->id],
        ]));
    }

    private function product(int $categoryId, string $fa, string $en): Product
    {
        return Product::factory()->create([
            'category_id' => $categoryId,
            'name' => ['fa' => $fa, 'en' => $en],
            'brand' => 'Zwilling',
            'status' => ProductStatus::ACTIVE,
        ]);
    }

    private function cards(string $answer, array $cards): array
    {
        $method = new ReflectionMethod(ShoppingAssistantService::class, 'cardsNamedInResponse');
        $service = (new \ReflectionClass(ShoppingAssistantService::class))
            ->newInstanceWithoutConstructor();
        return $method->invoke($service, $answer, $cards);
    }
}
