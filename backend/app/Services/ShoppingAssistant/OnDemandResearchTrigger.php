<?php

namespace App\Services\ShoppingAssistant;

use App\Models\Product;

final class OnDemandResearchTrigger
{
    public function __construct(
        private readonly OnDemandProductResearchRegistry $registry
    ) {
    }

    /**
     * Register at most one product; never execute web search here.
     *
     * @param array<string, mixed> $context
     */
    public function registerIfNeeded(
        string $message,
        string $tool,
        array $context
    ): bool {
        if (
            ! config('shopping_assistant_research.enabled', false) ||
            config('shopping_assistant.driver') !== 'gapgpt' ||
            ! in_array($tool, ['get_product_details', 'search_products'], true)
        ) {
            return false;
        }

        $products = $context['products'] ?? [];

        if (! is_array($products) || count($products) !== 1) {
            return false;
        }

        $card = reset($products);
        $id = is_array($card) ? ($card['id'] ?? null) : null;

        if (! is_numeric($id) || (int) $id <= 0) {
            return false;
        }

        if (! $this->asksForProductInformation($message)) {
            return false;
        }

        // A search result alone must never trigger a product research.
        if ($tool === 'search_products') {
            $productName = is_array($card) ? (string) ($card['name'] ?? '') : '';
            $normalizedMessage = \App\Support\SearchTextNormalizer::normalize($message);
            $normalizedName = \App\Support\SearchTextNormalizer::normalize($productName);
            if ($normalizedName === '' || ! str_contains(' '.$normalizedMessage.' ', ' '.$normalizedName.' ')) {
                return false;
            }
        }

        foreach ($context['verified_product_knowledge'] ?? [] as $entry) {
            if (
                is_array($entry) &&
                (int) ($entry['product_id'] ?? 0) === (int) $id &&
                ! empty($entry['facts'])
            ) {
                return false;
            }
        }

        $product = Product::query()->find((int) $id);

        return $product instanceof Product
            ? $this->registry->register($product)
            : false;
    }

    private function asksForProductInformation(string $message): bool
    {
        return preg_match(
            '/\b(?:specifications?|features?|materials?|capacity|dimensions?|size|weight|power|wattage|induction|dishwasher|compatible|compatibility|suitable|suitability|performance|maintenance|durability|how to use|why recommend|why did you recommend)\b|مشخصات|ویژگی|جنس|ظرفیت|ابعاد|اندازه|وزن|توان|وات|القایی|سازگار|مناسب|کاربرد|نگهداری|شستشو|چرا پیشنهاد|چرا توصیه/iu',
            $message
        ) === 1;
    }
}