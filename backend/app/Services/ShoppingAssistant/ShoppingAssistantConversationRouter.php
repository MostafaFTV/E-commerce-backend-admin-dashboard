<?php

namespace App\Services\ShoppingAssistant;

final class ShoppingAssistantConversationRouter
{
    public static function page(string $message): ?array
    {
        $text = mb_strtolower(trim($message));

        $routes = [
            'contact' => [
                'path' => '/contact',
                'fa' => 'تماس با ما',
                'en' => 'Contact',
            ],
            'about' => [
                'path' => '/about',
                'fa' => 'درباره ما',
                'en' => 'About us',
            ],
            'faq' => [
                'path' => '/faq',
                'fa' => 'پرسش‌های متداول',
                'en' => 'FAQ',
            ],
            'products' => [
                'path' => '/products',
                'fa' => 'محصولات',
                'en' => 'Products',
            ],
            'collections' => [
                'path' => '/collections',
                'fa' => 'کالکشن‌ها',
                'en' => 'Collections',
            ],
        ];

        $request = preg_match(
            '/(?:لینک|آدرس|صفحه|بخش|برو به|نشون بده|بفرست|بیار|link|url|page|navigate|open|take me to|where is)/iu',
            $text
        ) === 1;

        if (! $request) {
            return null;
        }

        if (preg_match('/(?:تماس با ما|ارتباط با ما|contact(?: us)?)/iu', $text)) {
            return $routes['contact'];
        }

        if (preg_match('/(?:درباره ما|درباره فروشگاه|\babout\s+us\b|\babout\s+(?:the\s+)?(?:store|shop|company|brand)\b)/iu', $text)) {
            return $routes['about'];
        }

        if (preg_match('/(?:سوالات متداول|سؤالات متداول|پرسش(?:‌|\s*)های متداول|faq|frequently asked)/iu', $text)) {
            return $routes['faq'];
        }

        if (preg_match('/(?:کالکشن(?:‌|\s*)ها|مجموعه(?:‌|\s*)ها|collections?)/iu', $text)) {
            return $routes['collections'];
        }

        if (preg_match('/(?:همه محصولات|صفحه محصولات|محصولات فروشگاه|all products|products page|product catalog|catalog page)/iu', $text)) {
            return $routes['products'];
        }

        return null;
    }

    public static function optionIndex(string $message): ?int
    {
        $text = mb_strtolower(trim($message));

        $patterns = [
            0 => '/(?:\b(?:first|1st|option\s*1|number\s*1)\b|اولی|گزینه(?:\s|‌)*اول|شماره(?:\s|‌)*یک)/iu',
            1 => '/(?:\b(?:second|2nd|option\s*2|number\s*2)\b|دومی|گزینه(?:\s|‌)*دوم|شماره(?:\s|‌)*دو)/iu',
            2 => '/(?:\b(?:third|3rd|option\s*3|number\s*3)\b|سومی|گزینه(?:\s|‌)*سوم|شماره(?:\s|‌)*سه)/iu',
            3 => '/(?:\b(?:fourth|4th|option\s*4|number\s*4)\b|چهارمی|گزینه(?:\s|‌)*چهارم)/iu',
            4 => '/(?:\b(?:fifth|5th|option\s*5|number\s*5)\b|پنجمی|گزینه(?:\s|‌)*پنجم)/iu',
        ];

        foreach ($patterns as $index => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $index;
            }
        }

        return null;
    }

    public static function comparing(string $message): bool
    {
        return preg_match(
            '/(?:\b(?:compare|comparison|versus|vs\.?|difference|which one|which is better|which would you|between them|both|them)\b|مقایسه|تفاوت|کدوم بهتر|کدام بهتر|بین این|بینشون|هر دو)/iu',
            $message
        ) === 1;
    }
}