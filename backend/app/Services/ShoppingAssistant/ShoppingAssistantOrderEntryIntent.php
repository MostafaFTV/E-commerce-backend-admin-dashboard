<?php

namespace App\Services\ShoppingAssistant;

final class ShoppingAssistantOrderEntryIntent
{
    public static function isDirectOrderEntry(string $message): bool
    {
        $text = trim($message);
        if ($text === '') {
            return false;
        }

        // Do not confuse tracking, cancellation or returns with placing an order.
        if (preg_match('/(?:پیگیری|وضعیت|لغو|مرجوع|ارسال|تحویل|تاخیر|تأخیر|tracking|track|cancel|return|refund|where is my order|order status)/iu', $text) === 1) {
            return false;
        }

        return preg_match('/(?:از\s*کجا|کجا|چطور|چگونه|کدوم\s*راه|چه\s*طوری|راهنمایی).{0,75}(?:سفارش(?:م|مو|م رو)?\s*(?:رو|را)?\s*ثبت|ثبت\s*سفارش|سفارش\s*بدم|خرید\s*کنم)|(?:سفارش(?:م|مو)?\s*(?:رو|را)?\s*ثبت|ثبت\s*سفارش).{0,55}(?:کجا|چطور|چگونه|از\s*کجا)|(?:where|how).{0,65}(?:place an order|order online|check out|checkout)|(?:place an order|order online|check out|checkout).{0,65}(?:where|how)/iu', $text) === 1;
    }
}
