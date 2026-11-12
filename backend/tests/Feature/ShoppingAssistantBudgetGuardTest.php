<?php

namespace Tests\Feature;

use App\Contracts\ShoppingAssistantProvider;
use App\Exceptions\ShoppingAssistantBudgetExceeded;
use App\Exceptions\ShoppingAssistantUnavailable;
use App\Services\ShoppingAssistant\ShoppingAssistantBudgetGuard;
use App\Services\ShoppingAssistant\ShoppingAssistantService;
use App\Services\ShoppingAssistant\ShoppingAssistantUsageLogger;
use App\Services\ShoppingAssistant\ShoppingCatalogTools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShoppingAssistantBudgetGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('shopping_assistant.driver', 'gapgpt');
        config()->set('shopping_assistant.input_cost_per_million_tokens', '0.10');
        config()->set('shopping_assistant.output_cost_per_million_tokens', '0.50');
        config()->set('shopping_assistant.daily_budget_usd', '0.000006');
        config()->set('shopping_assistant.monthly_budget_usd', '1.00');
        config()->set('shopping_assistant.max_output_tokens', 10);
        Http::fake();
    }

    public function test_pending_reservations_prevent_concurrent_budget_overspend(): void
    {
        $guard = app(ShoppingAssistantBudgetGuard::class);
        $first = $guard->reserve('conversation-one', 10, 10);

        $this->assertNotNull($first);
        $this->expectException(ShoppingAssistantBudgetExceeded::class);

        $guard->reserve('conversation-two', 10, 10);
    }

    public function test_success_reconciles_reservation_to_actual_provider_tokens(): void
    {
        $guard = app(ShoppingAssistantBudgetGuard::class);
        $reservation = $guard->reserve('conversation-one', 10, 10);

        $cost = app(ShoppingAssistantUsageLogger::class)->record(
            'conversation-one',
            'success',
            1,
            1,
            $reservation
        );

        $this->assertSame(0.0000006, $cost);
        $this->assertDatabaseHas('shopping_assistant_budget_reservations', [
            'id' => $reservation,
            'status' => 'settled',
            'reserved_cost_usd' => '0.000006000000',
            'actual_cost_usd' => '0.000000600000',
        ]);
        $this->assertDatabaseHas('shopping_assistant_usage_logs', [
            'budget_reservation_id' => $reservation,
            'input_tokens' => 1,
            'output_tokens' => 1,
            'estimated_cost_usd' => '0.000000600000',
        ]);
    }

    public function test_unknown_provider_tokens_keep_the_full_reservation_and_fail_closed(): void
    {
        config()->set('shopping_assistant.daily_budget_usd', '0.01');
        config()->set('shopping_assistant.monthly_budget_usd', '0.01');

        $provider = \Mockery::mock(ShoppingAssistantProvider::class);
        $provider->shouldReceive('complete')->once()->andReturn([
            'content' => 'Here is a catalog suggestion.',
            'input_tokens' => 0,
            'output_tokens' => 0,
        ]);
        $service = new ShoppingAssistantService(
            new ShoppingCatalogTools(),
            $provider,
            app(ShoppingAssistantBudgetGuard::class),
            app(\App\Services\ShoppingAssistant\ShoppingAssistantDailyQuota::class),
            app(\App\Services\ShoppingAssistant\ProductKnowledgeContext::class),
            app(\App\Services\ShoppingAssistant\OnDemandResearchTrigger::class)
        );

        try {
            $service->respond('Recommend a product', 'en', [], 'conversation-one', [], 'user:test');
            $this->fail('Missing token usage must fail closed.');
        } catch (ShoppingAssistantUnavailable) {
            $this->assertDatabaseHas('shopping_assistant_budget_reservations', [
                'conversation_fingerprint' => hash('sha256', 'conversation-one'),
                'status' => 'unknown',
                'actual_cost_usd' => null,
            ]);
        }

        $reservedCost = \Illuminate\Support\Facades\DB::table('shopping_assistant_budget_reservations')
            ->value('reserved_cost_usd');
        config()->set('shopping_assistant.daily_budget_usd', $reservedCost);

        $this->expectException(ShoppingAssistantBudgetExceeded::class);
        app(ShoppingAssistantBudgetGuard::class)->reserve('conversation-two', 10, 10);
    }

    public function test_provider_failure_keeps_an_unknown_reservation_for_reconciliation(): void
    {
        config()->set('shopping_assistant.daily_budget_usd', '0.01');
        config()->set('shopping_assistant.monthly_budget_usd', '0.01');

        $provider = \Mockery::mock(ShoppingAssistantProvider::class);
        $provider->shouldReceive('complete')
            ->once()
            ->andThrow(new ShoppingAssistantUnavailable(
                'Provider request failed.',
                diagnostics: [
                    'failure_stage' => 'provider_transport',
                    'failure_category' => 'provider_connection_failure',
                    'dispatch_state' => 'attempted',
                ]
            ));
        $service = new ShoppingAssistantService(
            new ShoppingCatalogTools(),
            $provider,
            app(ShoppingAssistantBudgetGuard::class),
            app(\App\Services\ShoppingAssistant\ShoppingAssistantDailyQuota::class),
            app(\App\Services\ShoppingAssistant\ProductKnowledgeContext::class),
            app(\App\Services\ShoppingAssistant\OnDemandResearchTrigger::class)
        );

        try {
            $service->respond('Recommend a product', 'en', [], 'conversation-one', [], 'user:test');
            $this->fail('Provider failures must propagate.');
        } catch (ShoppingAssistantUnavailable) {
            $this->assertDatabaseHas('shopping_assistant_budget_reservations', [
                'conversation_fingerprint' => hash('sha256', 'conversation-one'),
                'status' => 'unknown',
                'actual_cost_usd' => null,
            ]);
        }

        $reservedCost = \Illuminate\Support\Facades\DB::table('shopping_assistant_budget_reservations')
            ->value('reserved_cost_usd');
        config()->set('shopping_assistant.daily_budget_usd', $reservedCost);

        $this->expectException(ShoppingAssistantBudgetExceeded::class);
        app(ShoppingAssistantBudgetGuard::class)->reserve('conversation-two', 10, 10);
    }

    public function test_missing_pricing_fails_before_a_reservation_is_created(): void
    {
        config()->set('shopping_assistant.output_cost_per_million_tokens', null);

        try {
            app(ShoppingAssistantBudgetGuard::class)->reserve('conversation-one', 10, 10);
            $this->fail('Missing pricing must fail closed.');
        } catch (ShoppingAssistantUnavailable) {
            $this->assertDatabaseCount('shopping_assistant_budget_reservations', 0);
        }
    }

    public function test_mock_requests_do_not_reserve_or_consume_paid_budget(): void
    {
        config()->set('shopping_assistant.driver', 'mock');

        $reservation = app(ShoppingAssistantBudgetGuard::class)
            ->reserve('conversation-one', 100, 100);

        $this->assertNull($reservation);
        $this->assertDatabaseCount('shopping_assistant_budget_reservations', 0);
        $this->assertSame(
            0.0,
            app(ShoppingAssistantUsageLogger::class)->record(
                'conversation-one',
                'success',
                100,
                100
            )
        );
    }

    public function test_exhausted_budget_refuses_api_request_before_provider_dispatch(): void
    {
        config()->set('shopping_assistant.daily_budget_usd', '0.000001');

        $this->postJson('/api/assistant/chat?locale=en', [
            'message' => 'Recommend a pot',
        ])->assertStatus(429)
            ->assertJsonPath(
                'message',
                'The shopping assistant has reached its current spending limit.'
            );

        Http::assertSentCount(0);
        $this->assertDatabaseCount('shopping_assistant_budget_reservations', 0);
    }

    public function test_legacy_unpriced_provider_usage_blocks_standard_budget_and_local_test_scope_isolated(): void
    {
        $this->insertLegacyUsage(21, 'success', null, 0, 0, null);
        $unknownReservation = DB::table('shopping_assistant_budget_reservations')->insertGetId([
            'conversation_fingerprint' => hash('sha256', 'historical-unknown'),
            'budget_date' => now()->toDateString(),
            'budget_month' => now()->format('Y-m'),
            'reserved_cost_usd' => '0.000578100000',
            'actual_cost_usd' => null,
            'status' => 'unknown',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(ShoppingAssistantBudgetGuard::class)->reserve('standard-conversation', 10, 10);
            $this->fail('Legacy unknown provider spend must block the standard budget.');
        } catch (ShoppingAssistantUnavailable $exception) {
            $this->assertSame('legacy_spend_unknown', $exception->diagnostics()['failure_category']);
        }

        $this->app->detectEnvironment(static fn () => 'local');
        config()->set('shopping_assistant.local_test_budget_usd', '0.000006');
        $localReservation = app(ShoppingAssistantBudgetGuard::class)
            ->reserve('local-test-conversation', 10, 10);

        $this->assertNotNull($localReservation);
        $this->assertDatabaseHas('shopping_assistant_budget_reservations', [
            'id' => $localReservation,
            'budget_scope' => 'local_test',
            'status' => 'reserved',
        ]);
        $this->assertDatabaseHas('shopping_assistant_budget_reservations', [
            'id' => $unknownReservation,
            'budget_scope' => 'standard',
            'status' => 'unknown',
            'reserved_cost_usd' => '0.000578100000',
            'actual_cost_usd' => null,
        ]);
        $this->assertDatabaseHas('shopping_assistant_usage_logs', [
            'id' => 21,
            'status' => 'success',
            'estimated_cost_usd' => null,
        ]);

        $this->expectException(ShoppingAssistantBudgetExceeded::class);
        app(ShoppingAssistantBudgetGuard::class)->reserve('local-test-over-limit', 10, 10);
    }

    public function test_local_test_budget_cannot_bypass_unknown_spend_protection_outside_local_environment(): void
    {
        $this->insertLegacyUsage(21, 'success', null, 0, 0, null);
        $this->app->detectEnvironment(static fn () => 'production');
        config()->set('shopping_assistant.local_test_budget_usd', '1.00');

        try {
            app(ShoppingAssistantBudgetGuard::class)->reserve('production-conversation', 10, 10);
            $this->fail('The local test budget must not apply outside local.');
        } catch (ShoppingAssistantUnavailable $exception) {
            $this->assertSame('legacy_spend_unknown', $exception->diagnostics()['failure_category']);
        }

        $this->assertDatabaseCount('shopping_assistant_budget_reservations', 0);
    }

    public function test_known_pre_dispatch_failure_and_nonbillable_usage_do_not_count_as_provider_spend(): void
    {
        $this->insertLegacyUsage(16, 'failed', null, 0, 0, 'not_dispatched');
        $this->insertLegacyUsage(21, 'nonbillable', null, 0, 0, null);

        $reservation = app(ShoppingAssistantBudgetGuard::class)
            ->reserve('after-local-filter', 10, 10);

        $this->assertNotNull($reservation);
        $this->assertDatabaseHas('shopping_assistant_budget_reservations', [
            'id' => $reservation,
            'budget_scope' => 'standard',
            'status' => 'reserved',
        ]);
    }

    public function test_unlinked_failed_provider_attempt_with_unknown_dispatch_remains_blocking(): void
    {
        $this->insertLegacyUsage(16, 'failed', null, 0, 0, 'attempted');

        try {
            app(ShoppingAssistantBudgetGuard::class)->reserve('unknown-failed-call', 10, 10);
            $this->fail('A failed call with unknown dispatch status must remain conservatively blocked.');
        } catch (ShoppingAssistantUnavailable $exception) {
            $this->assertSame('legacy_spend_unknown', $exception->diagnostics()['failure_category']);
        }

        $this->assertDatabaseCount('shopping_assistant_budget_reservations', 0);
    }

    public function test_known_actual_gapgpt_usage_is_counted_against_the_standard_budget(): void
    {
        config()->set('shopping_assistant.daily_budget_usd', '0.000006');
        $this->insertLegacyUsage(15, 'success', '0.000005000000', 30, 4, 'response_received');

        $this->expectException(ShoppingAssistantBudgetExceeded::class);
        app(ShoppingAssistantBudgetGuard::class)->reserve('after-billable-call', 10, 10);
    }

    private function insertLegacyUsage(
        int $id,
        string $status,
        ?string $cost,
        int $inputTokens,
        int $outputTokens,
        ?string $dispatchState
    ): void {
        DB::table('shopping_assistant_usage_logs')->insert([
            'id' => $id,
            'conversation_fingerprint' => hash('sha256', 'legacy-' . $id),
            'provider' => 'gapgpt',
            'model' => 'gpt-6.1-terra',
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'estimated_cost_usd' => $cost,
            'status' => $status,
            'budget_reservation_id' => null,
            'failure_stage' => null,
            'failure_category' => null,
            'dispatch_state' => $dispatchState,
            'provider_http_status' => null,
            'elapsed_ms' => null,
            'provider_request_id' => null,
            'created_at' => now(),
        ]);
    }
}
