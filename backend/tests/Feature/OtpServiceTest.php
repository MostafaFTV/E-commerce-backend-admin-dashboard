<?php

namespace Tests\Feature;

use App\Services\OtpService;
use App\Services\SmsIrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    private ?string $capturedOtp = null;

    private ?string $capturedMobile = null;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->fakeSms();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function fakeSms(): void
    {
        $this->app->bind(
            SmsIrService::class,
            function () {
                return Mockery::mock(
                    SmsIrService::class,
                    function ($mock) {
                        $mock
                            ->shouldReceive('sendVerify')
                            ->zeroOrMoreTimes()
                            ->withArgs(
                                function (
                                    string $mobile,
                                    string $code
                                ) {
                                    $this->capturedMobile = $mobile;
                                    $this->capturedOtp = $code;

                                    return true;
                                }
                            )
                            ->andReturn([
                                'message_id' => 123456,
                                'cost' => 0,
                            ]);
                    }
                );
            }
        );
    }

    private function service(): OtpService
    {
        return $this->app->make(
            OtpService::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Code Generation
    |--------------------------------------------------------------------------
    */

    public function test_generated_otp_is_six_digits(): void
    {
        $service = $this->service();

        $service->send(
            'test-purpose',
            '09120000000'
        );

        $this->assertNotNull(
            $this->capturedOtp
        );

        $this->assertMatchesRegularExpression(
            '/^[0-9]{6}$/',
            $this->capturedOtp
        );
    }

    /*
    |--------------------------------------------------------------------------
    | OTP Send
    |--------------------------------------------------------------------------
    */

    public function test_send_generates_and_delivers_otp(): void
    {
        $result = $this->service()->send(
            'test-purpose',
            '09120000000'
        );

        $this->assertNotNull(
            $this->capturedOtp
        );

        $this->assertMatchesRegularExpression(
            '/^[0-9]{6}$/',
            $this->capturedOtp
        );

        $this->assertSame(
            120,
            $result['expires_in']
        );

        $this->assertSame(
            60,
            $result['retry_after']
        );
    }

    public function test_send_applies_cooldown_for_same_purpose_and_subject(): void
    {
        $service = $this->service();

        $service->send(
            'test-purpose',
            '09120000001'
        );

        $this->assertNotNull(
            $this->capturedOtp
        );

        $this->capturedOtp = null;

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $service->send(
            'test-purpose',
            '09120000001'
        );
    }

    public function test_different_purposes_are_isolated(): void
    {
        $service = $this->service();

        $service->send(
            'register',
            '09120000002'
        );

        $registerOtp = $this->capturedOtp;

        $this->capturedOtp = null;

        $service->send(
            'forgot-password',
            '09120000002'
        );

        $forgotOtp = $this->capturedOtp;

        $this->assertNotNull(
            $registerOtp
        );

        $this->assertNotNull(
            $forgotOtp
        );

        $this->assertNotSame(
            $registerOtp,
            $forgotOtp
        );
    }

    public function test_different_subjects_are_isolated(): void
    {
        $service = $this->service();

        $service->send(
            'forgot-password',
            '09120000003'
        );

        $firstOtp = $this->capturedOtp;

        $this->capturedOtp = null;

        $service->send(
            'forgot-password',
            '09120000004'
        );

        $secondOtp = $this->capturedOtp;

        $this->assertNotNull(
            $firstOtp
        );

        $this->assertNotNull(
            $secondOtp
        );

        $this->assertNotSame(
            $firstOtp,
            $secondOtp
        );
    }

    public function test_sms_failure_does_not_store_otp_or_cooldown(): void
    {
        $this->app->bind(
            SmsIrService::class,
            function () {
                return Mockery::mock(
                    SmsIrService::class,
                    function ($mock) {
                        $mock
                            ->shouldReceive('sendVerify')
                            ->once()
                            ->andThrow(
                                new \RuntimeException(
                                    'SMS.ir sandbox failure.'
                                )
                            );
                    }
                );
            }
        );

        $service = $this->app->make(
            OtpService::class
        );

        $this->expectException(
            \RuntimeException::class
        );

        try {
            $service->send(
                'test-purpose',
                '09120000017'
            );
        } finally {
            $this->assertFalse(
                $service->has(
                    'test-purpose',
                    '09120000017'
                )
            );

            $cooldownKey =
                'tkb:otp:cooldown:test-purpose:'
                . hash(
                    'sha256',
                    '09120000017'
                );

            $this->assertFalse(
                Cache::has($cooldownKey)
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | OTP Verification
    |--------------------------------------------------------------------------
    */

    public function test_valid_otp_can_be_verified(): void
    {
        $service = $this->service();

        $service->send(
            'test-purpose',
            '09120000005'
        );

        $this->assertNotNull(
            $this->capturedOtp
        );

        $payload = $service->verify(
            'test-purpose',
            '09120000005',
            $this->capturedOtp
        );

        $this->assertIsArray(
            $payload
        );

        $this->assertSame(
            'test-purpose',
            $payload['purpose']
        );

        $this->assertSame(
            '09120000005',
            $payload['subject']
        );
    }

    public function test_valid_otp_is_one_time_only(): void
    {
        $service = $this->service();

        $service->send(
            'test-purpose',
            '09120000006'
        );

        $otp = $this->capturedOtp;

        $this->assertNotNull(
            $otp
        );

        $service->verify(
            'test-purpose',
            '09120000006',
            $otp
        );

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $service->verify(
            'test-purpose',
            '09120000006',
            $otp
        );
    }

    public function test_wrong_otp_is_rejected(): void
    {
        $service = $this->service();

        $service->send(
            'test-purpose',
            '09120000007'
        );

        $this->assertNotNull(
            $this->capturedOtp
        );

        $wrongCode = $this->capturedOtp === '999999'
            ? '888888'
            : '999999';

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $service->verify(
            'test-purpose',
            '09120000007',
            $wrongCode
        );
    }

    public function test_wrong_otp_does_not_consume_valid_otp_immediately(): void
    {
        $service = $this->service();

        $service->send(
            'test-purpose',
            '09120000008'
        );

        $this->assertNotNull(
            $this->capturedOtp
        );

        $validOtp = $this->capturedOtp;

        $wrongCode = $validOtp === '999999'
            ? '888888'
            : '999999';

        try {
            $service->verify(
                'test-purpose',
                '09120000008',
                $wrongCode
            );
        } catch (
            \Illuminate\Validation\ValidationException
        ) {
        }

        $payload = $service->verify(
            'test-purpose',
            '09120000008',
            $validOtp
        );

        $this->assertIsArray(
            $payload
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Purpose / Subject Binding
    |--------------------------------------------------------------------------
    */

    public function test_otp_cannot_be_verified_for_another_purpose(): void
    {
        $service = $this->service();

        $service->send(
            'register',
            '09120000009'
        );

        $this->assertNotNull(
            $this->capturedOtp
        );

        $otp = $this->capturedOtp;

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $service->verify(
            'forgot-password',
            '09120000009',
            $otp
        );
    }

    public function test_otp_cannot_be_verified_for_another_subject(): void
    {
        $service = $this->service();

        $service->send(
            'forgot-password',
            '09120000010'
        );

        $this->assertNotNull(
            $this->capturedOtp
        );

        $otp = $this->capturedOtp;

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $service->verify(
            'forgot-password',
            '09120000011',
            $otp
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Metadata
    |--------------------------------------------------------------------------
    */

    public function test_send_preserves_metadata(): void
    {
        $service = $this->service();

        $metadata = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ];

        $service->send(
            'register',
            '09120000012',
            $metadata
        );

        $this->assertNotNull(
            $this->capturedOtp
        );

        $payload = $service->verify(
            'register',
            '09120000012',
            $this->capturedOtp
        );

        $this->assertSame(
            $metadata,
            $payload['metadata']
        );
    }

    public function test_empty_metadata_is_supported(): void
    {
        $service = $this->service();

        $service->send(
            'forgot-password',
            '09120000013'
        );

        $this->assertNotNull(
            $this->capturedOtp
        );

        $payload = $service->verify(
            'forgot-password',
            '09120000013',
            $this->capturedOtp
        );

        $this->assertSame(
            [],
            $payload['metadata']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SMS Mobile Normalization
    |--------------------------------------------------------------------------
    */

    public function test_iranian_mobile_is_converted_to_international_sms_format(): void
    {
        $service = $this->service();

        $service->send(
            'test-purpose',
            '09120000014'
        );

        $this->assertSame(
            '989120000014',
            $this->capturedMobile
        );

        $this->assertNotNull(
            $this->capturedOtp
        );
    }

    public function test_plus_98_mobile_is_supported(): void
    {
        $service = $this->service();

        $service->send(
            'test-purpose',
            '+989120000015'
        );

        $this->assertSame(
            '989120000015',
            $this->capturedMobile
        );

        $this->assertNotNull(
            $this->capturedOtp
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Attempts
    |--------------------------------------------------------------------------
    */

    public function test_otp_is_removed_after_maximum_failed_attempts(): void
    {
        $service = $this->service();

        $service->send(
            'test-purpose',
            '09120000016'
        );

        $this->assertNotNull(
            $this->capturedOtp
        );

        $wrongCode = $this->capturedOtp === '999999'
            ? '888888'
            : '999999';

        for ($i = 1; $i <= 5; $i++) {
            try {
                $service->verify(
                    'test-purpose',
                    '09120000016',
                    $wrongCode
                );
            } catch (
                \Illuminate\Validation\ValidationException
            ) {
            }
        }

        $this->assertFalse(
            $service->has(
                'test-purpose',
                '09120000016'
            )
        );

        $this->expectException(
            \Illuminate\Validation\ValidationException::class
        );

        $service->verify(
            'test-purpose',
            '09120000016',
            $wrongCode
        );
    }
}
