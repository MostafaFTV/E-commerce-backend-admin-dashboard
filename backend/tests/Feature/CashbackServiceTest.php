<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CashbackRule;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CashbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CashbackServiceTest extends TestCase
{
    use RefreshDatabase;

    private function category(): Category
    {
        return Category::query()->create([
            'name' => [
                'en' => 'Cashback Test Category',
                'fa' => 'دسته آزمایشی',
            ],
            'slug' => 'cashback-test-category-' . uniqid(),
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function product(Category $category): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => [
                'en' => 'Cashback Test Product',
                'fa' => 'محصول آزمایشی',
            ],
            'slug' => 'cashback-test-product-' . uniqid(),
            'price' => '100000.00',
            'status' => 'active',
        ]);
    }

    private function rule(
        string $scope,
        string $percentage
    ): CashbackRule {
        return CashbackRule::query()->create([
            'name' => 'Cashback Rule ' . uniqid(),
            'scope' => $scope,
            'percentage' => $percentage,
            'priority' => 1,
            'is_active' => true,
            'expires_after_days' => 90,
        ]);
    }

    private function order(
        array $lines,
        string $discount = '0.00',
        string $walletUsed = '0.00'
    ): Order {
        $user = User::factory()->create();

        $subtotal = array_reduce(
            $lines,
            fn ($sum, $line) =>
                $sum + (int) $line['total'],
            0
        );

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'CB-' . uniqid(),
            'status' => 'pending_payment',
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping_cost' => '0.00',
            'tax' => '0.00',
            'total_before_wallet' => $subtotal - (float) $discount,
            'wallet_credit_used' => $walletUsed,
            'total' => $subtotal - (float) $discount - (float) $walletUsed,
            'currency' => 'IRT',
            'shipping_address' => ['city' => 'Tehran'],
        ]);

        foreach ($lines as $line) {
            $order->items()->create([
                'product_id' => $line['product']->id,
                'product_name' => 'Test Product',
                'sku' => 'CB-' . uniqid(),
                'quantity' => 1,
                'unit_price' => $line['total'],
                'total' => $line['total'],
            ]);
        }

        return $order;
    }

    public function test_calculates_percentage_cashback(): void
    {
        $category = $this->category();
        $product = $this->product($category);

        $this->rule('all', '10.00');

        $order = $this->order([
            ['product' => $product, 'total' => '100000'],
        ]);

        app(CashbackService::class)->snapshotOrder($order);

        $this->assertDatabaseHas('cashback_rule_order_items', [
            'order_item_id' => $order->items()->first()->id,
            'eligible_amount' => 100000,
            'cashback_amount' => 10000,
        ]);
    }

    public function test_wallet_credit_does_not_earn_cashback(): void
    {
        $category = $this->category();
        $product = $this->product($category);

        $this->rule('all', '10.00');

        $order = $this->order(
            [['product' => $product, 'total' => '100000']],
            '0.00',
            '20000.00'
        );

        app(CashbackService::class)->snapshotOrder($order);

        $this->assertDatabaseHas('cashback_rule_order_items', [
            'eligible_amount' => 80000,
            'cashback_amount' => 8000,
        ]);
    }

    public function test_distributes_eligible_amount_between_items(): void
    {
        $category = $this->category();

        $firstProduct = $this->product($category);
        $secondProduct = $this->product($category);

        $rule = $this->rule('all', '10.00');

        $order = $this->order(
            [
                ['product' => $firstProduct, 'total' => '600000'],
                ['product' => $secondProduct, 'total' => '400000'],
            ],
            '0.00',
            '200000.00'
        );

        app(CashbackService::class)->snapshotOrder($order);

        $snapshots = DB::table('cashback_rule_order_items')
            ->orderBy('order_item_id')
            ->get();

        $this->assertCount(2, $snapshots);

        $this->assertEquals(
            '480000.00',
            $snapshots[0]->eligible_amount
        );

        $this->assertEquals(
            '320000.00',
            $snapshots[1]->eligible_amount
        );

        $this->assertEquals(
            '80000.00',
            $snapshots->sum('cashback_amount')
        );
    }

    public function test_snapshot_is_not_duplicated(): void
    {
        $category = $this->category();
        $product = $this->product($category);

        $this->rule('all', '10.00');

        $order = $this->order([
            ['product' => $product, 'total' => '100000'],
        ]);

        $service = app(CashbackService::class);

        $service->snapshotOrder($order);
        $service->snapshotOrder($order);

        $this->assertDatabaseCount(
            'cashback_rule_order_items',
            1
        );
    }

    public function test_rejects_wallet_credit_exceeding_subtotal(): void
    {
        $category = $this->category();
        $product = $this->product($category);

        $this->rule('all', '10.00');

        $order = $this->order(
            [['product' => $product, 'total' => '100000']],
            '20000.00',
            '90000.00'
        );

        $this->expectException(\LogicException::class);

        app(CashbackService::class)->snapshotOrder($order);
    }

    public function test_existing_snapshot_survives_rule_percentage_change(): void
    {
        $category = $this->category();
        $product = $this->product($category);

        $rule = $this->rule('all', '10.00');

        $order = $this->order([
            ['product' => $product, 'total' => '100000'],
        ]);

        $service = app(CashbackService::class);

        $service->snapshotOrder($order);

        $rule->update([
            'percentage' => '25.00',
        ]);

        $service->snapshotOrder($order);

        $snapshot = DB::table('cashback_rule_order_items')
            ->first();

        $this->assertEquals(
            '10.00',
            $snapshot->percentage_snapshot
        );

        $this->assertEquals(
            '10000.00',
            $snapshot->cashback_amount
        );

        $this->assertDatabaseCount(
            'cashback_rule_order_items',
            1
        );
    }

    public function test_fractional_amount_rounding_is_deterministic(): void
    {
        $category = $this->category();
        $product = $this->product($category);

        $this->rule('all', '7.50');

        $order = $this->order([
            ['product' => $product, 'total' => '101'],
        ]);

        app(CashbackService::class)->snapshotOrder($order);

        $snapshot = DB::table('cashback_rule_order_items')
            ->first();

        $this->assertEquals(
            '7.58',
            $snapshot->cashback_amount
        );
    }
    public function test_mixed_order_snapshot_remains_idempotent(): void
    {
        $category = $this->category();

        $eligible = $this->product($category);
        $excluded = $this->product($category);

        $rule = $this->rule('products', '10.00');
        $rule->products()->attach($eligible->id);

        $order = $this->order([
            ['product' => $eligible, 'total' => '100000'],
            ['product' => $excluded, 'total' => '100000'],
        ]);

        $service = app(CashbackService::class);

        $service->snapshotOrder($order);
        $service->snapshotOrder($order);

        $this->assertDatabaseCount(
            'cashback_rule_order_items',
            1
        );

        $this->assertNotNull(
            $order->fresh()->cashback_snapshot_completed_at
        );
    }

    public function test_partial_snapshot_without_completion_is_rejected(): void
    {
        $category = $this->category();
        $product = $this->product($category);

        $this->rule('all', '10.00');

        $order = $this->order([
            ['product' => $product, 'total' => '100000'],
        ]);

        DB::table('cashback_rule_order_items')->insert([
            'order_item_id' => $order->items()->first()->id,
            'cashback_rule_id' => null,
            'rule_name_snapshot' => 'Incomplete',
            'percentage_snapshot' => '10.00',
            'eligible_amount' => '100000.00',
            'cashback_amount' => '10000.00',
            'expires_after_days_snapshot' => 90,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\LogicException::class);

        app(CashbackService::class)->snapshotOrder($order);
    }
    public function test_product_without_rule_has_no_snapshot(): void
    {
        $category = $this->category();
        $product = $this->product($category);

        $order = $this->order([
            ['product' => $product, 'total' => '100000'],
        ]);

        app(CashbackService::class)->snapshotOrder($order);

        $this->assertDatabaseCount(
            'cashback_rule_order_items',
            0
        );
    }
}