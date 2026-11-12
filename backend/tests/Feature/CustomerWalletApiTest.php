<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerWalletApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_wallet_endpoints(): void
    {
        $this->getJson('/api/wallet')->assertUnauthorized();
        $this->getJson('/api/wallet/credits')->assertUnauthorized();
        $this->getJson('/api/wallet/transactions')->assertUnauthorized();
    }

    public function test_customer_without_wallet_receives_zero_balances_and_empty_history(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/wallet')->assertOk()
            ->assertJsonPath('data.wallet_exists', false)
            ->assertJsonPath('data.available', '0.00')
            ->assertJsonPath('data.pending', '0.00');

        $this->getJson('/api/wallet/credits')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/wallet/transactions')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_customer_wallet_queries_are_scoped_to_authenticated_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Wallet::query()->create(['user_id' => $owner->id, 'currency' => 'IRT']);
        Wallet::query()->create(['user_id' => $other->id, 'currency' => 'IRT']);
        Sanctum::actingAs($owner);

        $this->getJson('/api/wallet')->assertOk()->assertJsonPath('data.wallet_exists', true);
        $this->getJson('/api/wallet/credits')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/wallet/transactions')->assertOk()->assertJsonCount(0, 'data');
    }
}