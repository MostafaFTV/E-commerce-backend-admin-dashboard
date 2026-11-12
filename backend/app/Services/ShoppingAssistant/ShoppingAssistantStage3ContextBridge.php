<?php

declare(strict_types=1);

namespace App\Services\ShoppingAssistant;

/**
 * Narrow contextual rescue for a specific user follow-up in a shopping dialogue.
 * NEVER treats history as a source of verified product specifications.
 */
final class ShoppingAssistantStage3ContextBridge
{
    /** @param array<int, array<string, mixed>> $history */
    public static function needsContinuation(string $message, array $history): bool
    {
        $text = trim($message);
        if ($history === [] || $text === '' || strlen($text) > 880
            || preg_match('~(?:https?://|www\.)~iu', $text) === 1) {
            return false;
        }
        // Must have conversational evidence in the history. Without it, isolated
        // pronouns and short requests must not be assumed to name a product.
        $seen = false;
        $shoppingAnchor = false;
        foreach (array_slice($history, -8) as $turn) {
            if (! is_array($turn) || ! in_array($turn['role'] ?? null, ['user', 'assistant'], true)) {
                continue;
            }
            $prior = (string) ($turn['content'] ?? '');
            if ($prior === '') { continue; }
            $seen = true;
            if (preg_match('/(?:قابلمه|کتری|اجاق|سرویس|ست|محصول|گزینه|خرید|آشپزی|درب|ظرف|پیشنهاد|کابینت|رنگ|مدل|cook|stove|hob|product|option|bottle|kettle|serving|color|dishwasher|kitchen)/iu', $prior) === 1) {
                $shoppingAnchor = true;
            }
        }
        if (! $seen || ! $shoppingAnchor) { return false; }
        // Explicit changes, callbacks to earlier selections, or constraints.
        return preg_match('/(?:راستی.{0,90}(?:عوض|الان|القایی)|(?:الان|دیگه).{0,70}(?:القایی|گازی)|(?:نه|فکر کردم).{0,100}(?:ست|وسیل|می(?:‌|\s*)خوام)|یادتون نره|قبلاً جواب دادم|دومی|اولی|آخری|اون یکی|این یکی|دربش|آبی هم|برای چهار|برای شش|(?:actually|forget|instead|not that one|the other one|for four|for six|which bottle|that one|the previous one|easy dishwasher cleanup|need to serve six))/iu', $text) === 1;
    }
}
