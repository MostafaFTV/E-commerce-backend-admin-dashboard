<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createEligibleOrder(
        User $user,
        string $status = 'paid',
        int $days = 90
    ): Order {
        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'WALLET-'.uniqid(),
            'status' => $status,
            'subtotal' => '100000.00',
            'discount' => '0.00',
            'shipping_cost' => '0.00',
            'tax' => '0.00',
            'total' => '100000.00',
            'currency' => 'IRT',
            'shipping_address' => ['city' => 'Tehran'],
        ]);

        $order->forceFill([
            'cashback_snapshot_completed_at' => now(),
        ])->save();

        $categoryId = DB::table('categories')->insertGetId([
            'name' => json_encode(['en' => 'Test']),
            'slug' => 'wallet-category-'.uniqid(),
            'is_active' => 1,
            'sort_order' => 0,
        ]);

        $productId = DB::table('products')->insertGetId([
            'category_id' => $categoryId,
            'name' => json_encode(['en' => 'Test']),
            'slug' => 'wallet-product-'.uniqid(),
            'price' => '100000.00',
            'status' => 'active',
        ]);

        $item = $order->items()->create([
            'product_id' => $productId,
            'product_name' => 'Test',
            'quantity' => 1,
            'unit_price' => '100000.00',
            'total' => '100000.00',
        ]);

        DB::table('cashback_rule_order_items')->insert([
            'order_item_id' => $item->id,
            'cashback_rule_id' => null,
            'rule_name_snapshot' => 'Test',
            'percentage_snapshot' => '10.00',
            'eligible_amount' => '100000.00',
            'cashback_amount' => '10000.00',
            'expires_after_days_snapshot' => $days,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $order;
    }

    public function test_paid_order_issues_pending_credit_once(): void
    {
        $user = User::factory()->create();
        $order = $this->createEligibleOrder($user);
        $service = app(WalletService::class);

        $service->issuePending($order);
        $service->issuePending($order);

        $this->assertDatabaseCount('wallets', 1);
        $this->assertDatabaseCount('wallet_credits', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);

        $this->assertSame('10000.00', $service->balances($user)['pending']);
        $this->assertSame('0.00', $service->balances($user)['available']);
    }

    public function test_delivered_order_activates_credit_once(): void
    {
        $user = User::factory()->create();
        $order = $this->createEligibleOrder($user);
        $service = app(WalletService::class);

        $service->issuePending($order);

        $order->update(['status' => 'delivered']);

        $service->activateDelivered($order);
        $service->activateDelivered($order);

        $credit = DB::table('wallet_credits')->first();

        $this->assertSame('available', $credit->status);
        $this->assertNotNull($credit->expires_at);
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertSame('0.00', $service->balances($user)['pending']);
        $this->assertSame('10000.00', $service->balances($user)['available']);
    }

    public function test_pending_payment_cannot_issue_cashback(): void
    {
        $user = User::factory()->create();

        $order = $this->createEligibleOrder(
            $user,
            'pending_payment'
        );

        $this->expectException(LogicException::class);

        app(WalletService::class)->issuePending($order);
    }

    public function test_credit_without_delivery_is_not_spendable(): void
    {
        $user = User::factory()->create();
        $order = $this->createEligibleOrder($user);
        $service = app(WalletService::class);

        $service->issuePending($order);

        $balances = $service->balances($user);

        $this->assertSame('10000.00', $balances['pending']);
        $this->assertSame('0.00', $balances['available']);
    }
    private function spendingFixture(): array
    {
        $user = User::factory()->create();
        $source = $this->createEligibleOrder($user);
        $wallet = app(WalletService::class);

        $wallet->issuePending($source);
        $source->update(['status' => 'delivered']);
        $wallet->activateDelivered($source);

        $purchase = $this->createEligibleOrder(
            $user,
            'pending_payment'
        );

        return [$user, $purchase];
    }

    public function test_spending_reserves_available_credit(): void
    {
        [$user, $purchase] = $this->spendingFixture();

        $service = app(\App\Services\WalletSpendingService::class);

        $service->reserve($purchase, '3000.00');
        $service->reserve($purchase, '3000.00');

        $this->assertDatabaseCount('wallet_allocations', 1);

        $this->assertDatabaseHas('wallet_allocations', [
            'order_id' => $purchase->id,
            'amount' => '3000.00',
            'status' => 'reserved',
        ]);

        $this->assertSame(
            '7000.00',
            app(WalletService::class)->balances($user)['available']
        );

        $this->assertDatabaseCount('wallet_transactions', 3);
    }

    public function test_spending_settles_reserved_credit_once(): void
    {
        [$user, $purchase] = $this->spendingFixture();

        $service = app(\App\Services\WalletSpendingService::class);

        $service->reserve($purchase, '4000.00');

        $purchase->update(['status' => 'paid']);

        $service->settle($purchase);
        $service->settle($purchase);

        $this->assertDatabaseHas('wallet_allocations', [
            'order_id' => $purchase->id,
            'status' => 'spent',
            'amount' => '4000.00',
        ]);

        $this->assertSame(
            '6000.00',
            app(WalletService::class)->balances($user)['available']
        );

        $this->assertDatabaseCount('wallet_transactions', 4);
    }

    public function test_spending_releases_unpaid_reservation(): void
    {
        [$user, $purchase] = $this->spendingFixture();

        $service = app(\App\Services\WalletSpendingService::class);

        $service->reserve($purchase, '4500.00');
        $service->release($purchase);
        $service->release($purchase);

        $this->assertDatabaseHas('wallet_allocations', [
            'order_id' => $purchase->id,
            'status' => 'released',
        ]);

        $this->assertSame(
            '10000.00',
            app(WalletService::class)->balances($user)['available']
        );

        $this->assertDatabaseCount('wallet_transactions', 4);
    }

    public function test_spending_cannot_exceed_available_balance(): void
    {
        [, $purchase] = $this->spendingFixture();

        $this->expectException(\LogicException::class);

        app(\App\Services\WalletSpendingService::class)
            ->reserve($purchase, '10001.00');
    }

    public function test_spending_expires_unused_credit_once(): void
    {
        [$user] = $this->spendingFixture();

        DB::table('wallet_credits')->update([
            'expires_at' => now()->subDay(),
        ]);

        $service = app(\App\Services\WalletSpendingService::class);

        $this->assertSame(1, $service->expireAvailableCredits());
        $this->assertSame(0, $service->expireAvailableCredits());

        $this->assertSame(
            '0.00',
            app(WalletService::class)->balances($user)['available']
        );

        $this->assertDatabaseHas('wallet_credits', [
            'status' => 'expired',
            'remaining_amount' => '0.00',
        ]);

        $this->assertDatabaseCount('wallet_transactions', 3);
    }
    public function test_reversal_invalidates_pending_cashback(): void
    {
        $user = User::factory()->create();
        $order = $this->createEligibleOrder($user);

        app(WalletService::class)->issuePending($order);

        $order->update(['status' => 'cancelled']);

        $service = app(\App\Services\WalletReversalService::class);

        $service->reverseEarned($order);
        $service->reverseEarned($order);

        $this->assertDatabaseHas('wallet_credits', [
            'source_order_id' => $order->id,
            'status' => 'reversed',
            'remaining_amount' => '0.00',
        ]);

        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertSame(
            '0.00',
            app(WalletService::class)->balances($user)['pending']
        );
    }

    public function test_reversal_invalidates_unused_available_cashback(): void
    {
        $user = User::factory()->create();
        $order = $this->createEligibleOrder($user);

        $walletService = app(WalletService::class);
        $walletService->issuePending($order);

        $order->update(['status' => 'delivered']);
        $walletService->activateDelivered($order);

        $order->update(['status' => 'returned']);

        app(\App\Services\WalletReversalService::class)
            ->reverseEarned($order);

        $this->assertSame(
            '0.00',
            $walletService->balances($user)['available']
        );

        $this->assertDatabaseHas('wallet_credits', [
            'source_order_id' => $order->id,
            'status' => 'reversed',
        ]);
    }

    public function test_reversal_restores_spent_wallet_credit(): void
    {
        [$user, $purchase] = $this->spendingFixture();

        $spending = app(\App\Services\WalletSpendingService::class);
        $spending->reserve($purchase, '3000.00');

        $purchase->update(['status' => 'paid']);
        $spending->settle($purchase);

        $purchase->update(['status' => 'returned']);

        $service = app(\App\Services\WalletReversalService::class);
        $service->restoreSpent($purchase);
        $service->restoreSpent($purchase);

        $this->assertSame(
            '10000.00',
            app(WalletService::class)->balances($user)['available']
        );

        $this->assertDatabaseHas('wallet_allocations', [
            'order_id' => $purchase->id,
            'status' => 'restored',
        ]);

        $this->assertDatabaseCount('wallet_transactions', 5);
    }

    public function test_reversal_does_not_renew_expired_credit(): void
    {
        [$user, $purchase] = $this->spendingFixture();

        $spending = app(\App\Services\WalletSpendingService::class);
        $spending->reserve($purchase, '3000.00');

        $purchase->update(['status' => 'paid']);
        $spending->settle($purchase);

        DB::table('wallet_credits')->update([
            'expires_at' => now()->subDay(),
        ]);

        $purchase->update(['status' => 'returned']);

        app(\App\Services\WalletReversalService::class)
            ->restoreSpent($purchase);

        $this->assertSame(
            '0.00',
            app(WalletService::class)->balances($user)['available']
        );

        $this->assertDatabaseHas('wallet_allocations', [
            'order_id' => $purchase->id,
            'status' => 'restored',
        ]);
    }

    public function test_reversal_blocks_spent_source_cashback(): void
    {
        [$user, $purchase] = $this->spendingFixture();

        $spending = app(\App\Services\WalletSpendingService::class);
        $spending->reserve($purchase, '3000.00');

        $purchase->update(['status' => 'paid']);
        $spending->settle($purchase);

        $sourceCredit = DB::table('wallet_credits')->first();

        $source = Order::query()->findOrFail(
            $sourceCredit->source_order_id
        );

        $source->update(['status' => 'returned']);

        $this->expectException(\LogicException::class);

        app(\App\Services\WalletReversalService::class)
            ->reverseEarned($source);
    }
    public function test_reversal_preflight_blocks_spent_source(): void
    {
        [, $purchase] = $this->spendingFixture();

        $spending = app(\App\Services\WalletSpendingService::class);

        $spending->reserve($purchase, '3000.00');
        $purchase->update(['status' => 'paid']);
        $spending->settle($purchase);

        $sourceOrderId = DB::table('wallet_credits')
            ->value('source_order_id');

        $source = Order::query()->findOrFail($sourceOrderId);

        $this->expectException(\LogicException::class);

        app(\App\Services\WalletReversalService::class)
            ->assertCanReverseEarned($source);
    }

    public function test_reversal_preflight_allows_unused_source(): void
    {
        $user = User::factory()->create();
        $order = $this->createEligibleOrder($user);

        app(WalletService::class)->issuePending($order);

        app(\App\Services\WalletReversalService::class)
            ->assertCanReverseEarned($order);

        $this->assertDatabaseCount('wallet_credits', 1);

        $this->assertDatabaseMissing('wallet_credits', [
            'source_order_id' => $order->id,
            'status' => 'reversed',
        ]);
    }
}