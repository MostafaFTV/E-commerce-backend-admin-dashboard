<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\SmsIrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class PhoneChangeApiTest extends TestCase
{
    use RefreshDatabase;

    private ?string $sentCode = null;
    private ?string $sentMobile = null;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->app->bind(SmsIrService::class, function () {
            $mock = Mockery::mock(SmsIrService::class);
            $mock->shouldReceive('sendVerify')->zeroOrMoreTimes()
                ->andReturnUsing(function (string $mobile, string $code) {
                    $this->sentMobile = $mobile;
                    $this->sentCode = $code;
                    return ['message_id' => 1, 'cost' => 0];
                });
            return $mock;
        });
    }

    private function account(string $phone = '09120000000'): User
    {
        return User::factory()->create([
            'phone' => $phone,
            'status' => 'active',
            'phone_verified_at' => now()->subDay(),
        ]);
    }

    private function requestCode(User $user, string $phone = '09121111111'): string
    {
        return (string) $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/phone-change/request-otp', ['new_phone' => $phone])
            ->assertOk()
            ->assertJsonStructure(['data' => ['challenge_id', 'expires_in', 'retry_after']])
            ->json('data.challenge_id');
    }

    public function test_guest_cannot_request_or_verify_phone_change(): void
    {
        $this->postJson('/api/profile/phone-change/request-otp', ['new_phone' => '09121111111'])
            ->assertUnauthorized();
        $this->postJson('/api/profile/phone-change/verify-otp', [
            'challenge_id' => str_repeat('a', 48), 'code' => '123456',
        ])->assertUnauthorized();
    }

    public function test_valid_request_sends_sms_to_new_phone_without_password(): void
    {
        $user = $this->account();
        $this->requestCode($user, '+989121111111');
        $this->assertSame('989121111111', $this->sentMobile);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $this->sentCode);
        $this->assertSame('09120000000', $user->fresh()->phone);
    }

    public function test_invalid_existing_and_same_phone_are_rejected(): void
    {
        $user = $this->account();
        $this->account('09122222222');
        foreach (['12345', '09120000000', '09122222222'] as $phone) {
            $this->actingAs($user, 'sanctum')->postJson('/api/profile/phone-change/request-otp', [
                'new_phone' => $phone,
            ])->assertUnprocessable()->assertJsonValidationErrors(['new_phone']);
        }
        $this->assertNull($this->sentCode);
    }

    public function test_valid_otp_changes_phone_and_cannot_be_replayed(): void
    {
        $user = $this->account();
        $id = $this->requestCode($user);
        $code = $this->sentCode;
        $this->assertNotNull($code);

        $this->actingAs($user, 'sanctum')->postJson('/api/profile/phone-change/verify-otp', [
            'challenge_id' => $id, 'code' => $code,
        ])->assertOk()->assertJsonPath('data.phone', '09121111111');

        $this->assertSame('09121111111', $user->fresh()->phone);
        $this->assertNotNull($user->fresh()->phone_verified_at);

        $this->actingAs($user, 'sanctum')->postJson('/api/profile/phone-change/verify-otp', [
            'challenge_id' => $id, 'code' => $code,
        ])->assertUnprocessable();
    }

    public function test_wrong_code_does_not_change_phone(): void
    {
        $user = $this->account();
        $id = $this->requestCode($user);
        $wrong = $this->sentCode === '999999' ? '888888' : '999999';
        $this->actingAs($user, 'sanctum')->postJson('/api/profile/phone-change/verify-otp', [
            'challenge_id' => $id, 'code' => $wrong,
        ])->assertUnprocessable()->assertJsonValidationErrors(['code']);
        $this->assertSame('09120000000', $user->fresh()->phone);
    }

    public function test_challenge_cannot_be_used_by_other_account(): void
    {
        $user = $this->account();
        $other = $this->account('09123333333');
        $id = $this->requestCode($user);
        $this->actingAs($other, 'sanctum')->postJson('/api/profile/phone-change/verify-otp', [
            'challenge_id' => $id, 'code' => $this->sentCode,
        ])->assertUnprocessable()->assertJsonValidationErrors(['challenge_id']);
        $this->assertSame('09123333333', $other->fresh()->phone);
    }

    public function test_resend_respects_cooldown_and_preserves_challenge(): void
    {
        $user = $this->account();
        $id = $this->requestCode($user);
        $this->actingAs($user, 'sanctum')->postJson('/api/profile/phone-change/resend-otp', [
            'challenge_id' => $id,
        ])->assertUnprocessable();

        $this->travel(61)->seconds();
        $this->actingAs($user, 'sanctum')->postJson('/api/profile/phone-change/resend-otp', [
            'challenge_id' => $id,
        ])->assertOk()->assertJsonPath('data.challenge_id', $id);
    }

    public function test_expired_challenge_is_rejected(): void
    {
        $user = $this->account();
        $id = $this->requestCode($user);
        $this->travel(121)->seconds();
        $this->actingAs($user, 'sanctum')->postJson('/api/profile/phone-change/verify-otp', [
            'challenge_id' => $id, 'code' => $this->sentCode,
        ])->assertUnprocessable()->assertJsonValidationErrors(['challenge_id']);
    }
}
