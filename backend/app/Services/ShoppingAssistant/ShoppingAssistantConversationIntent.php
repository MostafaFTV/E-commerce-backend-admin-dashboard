<?php

namespace App\Services\ShoppingAssistant;

final class ShoppingAssistantConversationIntent
{
    public static function wantsProductLink(string $message): bool
    {
        $text = mb_strtolower($message);
        return preg_match('/(?:\blink\b|\burl\b|product\s+page|page\s+for|\bwebsite\b|لینک|صفحه\s*محصول|آدرس\s*محصول)/iu', $text) === 1;
    }

    public static function selectedIndex(string $message): ?int
    {
        $text = mb_strtolower($message);
        if (preg_match('/(?:\b(?:first|1st|number\s*1|option\s*1)\b|اولی|گزینه\s*[۱1])/iu', $text)) return 0;
        if (preg_match('/(?:\b(?:second|2nd|number\s*2|option\s*2)\b|دومی|گزینه\s*[۲2])/iu', $text)) return 1;
        if (preg_match('/(?:\b(?:third|3rd|number\s*3|option\s*3)\b|سومی|گزینه\s*[۳3])/iu', $text)) return 2;
        return null;
    }
}
