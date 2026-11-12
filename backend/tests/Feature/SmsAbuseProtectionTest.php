<?php

namespace Tests\Feature;

use App\Services\OtpService;
use App\Services\SmsIrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SmsAbuseProtectionTest extends TestCase
{
    use RefreshDatabase;

    private int $messages = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->fakeSms();
    }

    private function fakeSms(bool $fail = false): void
    {
        $this->app->bind(SmsIrService::class, function () use ($fail) {
            $mock = Mockery::mock(SmsIrService::class);
            $mock->shouldReceive('sendVerify')
                ->zeroOrMoreTimes()
                ->andReturnUsing(function () use ($fail) {
                    $this->messages++;
                    if ($fail) {
                        throw new RuntimeException('Provider timeout: delivery unknown');
                    }
                    return ['message_id' => 123, 'cost' => 0];
                });
            return $mock;
        });
    }

    public function test_six_per_hour_is_shared_across_purposes(): void
    {
        $otp = $this->app->make(OtpService::class);
        for ($i = 0; $i < 6; $i++) {
            $otp->send('purpose-' . $i, '09121111111');
        }

        $this->assertSame(6, $this->messages);
        try {
            $otp->send('purpose-6', '09121111111');
            $this->fail('Expected phone quota to reject seventh send.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('mobile', $e->errors());
        }
        $this->assertSame(6, $this->messages);
    }

    public function test_fifteen_per_day_is_shared_across_purposes(): void
    {
        config(['sms_abuse.per_phone_hour' => 100]);
        $otp = $this->app->make(OtpService::class);
        for ($i = 0; $i < 15; $i++) {
            $otp->send('purpose-' . $i, '09122222222');
        }
        $this->assertSame(15, $this->messages);
        $this->expectException(ValidationException::class);
        $otp->send('purpose-15', '09122222222');
    }

    public function test_equivalent_phone_formats_share_one_allowance(): void
    {
        config(['sms_abuse.per_phone_hour' => 1]);
        $otp = $this->app->make(OtpService::class);
        $otp->send('register', '09123333333');
        $this->expectException(ValidationException::class);
        $otp->send('forgot-password', '+989123333333');
    }

    public function test_cooldown_rejection_does_not_consume_quota(): void
    {
        config(['sms_abuse.per_phone_hour' => 2]);
        $otp = $this->app->make(OtpService::class);
        $otp->send('register', '09124444444');
        try {
            $otp->send('register', '09124444444');
            $this->fail('Expected cooldown.');
        } catch (ValidationException) {
        }
        $otp->send('forgot-password', '09124444444');
        $this->assertSame(2, $this->messages);
    }

    public function test_provider_timeout_reserves_allowance_conservatively(): void
    {
        config(['sms_abuse.per_phone_hour' => 1]);
        $this->fakeSms(true);
        $otp = $this->app->make(OtpService::class);
        try {
            $otp->send('register', '09125555555');
            $this->fail('Expected provider timeout.');
        } catch (RuntimeException) {
        }
        $this->assertFalse($otp->has('register', '09125555555'));
        $this->fakeSms();
        $otp = $this->app->make(OtpService::class);
        $this->expectException(ValidationException::class);
        $otp->send('forgot-password', '09125555555');
    }
}
