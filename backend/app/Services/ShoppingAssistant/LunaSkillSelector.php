<?php

namespace App\Services\ShoppingAssistant;

/**
 * Optional, advisory-only skill selection. No product facts or IDs are created here.
 * This never calls providers or changes catalog/research/price/budget decisions.
 */
final class LunaSkillSelector
{
    /** @param array<int, array{role?: string, content?: string}> $history
     *  @param array<string, mixed> $catalogContext
     */
    public function prompt(array $history, array $catalogContext): string
    {
        $message = '';
        for ($i = count($history) - 1; $i >= 0; --$i) {
            if (($history[$i]['role'] ?? '') === 'user' && is_string($history[$i]['content'] ?? null)) {
                $message = $history[$i]['content'];
                break;
            }
        }
        $skill = $this->select($message, $catalogContext);
        if ($skill === null) {
            return '';
        }

        $policy = [
            'complaint' => 'Acknowledge the concrete complaint, do not get defensive, and give only an authorized remedy or verified handoff; never promise a refund or action.',
            'purchase' => 'The customer wants to order or proceed. Answer the purchase question directly, using ONLY server-provided verified contact/order guidance. Do not invent checkout, availability or completed orders.',
            'comparison' => 'Compare only the differences supported by exact-item catalog or verified manufacturer evidence. State the most relevant trade-off; if no verified winner exists, do not invent one.',
            'decision' => 'Help decide now, using the customer’s stated priorities and verified evidence. Do not restart discovery. If evidence cannot distinguish choices, give a conditional choice with its decisive unknown.',
            'exact_spec' => 'Answer an exact technical question only from exact-model verified facts. If missing, say so briefly, distinguish general selection guidance, and do not pretend external research has been completed.',
            'budget' => 'Respect the stated affordability concern without inventing numeric prices, discounts or value claims. Give practical non-price criteria and use only the approved pricing/contact route.',
            'discovery' => 'Use known needs first. When verified catalog matches exist and the customer asks for a suggestion, recommend the strongest defensible named match with a concise why, rather than only listing criteria. If catalog matches are absent, offer useful category guidance and at most one decisive clarification. Do not ask for facts already given or raise prices without a price question.',
        ];

        return "Selected consultation skill (behavior only, never evidence): {$skill}. " . $policy[$skill] .
            ' Higher-priority server rules, catalog facts, research verification, budget and output contract always prevail.';
    }

    /** @param array<string, mixed> $catalogContext */
    public function select(string $message, array $catalogContext = []): ?string
    {
        $message = strtolower(trim($message));
        if ($message === '') {
            return null;
        }
        $tool = $catalogContext['tool'] ?? null;
        // Known server-side tool intent takes priority over superficial trigger words.
        if ($tool === 'purchase_guidance') {
            return 'purchase';
        }
        if ($tool === 'get_pricing') {
            return 'budget';
        }
        $patterns = [
            // Customer-service dissatisfaction outranks incidental words about buying or budget.
            'complaint' => '/(?:شکایت|ناراضی|ناراحت(?:م|یم)?|شکسته|خراب|معیوب|آسیب|مرجوع|پشیمونم|عوضش کنید|همه جنس.{0,20}بی[‌\- ]?کیفیت|قبلاً جواب دادم|دوباره می[‌ ]?پرسید|چرا (?:دوباره|جواب می[‌ ]?دی|وزن محصول رو نمی)|فروشنده .{0,55} سایت|اعتماد کنم|قول بدید|complain|damaged|defective|broken|refund|return it|no delivery update|no update for|this is ridiculous|you recommended .{0,80} over my budget)/u',
            'purchase' => '/(?:خرید(?:م|ش)?|سفارش (?:بدم|بدهم|ثبت|می|دادن)|ثبت سفارش|چطور (?:بخرم|سفارش)|نحوه سفارش|order (?:it|now|this)|how (?:do i|to) (?:buy|order)|ready to buy|checkout)/u',
            'decision' => '/(?:کدوم رو (?:بگیرم|انتخاب کنم)|کدام را (?:بخرم|انتخاب کنم)|انتخاب نهایی|پیشنهاد نهایی|تو کدوم|تصمیم|which (?:one|should i)|what should i choose|recommend one)/u',
            'comparison' => '/(?:مقایسه|تفاوت|فرق|در برابر|یا (?:اون|این|دومی)|کدوم بهتر|کدام بهتر|compare|difference|versus|\bvs\.?\b|which is better|بین .{1,65}(?:کدوم|موندم|بهتره)?|کدوم(?:ش|و)?|کدام|بهتره یا|کیفیتش بهتره|می[‌ ]?ارزه یا|راحت[‌ ]?تره.{0,30}یا|سریع[‌ ]?تره|تیزتره|عمر بیشتری|which (?:bowl|bottle|serving)|which .* (?:lighter|better)|just tell me which)/u',
            'exact_spec' => '/(?:وزن دقیق|وزنش چقد|ابعاد دقیق|جنس دقیق|توان دقیق|گنجایش دقیق|مدل دقیق|مشخصات فنی|سازگار(?:ی)? با|induction|compatible|exact (?:weight|dimensions|material|capacity|spec)|how (?:much does|heavy is) it weigh)/u',
            'budget' => '/(?:بودجه|قیمت|گران|ارزون|ارزان|مقرون|تخفیف|چند تومن|چند تومان|budget|price|cost|cheaper|expensive|afford|\$\s*\d+|\bspend up to\b|\bpremium\b|\bworth it\b|هزینه[‌ ]?م|به[‌ ]?صرفه)/u',
            'discovery' => '/(?:دنبال|می(?:خوام|‌خوام)|می(?:خواهم|‌خواهم)|نیاز|چه (?:مدلی|چیزی)|برای (?:خونه|خانه|خانواده|آشپزی)|پیشنهاد(?: بده| بدید| بدهید| می[‌ ]?دی| می[‌ ]?دهید| کنید)?|looking for|need a|suggest|recommend|which type|لازم دارم|به درد می[‌ ]?خوره|\bbuying for\b)/u',
        ];
        foreach ($patterns as $skill => $regex) {
            if (preg_match($regex, $message) === 1) {
                return $skill;
            }
        }
        return null;
    }
}
