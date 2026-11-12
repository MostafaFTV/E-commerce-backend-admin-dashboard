<?php

namespace App\Services\ShoppingAssistant;

use App\Contracts\ShoppingAssistantProvider;

class MockShoppingAssistantProvider implements ShoppingAssistantProvider
{
    public function complete(
        string $locale,
        array $history,
        array $catalogContext,
        int $maxOutputTokens
    ): array {
        $products = $catalogContext['products'] ?? [];
        $tool = $catalogContext['tool'] ?? '';

        if ($tool === 'conversation_follow_up') {
            $content = $locale === 'fa'
                ? 'متوجه شدم. برای اینکه بهتر راهنمایی‌تان کنم، نوع استفاده مدنظرتان را کمی دقیق‌تر می‌فرمایید؟'
                : 'Understood. What type of use do you have in mind so I can narrow it down?';
        } elseif ($tool === 'list_categories') {
            $content = $locale === 'fa'
                ? ($catalogContext['categories'] === []
                    ? 'در حال حاضر دسته‌بندی فعالی برای نمایش نداریم.'
                    : 'دسته‌بندی‌های فعال فروشگاه در کارت‌های زیر نمایش داده شده‌اند.')
                : ($catalogContext['categories'] === []
                    ? 'There are no active categories to show right now.'
                    : 'Here are the store’s active categories.');
        } elseif ($tool === 'get_store_information') {
            $content = $locale === 'fa'
                ? ($catalogContext['stores'] === []
                    ? 'اطلاعات فروشگاه در حال حاضر در دسترس نیست.'
                    : 'اطلاعات شعب فعال فروشگاه را در ادامه می‌بینید.')
                : ($catalogContext['stores'] === []
                    ? 'Store information is not available right now.'
                    : 'Here is information for the active store locations.');
        } elseif ($tool === 'purchase_guidance') {
            $content = $locale === 'fa'
                ? ($products === []
                    ? 'اگر محصولی مدنظرتان است نامش را بفرمایید تا صفحه آن را پیدا کنم. برای تأیید جزئیات خرید و ارسال با فروشگاه تماس بگیرید.'
                    : 'برای ادامه، صفحه محصول را ببینید. برای تأیید روش خرید، ارسال یا جزئیاتی که اینجا ثبت نشده‌اند، با فروشگاه تماس بگیرید.')
                : ($products === []
                    ? 'Tell me which product you are considering and I can find its page. Contact the store to confirm purchase and delivery details.'
                    : 'To continue, review the product page. Contact the store to confirm purchase or delivery details that are not listed here.');
        } elseif ($products === []) {
            $content = $locale === 'fa'
                ? 'متأسفانه محصولی با این مشخصات در کاتالوگ فعال پیدا نشد.'
                : 'Sorry, I could not find an active catalog product matching that description.';
        } else {
            $content = $locale === 'fa'
                ? 'این گزینه‌ها را از کاتالوگ فعال فروشگاه پیدا کردم. وضعیت دسترسی هر محصول در کارت آن نمایش داده شده است.'
                : 'I found these options in the active store catalog. Availability is shown on each product card.';
        }

        $inputText = json_encode(
            [$history, $catalogContext],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        ) ?: '';

        return [
            'content' => $content,
            'input_tokens' => max(1, (int) ceil(mb_strlen($inputText) / 4)),
            'output_tokens' => max(1, (int) ceil(mb_strlen($content) / 4)),
        ];
    }
}
