<?php

namespace App\Services\ShoppingAssistant;

/**
 * Advisory retrieval of pre-reviewed behavioral rules; never product evidence.
 * Opt-in caller must gate this separately from the Skills feature flag.
 */
final class LunaScenarioGuidance
{
    private const CATEGORIES = [
        'comparison' => ['product_comparisons', 'professional_product_consultation'],
        'decision' => ['product_comparisons', 'professional_product_consultation'],
        'exact_spec' => ['uncertainty_and_unavailable_information', 'materials_quality_product_knowledge'],
        'budget' => ['budget_sensitive_recommendations'],
        'complaint' => ['complaints_and_difficult_customers'],
        'purchase' => ['professional_product_consultation'],
        'discovery' => ['understanding_customer_needs', 'personalization_and_preferences'],
    ];

    /** @param array<int,array{role?:string,content?:string}> $history
     *  @param array<string,mixed> $catalogContext
     *  @return array<int,array{id:string,rule:string,score:int}>
     */
    public function find(array $history, array $catalogContext = []): array
    {
        $message = '';
        for ($i = count($history) - 1; $i >= 0; --$i) {
            if (($history[$i]['role'] ?? '') === 'user' && is_string($history[$i]['content'] ?? null)) {
                $message = $history[$i]['content'];
                break;
            }
        }
        $skill = (new LunaSkillSelector())->select($message, $catalogContext);
        if ($skill === null || ! isset(self::CATEGORIES[$skill])) {
            return [];
        }
        $terms = $this->terms($message);
        if ($terms === []) {
            return [];
        }
        $file = base_path('resources/luna/behavior-scenario-index-v1.json');
        if (! is_file($file)) {
            return [];
        }
        $raw = file_get_contents($file);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($data) || ($data['version'] ?? null) !== 1 || ! is_array($data['cases'] ?? null)) {
            return [];
        }
        $ranked = [];
        foreach ($data['cases'] as $case) {
            if (! is_array($case) || ! in_array($case['category'] ?? null, self::CATEGORIES[$skill], true)) {
                continue;
            }
            $id = $case['id'] ?? null;
            $rule = $case['rule'] ?? null;
            $examples = $case['examples'] ?? null;
            if (! is_string($id) || preg_match('/^[A-Z][A-Z0-9-]{2,32}$/D', $id) !== 1
                || ! is_string($rule) || strlen($rule) > 240 || ! is_array($examples)) {
                continue;
            }
            $best = 0;
            foreach ($examples as $example) {
                if (! is_string($example)) {
                    continue;
                }
                $sampleTerms = $this->terms($example);
                if ($sampleTerms === []) {
                    continue;
                }
                $hits = count(array_intersect_key($terms, $sampleTerms));
                $score = (int) round(100 * $hits / max(1, count($terms) + count($sampleTerms) - $hits));
                $best = max($best, $score);
            }
            // No lexical connection means no specific scenario guidance, not a guessed match.
            if ($best >= 25) {
                $ranked[] = ['id' => $id, 'rule' => $rule, 'score' => $best];
            }
        }
        usort($ranked, static fn (array $a, array $b): int =>
            ($b['score'] <=> $a['score']) ?: strcmp($a['id'], $b['id']));
        return array_slice($ranked, 0, 2);
    }

    /** @param array<int,array{role?:string,content?:string}> $history
     *  @param array<string,mixed> $catalogContext
     */
    public function prompt(array $history, array $catalogContext = []): string
    {
        $matches = $this->find($history, $catalogContext);
        if ($matches === []) {
            return '';
        }
        $lines = ['Relevant behavioral scenario rules (advisory only; not product facts or ready-made answers):'];
        foreach ($matches as $match) {
            $lines[] = $match['id'] . ': ' . $match['rule'];
        }
        $lines[] = 'Follow higher-priority safety, verified catalog, manufacturer research and output rules. Never cite scenario examples as product evidence.';
        return implode("\n", $lines);
    }

    /** @return array<string,true> */
    private function terms(string $text): array
    {
        $text = strtr(strtolower($text), ['ي' => 'ی', 'ك' => 'ک', '‌' => ' ', 'ـ' => '']);
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [];
        $stop = array_fill_keys(['برای', 'این', 'اون', 'است', 'هست', 'من', 'ما', 'که', 'به', 'از', 'را', 'رو',
            'چه', 'چیه', 'می', 'با', 'یا', 'the', 'and', 'for', 'you', 'this', 'that', 'with', 'are',
            'can', 'how', 'what', 'which', 'want', 'have', 'need', 'یک', 'یه', 'در', 'it', 'is', 'to', 'i'], true);
        $terms = [];
        foreach ($parts as $part) {
            if (preg_match('/^.{3}/us', $part) === 1 && ! isset($stop[$part])) {
                $terms[$part] = true;
            }
        }
        return $terms;
    }
}
