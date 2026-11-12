<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PhoneChangeService
{
    private const PURPOSE = 'profile-phone-change';

    public function __construct(private readonly OtpService $otp) {}

    public function request(User $user, string $newPhone): array
    {
        $newPhone = $this->normalize($newPhone);
        $this->assertAvailable($user, $newPhone);

        $challengeId = bin2hex(random_bytes(24));
        $currentPhone = (string) $user->phone;

        $result = $this->otp->send(self::PURPOSE, $newPhone, [
            'user_id' => $user->getKey(),
            'previous_phone' => $currentPhone,
        ]);

        $this->saveChallenge($user, $challengeId, $newPhone, $currentPhone, $result['expires_in']);

        return [
            'challenge_id' => $challengeId,
            'expires_in' => $result['expires_in'],
            'retry_after' => $result['retry_after'],
        ];
    }

    public function resend(User $user, string $challengeId): array
    {
        $challenge = $this->getChallenge($user, $challengeId);
        $this->assertAvailable($user, $challenge['new_phone']);
        $result = $this->otp->send(self::PURPOSE, $challenge['new_phone'], [
            'user_id' => $user->getKey(),
            'previous_phone' => $challenge['previous_phone'],
        ]);
        $this->saveChallenge(
            $user,
            $challengeId,
            $challenge['new_phone'],
            $challenge['previous_phone'],
            $result['expires_in'],
        );

        return [
            'challenge_id' => $challengeId,
            'expires_in' => $result['expires_in'],
            'retry_after' => $result['retry_after'],
        ];
    }

    public function verify(User $user, string $challengeId, string $code): User
    {
        $lock = Cache::lock('tkb:phone-change:lock:user:' . $user->getKey(), 15);

        return $lock->block(5, function () use ($user, $challengeId, $code): User {
            $challenge = $this->getChallenge($user, $challengeId);

            return DB::transaction(function () use ($user, $challenge, $challengeId, $code): User {
                /** @var User $locked */
                $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

                if ((string) $locked->phone !== $challenge['previous_phone']) {
                    $this->invalidChallenge();
                }

                $this->assertAvailable($locked, $challenge['new_phone']);

                $payload = $this->otp->verify(self::PURPOSE, $challenge['new_phone'], $code);
                $metadata = $payload['metadata'] ?? null;

                if (! is_array($metadata)
                    || (int) ($metadata['user_id'] ?? 0) !== (int) $locked->getKey()
                    || (string) ($metadata['previous_phone'] ?? '') !== $challenge['previous_phone']) {
                    $this->invalidChallenge();
                }

                $locked->forceFill([
                    'phone' => $challenge['new_phone'],
                    'phone_verified_at' => now(),
                ])->save();

                Cache::forget($this->challengeKey($challengeId));
                Cache::forget($this->activeKey($locked));

                // Keep this authenticated session; revoke other Sanctum tokens.
                $currentToken = $user->currentAccessToken();
                if ($currentToken && method_exists($currentToken, 'getKey') && $currentToken->getKey()) {
                    $locked->tokens()->where('id', '!=', $currentToken->getKey())->delete();
                } else {
                    $locked->tokens()->delete();
                }

                return $locked->refresh();
            });
        });
    }

    private function normalize(string $phone): string
    {
        $phone = trim($phone);
        $phone = strtr($phone, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ]);
        $phone = preg_replace('/[\s()-]/u', '', $phone) ?? '';
        if (str_starts_with($phone, '+98')) {
            $phone = '0' . substr($phone, 3);
        } elseif (str_starts_with($phone, '98')) {
            $phone = '0' . substr($phone, 2);
        }

        if (! preg_match('/^09\d{9}$/D', $phone)) {
            throw ValidationException::withMessages(['new_phone' => ['Enter a valid Iranian mobile number.']]);
        }

        return $phone;
    }

    private function assertAvailable(User $user, string $phone): void
    {
        if ($phone === (string) $user->phone) {
            throw ValidationException::withMessages(['new_phone' => ['Enter a different mobile number.']]);
        }
        if (User::query()->where('phone', $phone)->where('id', '!=', $user->getKey())->exists()) {
            throw ValidationException::withMessages(['new_phone' => ['This mobile number is already registered.']]);
        }
    }

    private function saveChallenge(User $user, string $id, string $phone, string $previous, int $expires): void
    {
        $ttl = now()->addSeconds($expires);
        Cache::put($this->challengeKey($id), [
            'user_id' => $user->getKey(),
            'new_phone' => $phone,
            'previous_phone' => $previous,
        ], $ttl);
        Cache::put($this->activeKey($user), $id, $ttl);
    }

    private function getChallenge(User $user, string $id): array
    {
        $challenge = Cache::get($this->challengeKey($id));
        if (! is_array($challenge)
            || (int) ($challenge['user_id'] ?? 0) !== (int) $user->getKey()
            || Cache::get($this->activeKey($user)) !== $id
            || ! is_string($challenge['new_phone'] ?? null)
            || ! is_string($challenge['previous_phone'] ?? null)
            || (string) $user->phone !== $challenge['previous_phone']) {
            $this->invalidChallenge();
        }
        return $challenge;
    }

    private function challengeKey(string $id): string
    {
        return 'tkb:phone-change:challenge:' . hash('sha256', $id);
    }

    private function activeKey(User $user): string
    {
        return 'tkb:phone-change:active:user:' . $user->getKey();
    }

    private function invalidChallenge(): never
    {
        throw ValidationException::withMessages(['challenge_id' => ['The phone change request is invalid or expired.']]);
    }
}
