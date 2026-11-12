<?php

namespace App\Services\ShoppingAssistant;

final class ShoppingAssistantLanguage
{
    public static function detect(string $message, string $fallback): string
    {
        $letterCount = preg_match_all('/\p{L}/u', $message);
        $persianCount = preg_match_all('/[\x{0600}-\x{06FF}]/u', $message);
        $latinCount = preg_match_all('/\p{Latin}/u', $message);
        $preferred = in_array($fallback, ['fa', 'en'], true) ? $fallback : 'en';

        if (! is_int($letterCount) || $letterCount < 3) {
            return $preferred;
        }
        if (is_int($persianCount) && $persianCount > 0 && $persianCount >= (int) $latinCount) {
            return 'fa';
        }
        if (is_int($latinCount) && $latinCount > 0) {
            return self::isFinglish($message) ? 'fa' : 'en';
        }
        return $preferred;
    }

    public static function isFinglish(string $message): bool
    {
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $message) === 1) {
            return false;
        }
        $normalized = ' ' . mb_strtolower(preg_replace('/[^a-z]+/i', ' ', $message) ?? '') . ' ';
        $hits = 0;
        foreach ([' salam ', ' ye ', ' man ', ' mikham ', ' mikhastam ', ' baraye ', ' khone ', ' khoob ', ' ghahve ', ' saz ', ' lotfan ', ' farsi ', ' javab ', ' bede ', ' pishnahad ', ' moshakhas ', ' che ', ' kodom ', ' hame ', ' faghat ', ' chi ', ' mitoni '] as $word) {
            if (str_contains($normalized, $word)) { $hits++; }
        }
        return $hits >= 2;
    }

    /** Only normalize unmistakable product categories for catalog search, not facts. */
    public static function catalogSearchMessage(string $message): string
    {
        if (! self::isFinglish($message)) { return $message; }
        if (preg_match('/\bghahve\s*(?:saz|maker)\b/i', $message) === 1) {
            return 'قهوه ساز';
        }
        if (preg_match('/\b(?:ghablam|ghablame|ghablename|ghablam[e]?)\b/i', $message) === 1) {
            return 'قابلمه';
        }
        return $message;
    }
}
