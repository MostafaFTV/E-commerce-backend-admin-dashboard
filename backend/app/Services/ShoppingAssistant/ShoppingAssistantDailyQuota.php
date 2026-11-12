<?php

namespace App\Services\ShoppingAssistant;

use App\Exceptions\ShoppingAssistantUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class ShoppingAssistantDailyQuota
{
    public function admit(string $subject, string $locale): void
    {
        if (trim($subject) === '') {
            throw new ShoppingAssistantUnavailable(
                'Assistant quota identity is unavailable.',
                diagnostics: [
                    'failure_stage' => 'daily_quota',
                    'failure_category' => 'quota_identity_missing',
                    'dispatch_state' => 'not_dispatched',
                ]
            );
        }

        $limit = (int) config('shopping_assistant.daily_quota_messages', 20);
        if ($limit < 1 || $limit > 10000) {
            throw new ShoppingAssistantUnavailable('Assistant daily quota configuration is invalid.', diagnostics: [
                'failure_stage' => 'daily_quota',
                'failure_category' => 'invalid_quota_configuration',
                'dispatch_state' => 'not_dispatched',
            ]);
        }

        $now = CarbonImmutable::now('Asia/Tehran');
        $date = $now->format('Y-m-d');
        $identity = hash('sha256', $subject);
        $key = 'shopping-assistant:daily-quota:' . $date . ':' . $identity;
        $lockKey = $key . ':lock';
        $ttlSeconds = max(1, $now->diffInSeconds($now->addDay()->startOfDay()));
        $store = (string) config('cache.stores.' . config('cache.default') . '.driver');

        if (app()->environment('production') && ! in_array($store, ['redis', 'database'], true)) {
            throw new ShoppingAssistantUnavailable(
                'Shared assistant quota storage is unavailable.',
                diagnostics: [
                    'failure_stage' => 'daily_quota',
                    'failure_category' => 'quota_cache_not_shared',
                    'dispatch_state' => 'not_dispatched',
                ]
            );
        }

        try {
            Cache::lock($lockKey, 10)->block(5, function () use ($key, $ttlSeconds, $locale, $limit): void {
                $count = (int) Cache::get($key, 0);
                if ($count >= $limit) {
                    throw new \App\Exceptions\ShoppingAssistantDailyQuotaExceeded(
                        $locale === 'fa'
                            ? 'سهمیه روزانه ' . str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string) $limit) . ' درخواست دستیار هوشمند به پایان رسیده است. فردا دوباره تلاش کنید.'
                            : 'The daily limit of ' . $limit . ' AI assistant requests has been reached. Please try again tomorrow.'
                    );
                }

                if (! Cache::put($key, $count + 1, $ttlSeconds)) {
                    throw new RuntimeException('Assistant quota could not be persisted.');
                }
            });
        } catch (\App\Exceptions\ShoppingAssistantDailyQuotaExceeded $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ShoppingAssistantUnavailable(
                'Shared assistant quota storage is unavailable.',
                previous: $exception,
                diagnostics: [
                    'failure_stage' => 'daily_quota',
                    'failure_category' => 'quota_cache_unavailable',
                    'dispatch_state' => 'not_dispatched',
                ]
            );
        }
    }
}
