<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ShoppingAssistant\ShoppingAssistantConsultationMemory;
use App\Services\ShoppingAssistant\ShoppingAssistantConsultationPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ShoppingAssistantConsultationMemoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('shopping_assistant.driver', 'mock');
        Http::preventStrayRequests();
    }

    public function test_extracts_only_explicit_preferences_and_updates_drink_type(): void
    {
        $initial = ShoppingAssistantConsultationMemory::observe([], 'آشپزخانه کوچیک دارم و یه چیز جمع‌وجور برای نوشیدنی می‌خوام', 'fa');
        $this->assertSame('small', $initial['preferences']['kitchen_space']);
        $this->assertSame('compact', $initial['preferences']['product_size']);
        $this->assertSame('drinks', $initial['preferences']['usage']);
        $next = ShoppingAssistantConsultationMemory::observe($initial, 'قهوه', 'fa');
        $this->assertSame('coffee', $next['preferences']['drink_type']);
        $changed = ShoppingAssistantConsultationMemory::observe($next, 'نه، چای', 'fa');
        $this->assertSame('tea', $changed['preferences']['drink_type']);
    }

    public function test_explicit_corrections_remove_old_priorities(): void
    {
        $first = ShoppingAssistantConsultationMemory::observe([], 'قابلمه سبک و بادوام میخوام', 'fa');
        $this->assertSame('lightweight,durable', $first['preferences']['cookware_priorities']);
        $corrected = ShoppingAssistantConsultationMemory::observe($first, 'نه، سبک نمیخوام', 'fa');
        $this->assertSame('durable', $corrected['preferences']['cookware_priorities']);
        $revised = ShoppingAssistantConsultationMemory::observe($corrected, 'دیگه دوام مهم نیست', 'fa');
        $this->assertArrayNotHasKey('cookware_priorities', $revised['preferences']);
    }

    public function test_negated_compact_preference_is_not_preserved(): void
    {
        $first = ShoppingAssistantConsultationMemory::observe([], 'یه وسیله جمع وجور میخوام', 'fa');
        $this->assertSame('compact', $first['preferences']['product_size']);
        $corrected = ShoppingAssistantConsultationMemory::observe($first, 'نه، جمع وجور نمیخوام', 'fa');
        $this->assertArrayNotHasKey('product_size', $corrected['preferences']);
    }

    public function test_session_memory_is_server_owned_and_preserved_across_requests(): void
    {
        $first = $this->postJson('/api/assistant/chat?locale=fa', [
            'message' => 'سلام',
        ])->assertOk();
        $session = $first->json('data.conversation');
        $this->assertSame(60, $session['expires_in_minutes']);
        $second = $this->postJson('/api/assistant/chat?locale=fa', [
            'message' => 'قهوه',
            'conversation_id' => $session['id'],
            'session_token' => $session['session_token'],
        ])->assertOk();
        $stored = Cache::get('shopping-assistant:session:' . $session['id']);
        $this->assertSame('coffee', $stored['consultation']['preferences']['drink_type']);
        $this->assertSame(2, $stored['consultation']['version']);
        $this->assertArrayNotHasKey('consultation', $second->json('data'));
        Http::assertSentCount(0);
    }

    public function test_configured_longer_ttl_never_extends_beyond_one_hour(): void
    {
        config()->set('shopping_assistant.session_ttl_minutes', 120);
        $first = $this->postJson('/api/assistant/chat?locale=en', [
            'message' => 'Hello',
        ])->assertOk();
        $this->assertSame(60, $first->json('data.conversation.expires_in_minutes'));
    }

    public function test_invalid_token_cannot_read_or_mutate_memory(): void
    {
        $first = $this->postJson('/api/assistant/chat?locale=en', [
            'message' => 'Hello',
        ])->assertOk();
        $session = $first->json('data.conversation');
        $key = 'shopping-assistant:session:' . $session['id'];
        $before = Cache::get($key);
        $this->postJson('/api/assistant/chat?locale=en', [
            'message' => 'Coffee',
            'conversation_id' => $session['id'],
            'session_token' => str_repeat('0', 64),
        ])->assertNotFound();
        $this->assertSame($before, Cache::get($key));
    }

    public function test_planner_does_not_treat_unverified_preferences_as_catalog_facts(): void
    {
        $state = ShoppingAssistantConsultationMemory::observe([], 'I need a compact coffee maker', 'en');
        $context = ShoppingAssistantConsultationPlanner::context($state);
        $this->assertSame('compact', $context['explicit_customer_preferences']['product_size']);
        $this->assertArrayNotHasKey('products', $context);
        $this->assertSame('clarify', ShoppingAssistantConsultationPlanner::stage($state, ['tool' => 'conversation_follow_up', 'product_cards' => []], 'Which drink?'));
    }

    public function test_expired_session_cannot_be_reused(): void
    {
        $first = $this->postJson('/api/assistant/chat?locale=en', [
            'message' => 'Hello',
        ])->assertOk();
        $session = $first->json('data.conversation');
        $this->travel(61)->minutes();
        $this->postJson('/api/assistant/chat?locale=en', [
            'message' => 'Coffee',
            'conversation_id' => $session['id'],
            'session_token' => $session['session_token'],
        ])->assertNotFound();
    }
}
