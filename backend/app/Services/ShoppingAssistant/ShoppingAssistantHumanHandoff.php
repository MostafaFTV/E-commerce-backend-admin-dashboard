<?php

declare(strict_types=1);

namespace App\Services\ShoppingAssistant;

final class ShoppingAssistantHumanHandoff
{
    /**
     * Only unambiguous requests for a person. Avoid intercepting a shopper's
     * ordinary mention of an employee or product description.
     */
    public static function requested(string $message): bool
    {
        return preg_match(
            '/(?:\b(?:speak|talk|chat|connect)\s+(?:to|with)\s+(?:a\s+|an\s+)?(?:real\s+)?(?:human|person|representative|sales\s+advisor|staff\s+member)\b|\b(?:human\s+agent|live\s+agent)\b|(?:می[‌\s]*خوام|می[‌\s]*خواهم|میشه|می[‌\s]*شه|لطفا|لطفاً|می[‌\s]*تونم|می[‌\s]*توانم).{0,45}(?:با\s+(?:یک\s+|یه\s+)?(?:آدم\s+واقعی|فروشنده|مشاور\s+فروش|کارشناس|اپراتور)).{0,30}(?:صحبت|حرف|ارتباط)|(?:ارتباط|صحبت|حرف).{0,35}(?:با\s+(?:یک\s+|یه\s+)?(?:آدم\s+واقعی|فروشنده|مشاور\s+فروش|کارشناس|اپراتور)))/iu',
            $message
        ) === 1;
    }

    public static function message(string $locale): string
    {
        return $locale === 'fa'
            ? 'برای صحبت با مشاور فروش، لطفاً از راه‌های ارتباطی تأییدشده فروشگاه استفاده کنید. من نمی‌توانم تماس یا انتقال زنده را خودم برقرار کنم.'
            : 'For help from a sales advisor, please use the store’s verified contact options. I cannot initiate a live transfer or contact someone on your behalf.';
    }
}
