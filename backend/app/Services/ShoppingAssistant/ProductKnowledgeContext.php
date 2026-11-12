<?php

namespace App\Services\ShoppingAssistant;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ShoppingAssistantProductKnowledge;
use App\Support\SearchTextNormalizer;

class ProductKnowledgeContext
{
    private const APPROVED_SOURCE_TYPES = [
        'manufacturer_documentation',
        'trusted_documentation',
    ];

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function enrich(array $context): array
    {
        $productCards = $context['products'] ?? [];
        if (! is_array($productCards)) {
            return $context;
        }

        $productIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $card): int => is_array($card) ? (int) ($card['id'] ?? 0) : 0,
            $productCards
        ), static fn (int $id): bool => $id > 0)));
        if ($productIds === []) {
            return $context;
        }

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->where('status', ProductStatus::ACTIVE->value)
            ->whereHas('category', static fn ($query) => $query->where('is_active', true))
            ->get()
            ->keyBy('id');

        $knowledge = ShoppingAssistantProductKnowledge::query()
            ->whereIn('product_id', $products->keys())
            ->where('review_status', 'approved')
            ->whereNotNull('verified_at')
            ->get()
            ->keyBy('product_id');

        $verified = [];
        foreach ($products as $product) {
            $entry = $knowledge->get($product->id);
            if (
                ! $entry instanceof ShoppingAssistantProductKnowledge
                || ! $entry->product_updated_at
                || ! $product->updated_at
                || ! $entry->product_updated_at->equalTo($product->updated_at)
                || ! $this->matchesExactProduct($entry, $product)
            ) {
                continue;
            }

            $facts = $this->approvedFacts($entry);
            if ($facts === []) {
                continue;
            }

            $verified[] = [
                'product_id' => (int) $product->id,
                'manufacturer' => $entry->manufacturer,
                'model' => $entry->model,
                'verified_at' => $entry->verified_at->toIso8601String(),
                'facts' => $facts,
            ];
        }

        if ($verified !== []) {
            $context['verified_product_knowledge'] = $verified;
        }

        return $context;
    }

    private function matchesExactProduct(
        ShoppingAssistantProductKnowledge $entry,
        Product $product
    ): bool {
        $manufacturer = SearchTextNormalizer::normalize($entry->manufacturer);
        $brand = SearchTextNormalizer::normalize((string) $product->getRawOriginal('brand'));
        $model = SearchTextNormalizer::normalize($entry->model);
        if ($manufacturer === '' || $brand !== $manufacturer || $model === '') {
            return false;
        }

        $identifiers = [
            ...$this->localizedValues($product->getRawOriginal('name')),
            (string) $product->slug,
        ];

        foreach ($identifiers as $identifier) {
            $normalized = SearchTextNormalizer::normalize($identifier);
            if ($normalized !== '' && str_contains(' '.$normalized.' ', ' '.$model.' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{name: string, value: string, source_url: string}>
     */
    private function approvedFacts(ShoppingAssistantProductKnowledge $entry): array
    {
        $sources = [];
        foreach ($entry->sources ?? [] as $source) {
            if (
                ! is_array($source)
                || ($source['review_status'] ?? null) !== 'approved'
                || ! in_array($source['source_type'] ?? null, self::APPROVED_SOURCE_TYPES, true)
                || ! $this->isHttpsUrl($source['url'] ?? null)
            ) {
                continue;
            }

            $sources[$source['url']] = true;
        }

        $facts = [];
        foreach ($entry->facts ?? [] as $fact) {
            if (
                ! is_array($fact)
                || ($fact['review_status'] ?? null) !== 'approved'
                || ! is_string($fact['name'] ?? null)
                || trim($fact['name']) === ''
                || ! is_string($fact['value'] ?? null)
                || trim($fact['value']) === ''
                || ! is_string($fact['source_url'] ?? null)
                || ! isset($sources[$fact['source_url']])
                || $this->isRestrictedFact($fact['name'], $fact['value'])
            ) {
                continue;
            }

            $facts[] = [
                'name' => trim($fact['name']),
                'value' => trim($fact['value']),
                'source_url' => $fact['source_url'],
            ];
        }

        return $facts;
    }

    private function isRestrictedFact(string $name, string $value): bool
    {
        $text = $name.' '.$value;
        $restrictedTerms = '/\b(?:price|cost|pricing|discount|sale|offer|quote|currency|stock|in stock|availability|available|inventory|shipping|delivery|return policy|warranty|payment|store policy)\b|قیمت|هزینه|تخفیف|فروش ویژه|حراج|ارز|موجودی|موجود|ارسال|تحویل|مرجوعی|گارانتی|ضمانت|پرداخت|سیاست فروشگاه/iu';
        $amount = '[0-9۰-۹٠-٩]+(?:[,.٫٬][0-9۰-۹٠-٩]+)*';
        $currency = '(?:toman|tomans|تومان|rial|rials|ریال|usd|eur|gbp|irr|aed|cad|aud|chf|sar|jpy|cny|kwd|try|rub|درهم|[$€£¥])';

        return preg_match($restrictedTerms, $text) === 1
            || preg_match('/'.$amount.'\s*'.$currency.'|'.$currency.'\s*'.$amount.'/iu', $value) === 1
            || preg_match('/'.$amount.'\s*(?:%|percent|درصد)/iu', $value) === 1;
    }

    private function isHttpsUrl(mixed $url): bool
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && isset($parts['host'])
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }

    /**
     * @return array<int, string>
     */
    private function localizedValues(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_values(array_filter(
                $decoded,
                static fn (mixed $text): bool => is_string($text) && trim($text) !== ''
            ));
        }

        return trim($value) === '' ? [] : [$value];
    }
}
