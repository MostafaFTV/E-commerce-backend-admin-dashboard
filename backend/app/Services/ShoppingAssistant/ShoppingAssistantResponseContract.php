<?php

declare(strict_types=1);

namespace App\Services\ShoppingAssistant;

/**
 * Optional model response contract. This class does not grant product authority:
 * only server-resolved card IDs can be displayed.
 */
final class ShoppingAssistantResponseContract
{
    /** @return array{content: string, recommended_product_ids: array<int, int>}|null */
    public static function parse(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '' || strlen($raw) > 20000) {
            return null;
        }

        // Some providers return natural-language prose followed by a V2 JSON
        // object. Parse the object, but accept it only if its message matches
        // the prose exactly (apart from whitespace); otherwise fail closed.
        $preface = null;
        if ($raw[0] !== '{') {
            if (preg_match('/(?:^|\R)\s*(\{\s*"response_version"\s*:\s*2\b[\s\S]*\})\s*$/u', $raw, $matches, PREG_OFFSET_CAPTURE) !== 1) {
                return null;
            }
            $preface = trim(substr($raw, 0, $matches[1][1]));
            $raw = trim($matches[1][0]);
        }

        try {
            $value = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($value)
            || ($value['response_version'] ?? null) !== 2
            || ! is_string($value['message'] ?? null)
            || trim($value['message']) === ''
            || mb_strlen($value['message']) > 4000
            || ! is_array($value['recommended_product_ids'] ?? null)
            || ! array_is_list($value['recommended_product_ids'])
            || count($value['recommended_product_ids']) > 3
        ) {
            return null;
        }

        $ids = $value['recommended_product_ids'];
        foreach ($ids as $id) {
            if (! is_int($id) || $id <= 0) {
                return null;
            }
        }
        if (count(array_unique($ids)) !== count($ids)) {
            return null;
        }

        $message = trim($value['message']);
        if ($preface !== null
            && preg_replace('/\s+/u', ' ', $preface) !== preg_replace('/\s+/u', ' ', $message)
        ) {
            // An inconsistent object must never replace what was visibly said.
            return null;
        }
        if (isset($value['next_question']) && is_string($value['next_question'])) {
            $question = trim($value['next_question']);
            if ($question !== '' && mb_strlen($question) <= 250 && ! str_contains($message, $question)) {
                $message .= "\n\n" . $question;
            }
        }

        return ['content' => $message, 'recommended_product_ids' => $ids];
    }

    /**
     * A response explicitly attempting the V2 object contract must never be
     * shown as raw JSON if it is malformed. Ordinary prose and generic JSON
     * which is not a Luna response contract retain their legacy behavior.
     */
    public static function looksLikeBrokenContract(string $raw): bool
    {
        $prefix = substr($raw, 0, 20000);

        // Fail closed on malformed or mismatched contracts, including a
        // JSON object appended to natural language. Do not show internal JSON.
        return preg_match('/\{[\s\S]{0,2000}"(?:response_version|recommended_product_ids)"\s*:/u', $prefix) === 1;
    }

    public static function safeRecoveryMessage(string $locale): string
    {
        return $locale === 'fa'
            ? 'برای ادامه انتخاب، می‌توانیم گزینه‌های معتبر کاتالوگ را بررسی کنیم. اگر محصول مشخصی مدنظر دارید، نام یا کاربردش را بفرمایید.'
            : 'We can continue with verified catalog options. Tell me the product or use case you have in mind.';
    }

    /**
     * @param array<int, int> $ids
     * @param array<int, array<string, mixed>> $trustedCards
     * @return array<int, array<string, mixed>>
     */
    public static function verifiedCards(array $ids, array $trustedCards): array
    {
        if (count($ids) > 3 || count(array_unique($ids)) !== count($ids)) {
            return [];
        }

        $byId = [];
        foreach ($trustedCards as $card) {
            if (is_array($card) && is_int($card['id'] ?? null) && $card['id'] > 0) {
                $byId[$card['id']] = $card;
            }
        }
        $selected = [];
        foreach ($ids as $id) {
            if (! is_int($id) || $id <= 0 || ! isset($byId[$id])) {
                // A partly invalid selection is not trustworthy; fail closed.
                return [];
            }
            $selected[] = $byId[$id];
        }

        return $selected;
    }
}
