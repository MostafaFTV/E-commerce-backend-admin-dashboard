<?php

declare(strict_types=1);

namespace App\Services\ShoppingAssistant;

final class ShoppingAssistantConsultationPlanner
{
    /** @param array<string,mixed> $state
     *  @return array<string,mixed>
     */
    public static function context(array $state, string $latestMessage = ''): array
    {
        $decisionRequested = preg_match(
            '/چی(?: رو| را)? پیشنهاد|کدوم(?:و| رو| را)? پیشنهاد|کدوم بهتر|کدام بهتر|هردوش|هر دو(?:ش)?|(?:which(?:\s+(?:one|of\s+these|one\s+of\s+these))?|what) (?:would you )?(?:recommend|choose)|both (?:matter|important)/iu',
            $latestMessage
        ) === 1;

        return [
            'stage' => $decisionRequested ? 'decide' : ($state['stage'] ?? 'discover'),
            'decision_requested' => $decisionRequested,
            'explicit_customer_preferences' => $state['preferences'] ?? [],
            'last_assistant_question' => $state['last_question'] ?? null,
            'rule' => $decisionRequested
                ? 'Customer requests a buying decision. Do not loop into another generic question. Use only trusted product evidence. Give a defensible choice and an alternative if evidence exists; otherwise provide an explicit conditional shortlist, the key missing verification, and a concrete next action. Never invent a product advantage.'
                : 'Context from customer statements, not verified product evidence. Ask at most one material clarification. Never invent products or facts.',
        ];
    }

    /** @param array<string,mixed> $state
     *  @param array<string,mixed> $result
     */
    public static function stage(array $state, array $result, string $reply): string
    {
        $tool = (string) ($result['tool'] ?? '');
        if ($tool === 'compare_products') {
            return 'compare';
        }
        if (($result['product_cards'] ?? []) !== []) {
            return 'recommend';
        }
        if (preg_match('/[؟?]/u', $reply) === 1) {
            return 'clarify';
        }
        return in_array($state['stage'] ?? null, ['discover', 'clarify', 'recommend', 'compare', 'decide'], true)
            ? $state['stage'] : 'discover';
    }
}
