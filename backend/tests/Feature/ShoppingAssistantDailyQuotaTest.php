<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ShoppingAssistant\ShoppingAssistantDailyQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ShoppingAssistantDailyQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'shopping_assistant.enabled' => true,
            'shopping_assistant.driver' => 'gapgpt',
            'shopping_assistant.daily_quota_enabled' => true,
            'shopping_assistant.api_key' => 'offline-test-key',
            'shopping_assistant.daily_budget_usd' => null,
            'shopping_assistant.monthly_budget_usd' => null,
            'cache.default' => 'array',
        ]);
        Cache::flush();
        RateLimiter::for('shopping-assistant', static fn (Request $request) => [
            Limit::perMinute(1000)->by('quota-test-minute:' . $request->ip()),
            Limit::perHour(1000)->by('quota-test-hour:' . $request->ip()),
        ]);
        Http::preventStrayRequests();
    }

    public function test_daily_quota_can_be_disabled_without_disabling_provider(): void
    {
        config()->set('shopping_assistant.daily_quota_enabled', false);
        $this->fakeSuccessfulProvider();
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        for ($i = 0; $i < 25; $i++) {
            $this->postAssistant('Recommend cookware.', 'en', '198.51.100.55')->assertOk();
        }

        Http::assertSentCount(25);
    }

    public function test_authenticated_user_is_limited_to_twenty_luna_requests_across_ips(): void
    {
        $this->fakeSuccessfulProvider();
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        for ($request = 0; $request < 20; $request++) {
            $this->postAssistant('Recommend cookware.', 'en', '198.51.100.' . ($request + 1))
                ->assertOk();
        }

        $this->postAssistant('Recommend cookware.', 'en', '203.0.113.8')
            ->assertStatus(429)
            ->assertJsonPath(
                'message',
                'The daily limit of 20 AI assistant requests has been reached. Please try again tomorrow.'
            );

        Http::assertSentCount(20);
    }

    public function test_daily_quota_is_configurable_without_changing_default_limit(): void
    {
        config()->set('shopping_assistant.daily_quota_messages', 2);
        $quota = app(ShoppingAssistantDailyQuota::class);
        $quota->admit('user:configurable-quota', 'en');
        $quota->admit('user:configurable-quota', 'en');
        try {
            $quota->admit('user:configurable-quota', 'en');
            $this->fail('The third request should exceed the configured limit.');
        } catch (\App\Exceptions\ShoppingAssistantDailyQuotaExceeded $exception) {
            $this->assertSame('The daily limit of 2 AI assistant requests has been reached. Please try again tomorrow.', $exception->getMessage());
        }
    }

    public function test_guest_is_limited_per_ip_with_localized_quota_message(): void
    {
        $this->fakeSuccessfulProvider();
        for ($request = 0; $request < 20; $request++) {
            $this->postAssistant('Recommend cookware.', 'fa', '198.51.100.55')
                ->assertOk();
        }

        $this->postAssistant('برای قابلمه پیشنهاد بده.', 'fa', '198.51.100.55')
            ->assertStatus(429)
            ->assertJsonPath(
                'message',
                'سهمیه روزانه ۲۰ درخواست دستیار هوشمند به پایان رسیده است. فردا دوباره تلاش کنید.'
            );

        Http::assertSentCount(20);
    }

    public function test_daily_quota_resets_at_midnight_in_tehran(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 20:29:00', 'UTC'));
        $quota = app(ShoppingAssistantDailyQuota::class);
        for ($request = 0; $request < 20; $request++) {
            $quota->admit('user:daily-reset-test', 'en');
        }
        try {
            $quota->admit('user:daily-reset-test', 'en');
            $this->fail('The quota should be exhausted before midnight Tehran time.');
        } catch (\App\Exceptions\ShoppingAssistantDailyQuotaExceeded) {
            $this->assertTrue(true);
        }

        $this->travelTo(Carbon::parse('2026-11-01 20:31:00', 'UTC'));
        $quota->admit('user:daily-reset-test', 'en');
        $this->assertTrue(true);
    }

    public function test_atomic_admission_allows_only_twenty_competing_attempts(): void
    {
        $quota = app(ShoppingAssistantDailyQuota::class);
        $admitted = 0;
        $rejected = 0;

        foreach (range(1, 40) as $attempt) {
            try {
                $quota->admit('ip:198.51.100.91', 'en');
                $admitted++;
            } catch (\App\Exceptions\ShoppingAssistantDailyQuotaExceeded) {
                $rejected++;
            }
        }

        $this->assertSame(20, $admitted);
        $this->assertSame(20, $rejected);
    }

    public function test_nonbillable_and_mock_responses_do_not_consume_quota(): void
    {
        config()->set('shopping_assistant.driver', 'mock');
        $this->postAssistant('What are the store contact details?', 'en', '198.51.100.22')
            ->assertOk()
            ->assertJsonPath('data.usage.provider', 'mock');
        $this->postAssistant('Recommend cookware.', 'en', '198.51.100.22')
            ->assertOk()
            ->assertJsonPath('data.usage.provider', 'mock');
        Http::assertSentCount(0);

        config()->set('shopping_assistant.driver', 'gapgpt');
        $this->postAssistant('What are the store contact details?', 'en', '198.51.100.22')
            ->assertOk()
            ->assertJsonPath('data.usage.input_tokens', 0);
        Http::assertSentCount(0);

        $quota = app(ShoppingAssistantDailyQuota::class);
        for ($request = 0; $request < 20; $request++) {
            $quota->admit('ip:198.51.100.22', 'en');
        }
        $this->assertTrue(true);
    }

    public function test_dispatched_provider_failure_still_consumes_one_daily_request(): void
    {
        $requestCount = 0;
        Http::fake(function () use (&$requestCount) {
            $requestCount++;
            if ($requestCount === 1) {
                return Http::response([], 503);
            }

            return Http::response([
                'choices' => [[
                    'message' => ['content' => 'A valid response after the failure.'],
                ]],
                'usage' => ['prompt_tokens' => 80, 'completion_tokens' => 12],
            ]);
        });

        $this->postAssistant('Recommend cookware.', 'en', '198.51.100.77')
            ->assertStatus(503);

        $quota = app(ShoppingAssistantDailyQuota::class);
        for ($request = 0; $request < 19; $request++) {
            $quota->admit('ip:198.51.100.77', 'en');
        }
        try {
            $quota->admit('ip:198.51.100.77', 'en');
            $this->fail('The dispatched provider failure must remain charged.');
        } catch (\App\Exceptions\ShoppingAssistantDailyQuotaExceeded) {
            Http::assertSentCount(1);
        }
    }

    private function postAssistant(string $message, string $locale, string $ip)
    {
        RateLimiter::clear('shopping-assistant-ip-minute:' . $ip);
        RateLimiter::clear('shopping-assistant-ip-hour:' . $ip);

        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/assistant/chat?locale=' . $locale, [
                'message' => $message,
                'locale' => $locale,
            ]);
    }

    private function fakeSuccessfulProvider(): void
    {
        Http::fake(fn () => Http::response([
            'choices' => [[
                'message' => ['content' => 'I can help you explore cookware options.'],
            ]],
            'usage' => ['prompt_tokens' => 80, 'completion_tokens' => 12],
        ]));
    }
}
