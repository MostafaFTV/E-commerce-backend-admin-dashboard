<?php

declare(strict_types=1);

namespace App\Services\ShoppingAssistant;

/** Detects explicit demands to promise or guarantee a product's lifespan. */
final class ShoppingAssistantWarrantyAssuranceIntent
{
    public static function needsResponse(string $message): bool
    {
        $message = trim($message);
        if ($message === '') {
            return false;
        }

        // Require both an assurance demand and a durability/warranty referent;
        // historical mentions ("I bought it ten years ago") are not demands.
        $request = preg_match('/(?:قول\s*(?:بدید|بدهید|بده|میدید|می\x{200C}دید)|تضمین\s*(?:کنید|میکنید|می\x{200C}کنید|میخوام|می\x{200C}خوام)|ضمانت\s*(?:می(?:\x{200C})?کنید|کنید)|گارانتی(?:.{0,35})?(?:داره|دارد|میدید|می\x{200C}دید)|(?:can\s+you|will\s+you|do\s+you)\s+(?:promise|guarantee)|(?:promise|guarantee)\s+(?:me|it|that)|(?:is\s+it|does\s+it)\s+(?:guaranteed|have\s+(?:(?:a|the)\s+)?(?:lifetime\s+)?warranty))/iu', $message) === 1;
        $referent = preg_match('/(?:سال|عمر|کار\s*(?:می\x{200C})?کنه|کار\s*(?:می\x{200C})?کند|خراب|دوام|گارانتی|ضمانت|تضمین|lifetime|years?|last|durab|warranty|guarantee)/iu', $message) === 1;
        return $request && $referent;
    }
}
