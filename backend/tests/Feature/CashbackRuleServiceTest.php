<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CashbackRule;
use App\Models\Category;
use App\Models\Product;
use App\Services\CashbackRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CashbackRuleServiceTest extends TestCase
{
    use RefreshDatabase;

    private function category(?int $parentId = null): Category
    {
        return Category::query()->create([
            'parent_id' => $parentId,
            'name' => [
                'en' => 'Test Category',
                'fa' => 'دسته آزمایشی',
            ],
            'slug' => 'cashback-category-' . uniqid(),
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function product(Category $category): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => [
                'en' => 'Test Product',
                'fa' => 'محصول آزمایشی',
            ],
            'slug' => 'cashback-product-' . uniqid(),
            'price' => '100000.00',
            'status' => 'active',
        ]);
    }

    private function rule(
        string $scope,
        int $priority = 0,
        bool $active = true,
        ?string $startsAt = null,
        ?string $endsAt = null
    ): CashbackRule {
        return CashbackRule::query()->create([
            'name' => 'Cashback ' . uniqid(),
            'scope' => $scope,
            'percentage' => '10.00',
            'priority' => $priority,
            'is_active' => $active,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'expires_after_days' => 90,
        ]);
    }

    private function resolve(Product $product): ?CashbackRule
    {
        return app(CashbackRuleService::class)
            ->resolveForProduct($product);
    }

    public function test_global_rule_matches_product(): void
    {
        $product = $this->product($this->category());
        $rule = $this->rule('all');

        $this->assertSame(
            $rule->id,
            $this->resolve($product)?->id
        );
    }

    public function test_parent_category_rule_matches_child_product(): void
    {
        $parent = $this->category();
        $child = $this->category($parent->id);
        $product = $this->product($child);

        $rule = $this->rule('categories');
        $rule->categories()->attach($parent->id);

        $this->assertSame(
            $rule->id,
            $this->resolve($product)?->id
        );
    }

    public function test_higher_priority_wins(): void
    {
        $product = $this->product($this->category());

        $this->rule('all', 1);
        $winner = $this->rule('all', 20);

        $this->assertSame(
            $winner->id,
            $this->resolve($product)?->id
        );
    }

    public function test_specific_product_wins_on_equal_priority(): void
    {
        $product = $this->product($this->category());

        $this->rule('all', 10);

        $winner = $this->rule('products', 10);
        $winner->products()->attach($product->id);

        $this->assertSame(
            $winner->id,
            $this->resolve($product)?->id
        );
    }

    public function test_inactive_and_outside_date_rules_are_ignored(): void
    {
        $product = $this->product($this->category());

        $this->rule('all', 100, false);

        $this->rule(
            'all',
            90,
            true,
            now()->addDay()->toDateTimeString()
        );

        $this->rule(
            'all',
            80,
            true,
            null,
            now()->subDay()->toDateTimeString()
        );

        $winner = $this->rule('all', 1);

        $this->assertSame(
            $winner->id,
            $this->resolve($product)?->id
        );
    }

    public function test_lower_id_wins_when_priority_and_scope_match(): void
    {
        $product = $this->product($this->category());

        $first = $this->rule('all', 10);
        $this->rule('all', 10);

        $this->assertSame(
            $first->id,
            $this->resolve($product)?->id
        );
    }

    public function test_no_matching_rule_returns_null(): void
    {
        $product = $this->product($this->category());

        $this->assertNull(
            $this->resolve($product)
        );
    }
}