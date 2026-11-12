<?php

declare(strict_types=1);

namespace App\Services\ShoppingAssistant;

/** Narrow advice intent when verified catalog routing has returned no tool. */
final class ShoppingAssistantAdvisoryIntent
{
    public static function needsAdvice(string $message): bool
    {
        if (trim($message) === '' || strlen($message) > 4800) {
            return false;
        }
        $comparison = preg_match('/(?:کدوم|کدام|مقایسه|بهتر|راحت(?:‌|\s*)تر|فرق|تفاوت|compare|versus|\bvs\b|better|easier)/iu', $message) === 1;
        $cookware = preg_match('/(?:تابه|ماهیتابه|قابلمه|استیل|روکش(?:‌|\s*)دار|نچسب|املت|stainless|nonstick|coated|\bpan\b|skillet)/iu', $message) === 1;
        $regret = preg_match('/(?:پشیم(?:و|ا)ن|پشیمونی|regret)/iu', $message) === 1;
        $purchaseConcern = preg_match('/(?:می(?:‌|\s*)ترسم|نگرانم|دودل|مردد|بخرم|خرید|buy|purchase|afraid|worried)/iu', $message) === 1;
        // An exact chemical-free claim is a manufacturer-evidence question,
        // not an empty product search. Keep the routing narrow and never
        // assert that a coating is free of a substance merely from its label.
        $coatingClaim = preg_match('/(?:\b(?:PFOA|PFAS|PTFE|BPA)(?:[\s-]*free)?\b)/iu', $message) === 1;
        $productContext = preg_match('/(?:روکش|تابه|قابلمه|ظرف|این|مدل|محصول|coating|\bpan\b|product|model)/iu', $message) === 1;

        // A bounded, product-domain materials question must be answered as
        // advice even when a catalog keyword lookup did not find a SKU.
        // This does not create products or override existing catalog routes.
        // Keep the product noun and technical predicate both mandatory.
        $materialProduct = preg_match(
            '/(?:cutting[\s-]*boards?|chopping[\s-]*boards?|cookware|utensils?|knife|knives|pan|skillet|pot|container|تخته(?:‌|\s*)برش|تخته(?:‌|\s*)گوشت|چاقو|قابلمه|تابه|ظرف|وسایل(?:‌|\s*)آشپزخانه)/iu',
            $message
        ) === 1;
        $materialQuestion = preg_match(
            '/(?:bamboo|wood(?:en)?|plastic|stainless|steel|ceramic|silicone|glass|hygien(?:ic|e)|sanitary|safe|food[\s-]*safe|dishwasher|porous|antibacterial|clean|wash|maintain|بامبو|چوب(?:ی)?|پلاستیک(?:ی)?|استیل|سرامیک|سیلیکون|شیشه|بهداشت(?:ی)?|تمیز|شست(?:‌|\s*)وشو|ماشین(?:‌|\s*)ظرف(?:‌|\s*)شویی|ایمن|ضد(?:‌|\s*)باکتری|نگهداری)/iu',
            $message
        ) === 1;
        $asksAdvice = preg_match(
            '/(?:\b(?:are|is|does|can|should|which|what|how|why|whether|more|less|better|safer)\b|آیا|چطور|چگونه|کدوم|کدام|بهتر|مناسب|می(?:‌|\s*)شه|می(?:‌|\s*)شود|فرق|تفاوت)/iu',
            $message
        ) === 1;

        // Product-specific warranty / guarantee uncertainty must not become a
        // generic catalog greeting. The existing catalog route always wins.
        // This detects a request for advice, not proof of warranty coverage.
        $warrantyClaim = preg_match(
            '/(?:\bwarrant(?:y|ies)\b|\bguarantee(?:d)?\b|گارانتی|ضمانت|خدمات(?:‌|\s*)پس(?:‌|\s*)از(?:‌|\s*)فروش)/iu',
            $message
        ) === 1;
        $warrantyReference = preg_match(
            '/(?:\bthis\b|\bthat\b|\bit\b|\bmodel\b|\bproduct\b|\bitem\b|\bknife\b|\bpan\b|\bpot\b|این|اون|همین|محصول|مدل|کالا|چاقو|تابه|قابلمه)/iu',
            $message
        ) === 1;
        $warrantyQuestion = preg_match(
            '/(?:\b(?:does|do|is|are|has|have|what|which|how|can|comes?)\b|\?|؟|آیا|داره|دارد|شامل|چند(?:‌|\s*)سال|مدت)/iu',
            $message
        ) === 1;

        // Bounded everyday kitchen consultation. This is reached only after the
        // trusted catalog router had no usable route; catalog evidence still wins.
        // A kitchen/product noun AND a purchase/use/problem predicate are required.
        $kitchenSubject = preg_match(
            '/(?:آشپزخانه|آشپزخونه|استیل|روکش|سرامیک|پخت|آشپزی|خورش|برنج|پیاز|سبزی|غذا|اسموتی|قهوه(?:‌|\s*)ساز|توستر|سرخ(?:‌|\s*)کن|آسیاب|همزن|فلاسک|قمقمه|ترازو|پارچ|کتری|مخلوط(?:‌|\s*)کن|غذاساز|پلوپز|چاقو|قابلمه|تابه|ظرف|سینی|آبکش|آبچکان|بشقاب|کاسه|ماگ|لیوان|سرویس|بانکه|مایکروویو|فر|اجاق|kitchen|cook(?:ing|ware)?|food|meal|smoothie|blender|coffee maker|grinder|toaster|air[ -]?fryer|mixer|flask|thermos|bottle|scale|kettle|knife|kettle|saucepan|cooktop|microwave|dishwasher|bowl|tray|mug|utensil|container|appliance)/iu',
            $message
        ) === 1;
        $kitchenNeed = preg_match(
            '/(?:می(?:‌|\s*)خوام|می(?:‌|\s*)خواهم|می(?:‌|\s*)خوره|مناسب|کافیه|به(?:‌|\s*)درد|چی(?:‌|\s*)بگیرم|چه(?:‌|\s*)شکلی|چه(?:‌|\s*)نوعی|انتخاب|پیشنهاد|دنبال|لازمه|نیاز|مشکل|سنگین|کوچیک|بزرگ|راحت|سخت|وقت(?:‌|\s*)زیاد|زود|خراب|همیشه|هیچ(?:‌|\s*)وقت|سالم|حتما|حتماً|می(?:‌|\s*)شه|میشه|جواب(?:‌|\s*)می(?:‌|\s*)ده|می(?:‌|\s*)ارزه|مهمه|دقیق|نریزه|چطور|چگونه|should|which|what|how|make sense|recommend|choose|looking for|need|want|suit|enough|suitable|easier|harder|always|safe|better|help|struggl|avoid|prefer)/iu',
            $message
        ) === 1;

        return ($comparison && $cookware)
            || ($regret && $purchaseConcern)
            || ($coatingClaim && $productContext)
            || ($materialProduct && $materialQuestion && $asksAdvice)
            || ($warrantyClaim && $warrantyReference && $warrantyQuestion)
            || ($kitchenSubject && $kitchenNeed);
    }
}
