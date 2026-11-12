<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SmsIrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class LoginOtpApiTest extends TestCase
{
    use RefreshDatabase;

    private ?string $capturedOtp = null;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function createCustomer(array $attributes = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'otp-login@example.com',
            'phone' => '09120000000',
            'password' => 'password123',
            'status' => 'active',
            'phone_verified_at' => now(),
        ], $attributes));
    }

    private function fakeSms(): void
    {
        $this->app->bind(
            SmsIrService::class,
            function () {
                return Mockery::mock(
                    SmsIrService::class,
                    function ($mock) {
                        $mock->shouldReceive('sendVerify')
                            ->zeroOrMoreTimes()
                            ->withArgs(function (
                                string $mobile,
                                string $code
                            ) {
                                $this->capturedOtp = $code;

                                return true;
                            })
                            ->andReturn([
                                'message_id' => 123456,
                                'cost' => 0,
                            ]);
                    }
                );
            }
        );
    }

    public function test_active_customer_can_request_login_otp(): void
    {
        $this->createCustomer();
        $this->fakeSms();

        $this->postJson('/api/auth/login/request-otp', [
            'mobile' => '09120000000',
        ])
            ->assertOk()
            ->assertJsonPath('expires_in', 120)
            ->assertJsonPath('retry_after', 60);

        $this->assertNotNull($this->capturedOtp);
        $this->assertMatchesRegularExpression(
            '/^[0-9]{6}$/',
            $this->capturedOtp
        );
    }

    public function test_unknown_mobile_does_not_send_login_otp(): void
    {
        $this->fakeSms();

        $this->postJson('/api/auth/login/request-otp', [
            'mobile' => '09120000001',
        ])->assertOk();

        $this->assertNull($this->capturedOtp);
    }

    public function test_inactive_customer_does_not_receive_login_otp(): void
    {
        $this->createCustomer([
            'status' => 'inactive',
        ]);

        $this->fakeSms();

        $this->postJson('/api/auth/login/request-otp', [
            'mobile' => '09120000000',
        ])->assertOk();

        $this->assertNull($this->capturedOtp);
    }

    public function test_invalid_login_otp_is_rejected(): void
    {
        $this->createCustomer();
        $this->fakeSms();

        $this->postJson('/api/auth/login/request-otp', [
            'mobile' => '09120000000',
        ])->assertOk();

        $this->postJson('/api/auth/login/verify-otp', [
            'mobile' => '09120000000',
            'code' => '000000',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_valid_login_otp_returns_authenticated_user_and_token(): void
    {
        $user = $this->createCustomer();
        $this->fakeSms();

        $this->postJson('/api/auth/login/request-otp', [
            'mobile' => $user->phone,
        ])->assertOk();

        $this->assertNotNull($this->capturedOtp);

        $response = $this->postJson('/api/auth/login/verify-otp', [
            'mobile' => $user->phone,
            'code' => $this->capturedOtp,
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'user',
                'token',
            ]);

        $token = $response->json('token');

        $this->assertNotEmpty($token);

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk();
    }

    public function test_login_otp_cannot_be_reused(): void
    {
        $user = $this->createCustomer();
        $this->fakeSms();

        $this->postJson('/api/auth/login/request-otp', [
            'mobile' => $user->phone,
        ])->assertOk();

        $code = $this->capturedOtp;

        $this->assertNotNull($code);

        $this->postJson('/api/auth/login/verify-otp', [
            'mobile' => $user->phone,
            'code' => $code,
        ])->assertOk();

        $this->postJson('/api/auth/login/verify-otp', [
            'mobile' => $user->phone,
            'code' => $code,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }
}