<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OtpService
{
    public function __construct(
        protected SmsIrService $smsIrService,
        protected SmsAbuseProtection $smsAbuseProtection
    ) {}

    /**
     * Generate and send an OTP.
     *
     * The OTP itself is never returned to the caller
     * and never stored in plaintext.
     *
     * @param array<string,mixed> $metadata
     * @return array{
     *     expires_in:int,
     *     retry_after:int
     * }
     */
    public function send(
        string $purpose,
        string $subject,
        array $metadata = []
    ): array {
        $subject = trim($subject);

        if ($subject === '') {
            throw new RuntimeException(
                'OTP subject cannot be empty.'
            );
        }

        $cooldownKey = $this->cooldownKey(
            $purpose,
            $subject
        );

        $this->assertCooldownElapsed($cooldownKey);

        $length = max(
            4,
            (int) config(
                'smsir.otp.length',
                6
            )
        );

        $expires = max(
            30,
            (int) config(
                'smsir.otp.expires',
                120
            )
        );

        $cooldown = max(
            1,
            (int) config(
                'smsir.otp.resend_cooldown',
                60
            )
        );

        $code = $this->generateCode(
            $length
        );

        $payload = [
            'code_hash' => $this->hashCode(
                $code
            ),
            'attempts' => 0,
            'purpose' => $purpose,
            'subject' => $subject,
            'metadata' => $metadata,
            'created_at' => now()->toISOString(),
            'expires_at' => now()
                ->addSeconds($expires)
                ->toISOString(),
        ];

        /*
        |--------------------------------------------------------------------------
        | Send SMS before storing the OTP
        |--------------------------------------------------------------------------
        |
        | If SMS.ir fails, we do not leave behind a valid OTP that
        | the customer never received.
        |
        |--------------------------------------------------------------------------
        */

        // One distributed lock per phone protects the shared hourly/daily
        // allowance AND serializes sending across all OTP purposes.
        $this->smsAbuseProtection->execute(
            $subject,
            fn () => $this->assertCooldownElapsed($cooldownKey),
            function () use (
                $subject,
                $code,
                $purpose,
                $payload,
                $expires,
                $cooldownKey,
                $cooldown
            ): void {
                $this->smsIrService->sendVerify(
                    $this->smsMobile($subject),
                    $code
                );

                Cache::put(
                    $this->otpKey($purpose, $subject),
                    $payload,
                    now()->addSeconds($expires)
                );

                Cache::put(
                    $cooldownKey,
                    true,
                    now()->addSeconds($cooldown)
                );
            }
        );

        return [
            'expires_in' => $expires,
            'retry_after' => $cooldown,
        ];
    }

    protected function assertCooldownElapsed(string $cooldownKey): void
    {
        if (! Cache::has($cooldownKey)) {
            return;
        }

        $ttl = $this->remainingTtl($cooldownKey);

        throw ValidationException::withMessages([
            'mobile' => [
                "Please wait {$ttl} seconds before requesting another code.",
            ],
        ]);
    }

    /**
     * Verify an OTP.
     *
     * Verification is protected by a distributed lock keyed to the
     * purpose + subject so concurrent requests cannot both consume
     * the same OTP successfully.
     *
     * @return array<string,mixed>
     */
    public function verify(
        string $purpose,
        string $subject,
        string $code
    ): array {
        $subject = trim($subject);
        $code = trim($code);

        if ($subject === '') {
            throw ValidationException::withMessages([
                'code' => [
                    'The verification code is invalid or expired.',
                ],
            ]);
        }

        if ($code === '') {
            throw ValidationException::withMessages([
                'code' => [
                    'The verification code is incorrect.',
                ],
            ]);
        }

        $key = $this->otpKey(
            $purpose,
            $subject
        );

        /*
        |--------------------------------------------------------------------------
        | Distributed OTP Verification Lock
        |--------------------------------------------------------------------------
        |
        | All read / attempt-update / consume operations for one OTP must
        | happen atomically from the application's perspective.
        |
        | Redis-backed cache is used in production, while Laravel's cache
        | locking abstraction keeps this implementation driver-independent
        | where supported.
        |
        |--------------------------------------------------------------------------
        */

        $lock = Cache::lock(
            $this->verifyLockKey(
                $purpose,
                $subject
            ),
            10
        );

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'code' => [
                    'The verification request is busy. Please try again.',
                ],
            ]);
        }

        try {
            $payload = Cache::get($key);

            if (! is_array($payload)) {
                throw ValidationException::withMessages([
                    'code' => [
                        'The verification code is invalid or expired.',
                    ],
                ]);
            }

            $maxAttempts = max(
                1,
                (int) config(
                    'smsir.otp.max_attempts',
                    5
                )
            );

            $attempts = (int) (
                $payload['attempts'] ?? 0
            );

            if ($attempts >= $maxAttempts) {
                Cache::forget($key);

                throw ValidationException::withMessages([
                    'code' => [
                        'The verification code has expired.',
                    ],
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Constant-Time Hash Comparison
            |--------------------------------------------------------------------------
            */

            $storedHash = (string) (
                $payload['code_hash'] ?? ''
            );

            $providedHash = $this->hashCode(
                $code
            );

            if (
                $storedHash === ''
                || ! hash_equals(
                    $storedHash,
                    $providedHash
                )
            ) {
                $attempts++;

                if ($attempts >= $maxAttempts) {
                    Cache::forget($key);
                } else {
                    $payload['attempts'] = $attempts;

                    $remaining = $this->remainingTtl(
                        $key
                    );

                    Cache::put(
                        $key,
                        $payload,
                        now()->addSeconds(
                            max(1, $remaining)
                        )
                    );
                }

                throw ValidationException::withMessages([
                    'code' => [
                        'The verification code is incorrect.',
                    ],
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Consume OTP
            |--------------------------------------------------------------------------
            |
            | Because this happens while holding the distributed lock,
            | another concurrent request cannot consume the same OTP.
            |
            |--------------------------------------------------------------------------
            */

            Cache::forget($key);

            return $payload;
        } finally {
            $lock->release();
        }
    }

    /**
     * Check whether an OTP currently exists.
     */
    public function has(
        string $purpose,
        string $subject
    ): bool {
        return Cache::has(
            $this->otpKey(
                $purpose,
                trim($subject)
            )
        );
    }

    /**
     * Invalidate an OTP.
     */
    public function forget(
        string $purpose,
        string $subject
    ): void {
        Cache::forget(
            $this->otpKey(
                $purpose,
                trim($subject)
            )
        );
    }

    /**
     * Generate numeric OTP.
     */
    protected function generateCode(
        int $length
    ): string {
        $min = 10 ** ($length - 1);
        $max = (10 ** $length) - 1;

        return (string) random_int(
            $min,
            $max
        );
    }

    /**
     * Hash OTP using the application key.
     */
    protected function hashCode(
        string $code
    ): string {
        return hash_hmac(
            'sha256',
            $code,
            (string) config('app.key')
        );
    }

    /**
     * OTP cache key.
     */
    protected function otpKey(
        string $purpose,
        string $subject
    ): string {
        return 'tkb:otp:'
            . $purpose
            . ':'
            . hash(
                'sha256',
                trim($subject)
            );
    }

    /**
     * Distributed verification lock key.
     */
    protected function verifyLockKey(
        string $purpose,
        string $subject
    ): string {
        return 'tkb:otp:verify-lock:'
            . $purpose
            . ':'
            . hash(
                'sha256',
                trim($subject)
            );
    }

    /**
     * Cooldown cache key.
     */
    protected function cooldownKey(
        string $purpose,
        string $subject
    ): string {
        return 'tkb:otp:cooldown:'
            . $purpose
            . ':'
            . hash(
                'sha256',
                trim($subject)
            );
    }

    /**
     * Return remaining TTL of a cache key.
     */
    protected function remainingTtl(
        string $key
    ): int {
        /*
        |--------------------------------------------------------------------------
        | Redis
        |--------------------------------------------------------------------------
        |
        | Laravel's cache repository does not expose a common TTL method
        | across every driver, so we keep a conservative value here.
        |
        |--------------------------------------------------------------------------
        */

        try {
            $store = Cache::getStore();

            if (
                method_exists(
                    $store,
                    'getRedis'
                )
            ) {
                $redis = $store->getRedis();
                $prefix = $store->getPrefix();

                $ttl = $redis
                    ->connection()
                    ->ttl(
                        $prefix . $key
                    );

                if ($ttl > 0) {
                    return (int) $ttl;
                }
            }
        } catch (\Throwable) {
            // Fall back to configured cooldown.
        }

        return max(
            1,
            (int) config(
                'smsir.otp.resend_cooldown',
                60
            )
        );
    }

    /**
     * Normalize mobile number for SMS provider.
     *
     * TKB continues storing the Iranian 09xxxxxxxxx format,
     * while provider requests can use the international format.
     */
    protected function smsMobile(
        string $mobile
    ): string {
        $mobile = preg_replace(
            '/\s+/',
            '',
            trim($mobile)
        );

        if (
            is_string($mobile)
            && str_starts_with(
                $mobile,
                '+98'
            )
        ) {
            return substr(
                $mobile,
                1
            );
        }

        if (
            is_string($mobile)
            && str_starts_with(
                $mobile,
                '98'
            )
        ) {
            return $mobile;
        }

        if (
            is_string($mobile)
            && str_starts_with(
                $mobile,
                '09'
            )
        ) {
            return '98' . substr(
                $mobile,
                1
            );
        }

        return (string) $mobile;
    }
}
