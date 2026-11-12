<?php

declare(strict_types=1);

namespace App\Services\ShoppingAssistant;

/**
 * Server-owned, bounded session metadata. This is user-provided context,
 * never trusted catalog evidence or executable instruction.
 */
final class ShoppingAssistantConsultationMemory
{
    /** @param array<string, mixed> $prior
     *  @return array<string, mixed>
     */
    public static function observe(array $prior, string $message, string $locale): array
    {
        $preferences = is_array($prior['preferences'] ?? null) ? $prior['preferences'] : [];
        $normalized = mb_strtolower(trim($message));

        // Keep facts explicit and intentionally narrow; do not infer a price,
        // product specification, inventory state, or unsupported customer fact.
        if (preg_match('/(?:آشپزخانه|اشپزخانه).{0,20}(?:کوچ|کوچک|کم.?جا)|small kitchen|tiny kitchen/iu', $normalized)) {
            $preferences['kitchen_space'] = 'small';
        }
        if (preg_match('/جمع.?وجور|کم.?جا|compact|space.saving/iu', $normalized)) {
            $preferences['product_size'] = 'compact';
        }
        if (preg_match('/نوشیدنی|beverage|drinks?/iu', $normalized)) {
            $preferences['usage'] = 'drinks';
        }
        // Prefer explicitly affirmed drink choices over a rejected mention in
        // the same message (e.g. "نه، قهوه نمی‌خوام، چای می‌خوام").
        $coffeeMentioned = preg_match('/قهوه|coffee/iu', $normalized) === 1;
        $teaMentioned = preg_match('/چای|دمنوش|tea|herbal infusion/iu', $normalized) === 1;
        $coffeeRejected = preg_match('/(?:نه\s+|no\s+|not\s+|don[’\x27]t\s+want\s+)(?:قهوه|coffee)|(?:قهوه|coffee).{0,18}(?:نمی[‌\s-]*خوام|نمی[‌\s-]*خواهم|نمی[‌\s-]*خورم|نمی[‌\s-]*خوره|don[’\x27]t\s+want|not\s+for\s+me)/iu', $normalized) === 1;
        $teaRejected = preg_match('/(?:نه\s+|no\s+|not\s+|don[’\x27]t\s+want\s+)(?:چای|دمنوش|tea|herbal infusion)|(?:چای|دمنوش|tea|herbal infusion).{0,18}(?:نمی[‌\s-]*خوام|نمی[‌\s-]*خواهم|نمی[‌\s-]*خورم|don[’\x27]t\s+want|not\s+for\s+me)/iu', $normalized) === 1;
        if ($coffeeMentioned && ! $coffeeRejected) {
            $preferences['drink_type'] = 'coffee';
            $preferences['usage'] = 'drinks';
        } elseif ($teaMentioned && ! $teaRejected) {
            $preferences['drink_type'] = 'tea';
            $preferences['usage'] = 'drinks';
        } elseif (($coffeeRejected || $teaRejected) && isset($preferences['drink_type'])) {
            if (($coffeeRejected && $preferences['drink_type'] === 'coffee') || ($teaRejected && $preferences['drink_type'] === 'tea')) {
                unset($preferences['drink_type']);
            }
        }
        // Negated preferences should not be stored as affirmative requirements.
        // An explicit correction wins over the previous turn's inferred preference.
        if (preg_match('/(?:نه[،,\s]*|نمی[‌\s-]*خوام\s*|not\s+|no\s+|don[’\x27]t\s+(?:want|need)\s+)(?:یه\s+|یک\s+)?(?:چیز\s+)?(?:جمع.?وجور|کم.?جا|compact|space.saving)|(?:جمع.?وجور|کم.?جا|compact|space.saving).{0,22}(?:نمی[‌\s-]*خوام|نمی[‌\s-]*خواهم|نمی[‌\s-]*خواد|isn[’\x27]t\s+needed)/iu', $normalized)) {
            unset($preferences['product_size']);
        }
        if (preg_match('/(?:نه[،,\s]*|نمی[‌\s-]*خوام\s*|not\s+|no\s+)(?:یه\s+|یک\s+)?(?:چیز\s+)?(?:بزرگ|large|bigger)/iu', $normalized)) {
            unset($preferences['product_size']);
        }

        // Explicit cookware needs remain available even after old chat turns
        // drop out of the model's bounded history. These are user statements,
        // never verified technical specifications of any catalog product.
        if (preg_match('/(?:خانواده\s*|برای\s*|family of\s+)([0-9۰-۹٠-٩]{1,2})\s*(?:نفره|نفر|people|persons)?/iu', $normalized, $sizeMatch)) {
            $digits = str_replace(
                preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY),
                str_split('01234567890123456789'),
                $sizeMatch[1]
            );
            $size = (int) $digits;
            if ($size >= 1 && $size <= 20) {
                $preferences['household_size'] = (string) $size;
            }
        }
        $inductionRejected = preg_match('/(?:نه[،,\s]*|غیر[‌\s-]*|not\s+|non[ -]*|don[’\x27]t\s+(?:want|need)\s+)(?:اجاق\s*)?(?:القایی|induction)|(?:القایی|induction).{0,18}(?:نمی[‌\s-]*خوام|نمی[‌\s-]*خواهم|نمی[‌\s-]*خواد|نیست|ندارم|don[’\x27]t\s+want|not\s+needed)/iu', $normalized) === 1;
        if (preg_match('/(?:برقی|electric)/iu', $normalized)) {
            $preferences['hob_type'] = 'electric_unspecified';
        }
        if (preg_match('/(?:صفحه[‌\s-]*ای|سرامیک|ceramic|radiant)/iu', $normalized)) {
            $preferences['hob_type'] = 'electric_flat';
        } elseif (preg_match('/(?:القایی|induction)/iu', $normalized)
            && ! $inductionRejected
        ) {
            $preferences['hob_type'] = 'induction';
        } elseif (preg_match('/(?:اجاق\s*گاز|گازی|gas\s*(?:hob|stove|cooktop))/iu', $normalized)) {
            $preferences['hob_type'] = 'gas';
        } elseif ($inductionRejected && ($preferences['hob_type'] ?? null) === 'induction') {
            // An explicit retraction must clear a prior induction requirement;
            // never infer an alternative hob type without customer evidence.
            unset($preferences['hob_type']);
        }
        if (preg_match('/(?:غذاهای?\s*ایرانی|آشپزی\s*ایرانی|iranian\s*(?:food|cooking))/iu', $normalized)) {
            $preferences['cooking_style'] = 'iranian';
        }
        $light = preg_match('/(?:سبک(?:تر)?|کم[‌\s-]*وزن|lightweight|lighter)|(?:سبک(?:تر)?|کم[‌\s-]*وزن|lightweight).{0,12}(?:نمی[‌\s-]*خوام|مهم\s+نیست|not\s+important)/iu', $normalized) === 1;
        $durable = preg_match('/(?:دوام|بادوام|ماندگار|durab|long[ -]*lasting)/iu', $normalized) === 1;
        if (preg_match('/(?:هردوش|هر\s*دو|both)/iu', $normalized) === 1) {
            $question = (string) ($prior['last_question'] ?? '');
            if (preg_match('/(?:سبک|light)/iu', $question) === 1
                && preg_match('/(?:دوام|durab)/iu', $question) === 1
            ) {
                $light = true;
                $durable = true;
            }
        }
        $priorities = array_filter(explode(',', (string) ($preferences['cookware_priorities'] ?? '')));
        // If the visitor retracts a prior priority, remove it instead of
        // retaining it indefinitely. Check negations before adding priorities.
        $rejectLight = preg_match('/(?:نه[،,\s]*|دیگه\s+|نمی[‌\s-]*خوام\s*|not\s+)(?:خیلی\s+)?(?:سبک(?:تر)?|کم[‌\s-]*وزن|lightweight|lighter)|(?:سبک(?:تر)?|کم[‌\s-]*وزن|lightweight).{0,12}(?:نمی[‌\s-]*خوام|مهم\s+نیست|not\s+important)/iu', $normalized) === 1;
        $rejectDurable = preg_match('/(?:نه[،,\s]*|دیگه\s+|نمی[‌\s-]*خوام\s*|not\s+)(?:خیلی\s+)?(?:بادوام|دوام|ماندگار|durable|long[ -]*lasting)|(?:دوام|ماندگاری|durability).{0,12}(?:مهم\s+نیست|نمی[‌\s-]*خوام|not\s+important)/iu', $normalized) === 1;
        if ($rejectLight) {
            $priorities = array_values(array_diff($priorities, ['lightweight']));
            $light = false;
        }
        if ($rejectDurable) {
            $priorities = array_values(array_diff($priorities, ['durable']));
            $durable = false;
        }
        if ($light) { $priorities[] = 'lightweight'; }
        if ($durable) { $priorities[] = 'durable'; }
        $priorities = array_values(array_unique($priorities));
        if ($priorities !== []) {
            $preferences['cookware_priorities'] = implode(',', $priorities);
        } else {
            unset($preferences['cookware_priorities']);
        }

        $preferences = array_intersect_key($preferences, array_flip([
            'kitchen_space', 'product_size', 'usage', 'drink_type',
            'household_size', 'hob_type', 'cooking_style', 'cookware_priorities',
        ]));
        $preferences = array_filter($preferences, static fn (mixed $value): bool => is_string($value) && mb_strlen($value) <= 30);
        $lastQuestion = $prior['last_question'] ?? null;
        if (! is_string($lastQuestion) || mb_strlen($lastQuestion) > 180) {
            $lastQuestion = null;
        }

        return [
            'version' => 2,
            'locale' => $locale === 'en' ? 'en' : 'fa',
            'preferences' => $preferences,
            'stage' => in_array($prior['stage'] ?? null, ['discover', 'clarify', 'recommend', 'compare', 'decide'], true)
                ? $prior['stage'] : 'discover',
            'last_question' => $lastQuestion,
        ];
    }

    /** @param array<string,mixed> $observed
     *  @param array<string,mixed> $result
     *  @return array<string,mixed>
     */
    public static function commit(array $observed, string $assistantReply, array $result): array
    {
        // The server commits only after a successful assistant response and
        // billing settlement; errors must not advance session memory/TTL.
        $observed['stage'] = ShoppingAssistantConsultationPlanner::stage($observed, $result, $assistantReply);
        $lastQuestion = null;
        if (preg_match('/([^\n.!؟?]{1,160}[؟?])/u', $assistantReply, $matches)) {
            $lastQuestion = trim($matches[1]);
        }
        $observed['last_question'] = $lastQuestion;
        return $observed;
    }
}
