<?php

namespace App\Services\ShoppingAssistant;

final class ShoppingAssistantContextualFollowUp
{
    /** @param array<int,array{role?:string,content?:string}> $history */
    public static function isAnswerToLastQuestion(string $message, array $history): bool
    {
        $text = trim($message);
        if ($text === '' || mb_strlen($text) > 60 || preg_match('~(?:https?://|www\.)~iu', $text) === 1
            || preg_match('/[?؟]/u', $text) === 1) {
            return false;
        }
        for ($i = count($history) - 1; $i >= 0; --$i) {
            $entry = $history[$i];
            if (! is_array($entry) || ! in_array($entry['role'] ?? null, ['assistant', 'user'], true)) {
                continue;
            }
            return $entry['role'] === 'assistant'
                && is_string($entry['content'] ?? null)
                && preg_match('/[?؟]/u', $entry['content']) === 1;
        }
        return false;
    }

    /**
     * Only conversation-anchored messages can bypass the generic introduction.
     * An unrelated request must continue through the normal catalog router.
     *
     * @param array<int,array{role?:string,content?:string}> $history
     */
    public static function isContinuation(string $message, array $history, array $previousProductIds = []): bool
    {
        if ($history === [] || trim($message) === '' || mb_strlen($message) > 240
            || preg_match('~(?:https?://|www\.)~iu', $message) === 1) {
            return false;
        }
        // LUNA_HOUSEHOLD_CONTINUATION_V23
        // Household-size replies are a continuation only when the most recent
        // customer turn was about a purchase. Do not use assistant wording as evidence.
        if (preg_match(
            '/(?:Ø®Ø§Ù†ÙˆØ§Ø¯Ù‡.{0,40}(?:Ù†ÙØ±Ù‡|Ù†ÙØ±)|(?:[0-9Û°-Û¹]+|ÛŒÚ©|Ø¯Ùˆ|Ø³Ù‡|Ú†Ù‡Ø§Ø±|Ù¾Ù†Ø¬|Ø´Ø´)[â€Œ\s-]*Ù†ÙØ±Ù‡|family of\s+[0-9]+)/iu',
            $message
        ) === 1) {
            for ($i = count($history) - 1; $i >= 0; --$i) {
                $entry = $history[$i];
                if (! is_array($entry) || ($entry['role'] ?? null) !== 'user') {
                    continue;
                }
                $prior = (string) ($entry['content'] ?? '');
                if (preg_match(
                    '/(?:Ù‚Ø§Ø¨Ù„Ù…Ù‡|Ø¸Ø±ÙˆÙ|Ø³Ø±ÙˆÛŒØ³|Ø¢Ø´Ù¾Ø²ÛŒ|Ø®Ø±ÛŒØ¯|Ù…Ø­ØµÙˆÙ„|cookware|pots|pans|shopping)/iu',
                    $prior
                ) === 1) {
                    return true;
                }
                break;
            }
        }
        if (self::isAnswerToLastQuestion($message, $history)) {
            return true;
        }
        // Explicit conversational references, comparison, selection, specifications
        // and requests to change the response language are not new product searches.
        if (preg_match('/(?:\b(?:which one|choose one|between (?:them|those)|compare (?:them|these)|both|same (?:one|product)|its weight|how much does it weigh|tell me (?:its|the) weight|reply in farsi|answer in persian|farsi javab|lotfan farsi|in persian)\b|از بین|گزینه(?:[‌\s-]*های)?(?:\s+اصلی)?|کدوم(?:ش|و| رو)?|کدام(?:ش| رو)?|همون|همان|اون|این دو|هر دو|هردوش|وزن دقیق(?:ش)?|سبک بودن|دوام|برای (?:همین|این)|لطفا(?:ً)? فارسی|فارسی جواب|فقط فارسی|\b(?:javab bede|pishnahad moshakhas)\b)/iu', $message) === 1) {
            return true;
        }
        if ($previousProductIds !== [] && preg_match('/(?:چی پیشنهاد|پیشنهاد می(?:‌|\s*)کنی|بهم پیشنهاد|انتخاب کن|نظرت چیه|چه پیشنهادی|چقدر وزن|چه جنسی|مشخصات|سازگار|مناسبه|better|recommend|suggest|what do you think|how heavy)/iu', $message) === 1) {
            return true;
        }
        // An explicit answer to a previously asked cooking/hob preference question
        // may be longer than a single word or omit the question mark.
        if (preg_match('/(?:صفحه[‌\s-]*ای|القایی نیست|برقی(?:ه|ِ معمولی)?|گازی|خانواده(?:‌|\s)*[0-9۰-۹]|نفره)/iu', $message) === 1) {
            $lastAssistant = null;
            for ($i = count($history) - 1; $i >= 0; --$i) {
                if (($history[$i]['role'] ?? null) === 'assistant') {
                    $lastAssistant = $history[$i]['content'] ?? null;
                    break;
                }
            }
            return is_string($lastAssistant)
                && preg_match('/(?:اجاق|برقی|القایی|گازی|خانواده|نفر|hob|stove|cooktop|family)/iu', $lastAssistant) === 1;
        }
        return false;
    }
}
