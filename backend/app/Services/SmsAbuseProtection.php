<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Cross-purpose per-mobile SMS allowances.
 *
 * Counts attempts reserved BEFORE contacting the external SMS provider.
 * Reservations are deliberately retained when delivery fails or times out:
 * a timeout can mean an SMS was charged/sent despite the HTTP error.
 */
class SmsAbuseProtection
{
    public function execute(string $mobile, Closure $guard, Closure $dispatch): mixed
    {
        $phone = $this->normalizedPhone($mobile);
        $digest = hash('sha256', $phone);
        $lock = Cache::lock('tkb:sms:quota-lock:' . $digest, 30);

        try {
            return $lock->block(5, function () use ($digest, $guard, $dispatch) {
                // Cooldown is rechecked within the lock so concurrent
                // requests cannot both send for the same phone/purpose.
                $guard();

                $windows = [
                    ['1h', 3600, max(1, (int) config('sms_abuse.per_phone_hour', 6))],
                    ['24h', 86400, max(1, (int) config('sms_abuse.per_phone_day', 15))],
                ];

                $updates = [];

                foreach ($windows as [$label, $seconds, $limit]) {
                    $key = "tkb:sms:quota:{$label}:{$digest}";
                    $state = Cache::get($key);
                    $now = now()->getTimestamp();

                    if (! is_array($state)
                        || (int) ($state['reset_at'] ?? 0) <= $now) {
                        $state = ['count' => 0, 'reset_at' => $now + $seconds];
                    }

                    if ((int) $state['count'] >= $limit) {
                        throw ValidationException::withMessages([
                            'mobile' => ['SMS request limit reached. Please try again later.'],
                        ]);
                    }

                    $updates[] = [$key, [
                        'count' => ((int) $state['count']) + 1,
                        'reset_at' => (int) $state['reset_at'],
                    ]];
                }

                // Both counters are reserved while holding the same phone
                // lock; a storage exception fails closed before dispatch.
                foreach ($updates as [$key, $state]) {
                    Cache::put(
                        $key,
                        $state,
                        max(1, $state['reset_at'] - now()->getTimestamp())
                    );
                }

                return $dispatch();
            });
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'mobile' => ['SMS request is busy. Please try again.'],
            ]);
        }
    }

    private function normalizedPhone(string $mobile): string
    {
        $mobile = preg_replace('/\s+/', '', trim($mobile)) ?? '';

        if (str_starts_with($mobile, '+98')) {
            return '0' . substr($mobile, 3);
        }

        if (str_starts_with($mobile, '98')) {
            return '0' . substr($mobile, 2);
        }

        return $mobile;
    }
}
