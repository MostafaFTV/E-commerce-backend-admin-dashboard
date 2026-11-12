<?php

namespace App\Services\ShoppingAssistant;

use App\Contracts\ShoppingAssistantProvider;
use App\Exceptions\ShoppingAssistantDailyQuotaExceeded;
use App\Exceptions\ShoppingAssistantUnavailable;
use App\Models\Product;
use App\Support\SearchTextNormalizer;

class ShoppingAssistantService
{
    public function __construct(
        private readonly ShoppingCatalogTools $tools,
        private readonly ShoppingAssistantProvider $provider,
        private readonly ShoppingAssistantBudgetGuard $budgetGuard,
        private readonly ShoppingAssistantDailyQuota $dailyQuota,
        private readonly ProductKnowledgeContext $productKnowledge,
        private readonly OnDemandResearchTrigger $researchTrigger
    ) {
    }

    /**
     * @param array<int, array{role: string, content: string}> $history
     *
     * @return array{
     *     content: string,
     *     product_cards: array<int, array<string, mixed>>,
     *     catalog_data: array<string, mixed>,
     *     tool: string|null,
     *     input_tokens: int,
     *     output_tokens: int,
     *     contact_links?: array<int, array<string, mixed>>,
     *     contact_locations?: array<int, array<string, mixed>>,
     *     remembered_product_ids?: array<int, int>
     * }
     */
    public function respond(
        string $message,
        string $locale,
        array $history,
        string $conversationId,
        array $previousProductIds = [],
        string $quotaSubject = '',
        array $consultationState = []
    ): array
    {
                // TKB Luna conversation router v1.
        // Navigation only uses known routes; selections only use saved product IDs.

        // Explicit requests to speak with staff are a verified contact handoff,
        // not a prompt to invent a human representative or contact channel.
        // Other shopping and navigation intents keep their existing precedence.
        if (ShoppingAssistantHumanHandoff::requested($message)) {
            return [
                'content' => ShoppingAssistantHumanHandoff::message($locale),
                'product_cards' => [],
                'tool' => 'get_store_information',
                'input_tokens' => 0,
                'output_tokens' => 0,
                'catalog_data' => [],
                'contact_links' => $this->tools->contactLinks($locale),
                'contact_locations' => [],
                'remembered_product_ids' => $previousProductIds,
                'billing_status' => 'nonbillable',
            ];
        }

        // Stage 4 quality gate: an explicit request for WHERE TO PLACE an order
        // is a navigation/contact intent, not general product discovery.
        // The ShoppingCatalogTools contact links are server-generated and
        // validated; never fabricate a checkout URL or claim order placement.
        if (ShoppingAssistantOrderEntryIntent::isDirectOrderEntry($message)) {
            $links = $this->tools->contactLinks($locale);

            return [
                'content' => $links !== []
                    ? ($locale === 'fa'
                        ? 'برای راهنمایی ثبت سفارش، از راه‌های ارتباطی تأییدشده فروشگاه که در ادامه نمایش داده می‌شود استفاده کنید. مسیر پرداخت یا ثبت نهایی باید از همان کانال رسمی تأیید شود.'
                        : 'For help placing an order, use the store’s verified contact options shown below. Confirm the exact checkout or payment process through an official channel.')
                    : ($locale === 'fa'
                        ? 'در این گفتگو مسیر رسمی ثبت سفارش تأیید نشده است. لطفاً از صفحه تماس رسمی فروشگاه پیگیری کنید؛ نمی‌خواهم لینک یا مراحل پرداخت نادرست بدهم.'
                        : 'The official order path is not verified in this chat. Please use the store’s official contact page; I will not invent checkout or payment steps.'),
                'product_cards' => [],
                'tool' => 'get_store_information',
                'input_tokens' => 0,
                'output_tokens' => 0,
                'catalog_data' => [],
                'contact_links' => $links,
                'contact_locations' => [],
                'remembered_product_ids' => $previousProductIds,
                'billing_status' => 'nonbillable',
            ];
        }

        $page = ShoppingAssistantConversationRouter::page($message);

        if ($page !== null) {
            $base = rtrim(
                (string) config('shopping_assistant.customer_url', ''),
                '/'
            );

            if (
                $base !== ''
                && ! preg_match('~^https?://[^/]+$~i', $base)
            ) {
                $base = '';
            }

            $url = $base . $page['path'];

            return [
                'content' => $locale === 'fa'
                    ? 'لینک صفحه «' . $page['fa'] . '»: ' . $url
                    : 'Here is the ' . $page['en'] . ' page: ' . $url,
                'product_cards' => [],
                'tool' => null,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'catalog_data' => [],
                'contact_links' => [],
                'contact_locations' => [],
                'remembered_product_ids' => $previousProductIds,
                'billing_status' => 'nonbillable',
            ];
        }

        // A narrowly normalized catalog query preserves the original user text
        // for the model while allowing obvious Finglish categories to be found.
        $catalogQuery = ShoppingAssistantLanguage::catalogSearchMessage($message);
        $resolved = $this->tools->answerContext(
            $catalogQuery,
            $locale,
            $previousProductIds
        );

        $option = ShoppingAssistantConversationRouter::optionIndex($message);
        $comparing = ShoppingAssistantConversationRouter::comparing($message);
        $selectedProductId = null;

        // Resolve "the third one" against real saved product IDs.
        if (
            $previousProductIds !== []
            && ! $comparing
            && $option !== null
        ) {
            if (! isset($previousProductIds[$option])) {
                return [
                    'content' => $locale === 'fa'
                        ? 'در فهرست قبلی این شماره گزینه وجود ندارد. لطفاً یکی از گزینه‌های نمایش‌داده‌شده را انتخاب کنید.'
                        : 'That option was not in the previous list. Please choose one of the displayed products.',
                    'product_cards' => [],
                    'tool' => null,
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                    'catalog_data' => [],
                    'contact_links' => [],
                    'contact_locations' => [],
                    'remembered_product_ids' => $previousProductIds,
                    'billing_status' => 'nonbillable',
                ];
            }

            $selectedProductId = (int) $previousProductIds[$option];
            $resolved = $this->tools->answerContext(
                'this product',
                $locale,
                [$selectedProductId]
            );

        } elseif (
            $previousProductIds !== []
            && $comparing
            && preg_match(
                '/\b(?:these|them|those|previous|first|second|third)\b|این(?:ها|‌ها)?|اون(?:ها|‌ها)?|قبلی|اولی|دومی|سومی/iu',
                $message
            ) === 1
        ) {
            // Keep the previous catalog list for comparison.
            $resolved = $this->tools->answerContext(
                'compare these',
                $locale,
                $previousProductIds
            );
        }

        // Preserve the conversation for translation and language-switch requests.
        // These requests must reach the provider instead of the generic fallback.
        $isLanguageFollowUp = $history !== [] && preg_match(
            '/(?:explain|say|repeat|translate|rewrite|rephrase|summarize).{0,90}(?:in|into|to)\s+(?:english|persian|farsi)|(?:in|into)\s+(?:english|persian|farsi).{0,90}(?:please|explain|say)|(?:به\s+فارسی|به\s+انگلیسی|فارسی|انگلیسی).{0,65}(?:توضیح|بگو|بنویس|ترجمه|بازگو|خلاصه)|(?:توضیح|ترجمه|بازگو|خلاصه).{0,65}(?:فارسی|انگلیسی)/iu',
            $message
        ) === 1;

        if ($isLanguageFollowUp) {
            if ($previousProductIds !== []) {
                $resolved = $this->tools->answerContext(
                    'this product',
                    $locale,
                    $previousProductIds
                );
            }

            if ($resolved['tool'] === null) {
                $resolved = [
                    'tool' => 'get_product_details',
                    'context' => [
                        'tool' => 'get_product_details',
                        'products' => [],
                    ],
                    'product_cards' => [],
                ];
            }
        }

        // Links are authoritative server data, never model-generated text.
        // A request for both explanation and link needs a model answer,
        // followed by an authoritative catalog URL.
        $wantsLinkAndExplanation =
            ShoppingAssistantConversationIntent::wantsProductLink($message)
            && preg_match(
                '/(?:explain|describe|tell me more|more about|details|recommend|why|توضیح|بیشتر بگو|اطلاعات بیشتر|معرفی|راهنمایی)/iu',
                $message
            ) === 1;

        if (
            ShoppingAssistantConversationIntent::wantsProductLink($message)
            && ! $wantsLinkAndExplanation
        ) {
            $cards = $resolved['context']['products'] ?? [];
            $cards = is_array($cards) ? array_values(array_filter(
                $cards,
                static fn ($card): bool => is_array($card)
                    && is_int($card['id'] ?? null)
                    && is_string($card['url'] ?? null)
                    && preg_match('~^(?:https://[^\s]+|/products/[^\s]+)$~', $card['url']) === 1
            )) : [];
            $index = ShoppingAssistantConversationIntent::selectedIndex($message);

            // The router already narrowed this follow-up to one product.
            // Do not apply the original ordinal again to that single card.
            if (
                $previousProductIds !== []
                && $option !== null
                && ! $comparing
            ) {
                $index = null;
            }
            if ($index !== null) {
                $cards = isset($cards[$index]) ? [$cards[$index]] : [];
            }
            if (count($cards) === 1) {
                $card = $cards[0];
                $text = $locale === 'fa'
                    ? 'لینک صفحه محصول «' . $card['name'] . '": ' . $card['url']
                    : 'Here is the product page for ' . $card['name'] . ': ' . $card['url'];
                return [
                    'content' => $text,
                    'product_cards' => $cards,
                    'tool' => 'get_product_details',
                    'input_tokens' => 0, 'output_tokens' => 0,
                    'catalog_data' => [],
                    'contact_links' => [], 'contact_locations' => [],
                    'remembered_product_ids' => [(int) $card['id']],
                    'billing_status' => 'nonbillable',
                ];
            }
            if ($index !== null || count($cards) !== 1) {
                return [
                    'content' => $locale === 'fa'
                        ? 'لینک کدام محصول را می‌خواهید؟ لطفاً نام محصول یا شماره گزینه را بفرمایید.'
                        : 'Which product would you like a link to? Please tell me its name or option number.',
                    'product_cards' => $cards,
                    'tool' => 'get_product_details',
                    'input_tokens' => 0, 'output_tokens' => 0,
                    'catalog_data' => [], 'contact_links' => [], 'contact_locations' => [],
                    'remembered_product_ids' => array_values(array_map(
                        static fn (array $card): int => (int) $card['id'], $cards
                    )),
                    'billing_status' => 'nonbillable',
                ];
            }
        }

        // A short reply to Luna's previous question is conversation context, not
        // a new catalog query. Do not prematurely replace it with the generic
        // introductory fallback. Existing catalog/tool routes remain authoritative.
        $isConsultationContinuation = ShoppingAssistantContextualFollowUp::isContinuation(
            $message, $history, $previousProductIds
        );
        $isDecisionContinuation = $isConsultationContinuation && preg_match(
            '/(?:از بین|گزینه|انتخاب کن|کدوم|کدام|چی پیشنهاد|پیشنهاد می(?:‌|\s*)کنی|هردوش|هر دو|both|choose one|which one|recommend)/iu',
            $message
        ) === 1;
        // Do not discard an existing, authenticated product shortlist on a
        // comparative/decision follow-up just because a generic keyword routed
        // the latest utterance to an empty fresh catalog search.
        if ($previousProductIds !== [] && $isDecisionContinuation
            && in_array($resolved['tool'], [null, 'search_products'], true)
            && ($resolved['context']['products'] ?? []) === []) {
            $resolved = $this->tools->answerContext('compare these', $locale, $previousProductIds);
            if (($resolved['context']['products'] ?? []) !== []) {
                // The intent is to continue the consultation, not to browse afresh.
                $resolved['tool'] = 'conversation_follow_up';
                $resolved['context']['tool'] = 'conversation_follow_up';
            }
        }

        if (
            $resolved['tool'] === null
            && $isConsultationContinuation
        ) {
            // Rehydrate the previous displayed products from the live catalog.
            // History is conversational context, not trusted product evidence.
            $previous = $previousProductIds !== []
                ? $this->tools->answerContext('compare these', $locale, $previousProductIds)
                : null;
            $trustedProducts = is_array($previous['context']['products'] ?? null)
                ? $previous['context']['products'] : [];
            $resolved = [
                'tool' => 'conversation_follow_up',
                'context' => [
                    'tool' => 'conversation_follow_up',
                    'products' => $trustedProducts,
                ],
                'product_cards' => $trustedProducts === []
                    ? [] : ($previous['product_cards'] ?? []),
            ];
        }

        // Real comparison/anxiety consultation must not become a generic greeting
        // just because the catalog router found no exact product search term.
        // Never invent product records; let the model use an empty trusted catalog.
        // Stage 3 release candidate: explicit correction/reference to a prior
        // shopping discussion must not reset to the generic catalog introduction.
        // History is conversational only; it never certifies any product fact.
        if ($resolved['tool'] === null && ShoppingAssistantStage3ContextBridge::needsContinuation($message, $history)) {
            $resolved = [
                'tool' => 'conversation_follow_up',
                'context' => ['tool' => 'conversation_follow_up', 'products' => []],
                'product_cards' => [],
            ];
        }

        if ($resolved['tool'] === null && ShoppingAssistantAdvisoryIntent::needsAdvice($message)) {
            $resolved = [
                'tool' => 'conversation_follow_up',
                'context' => ['tool' => 'conversation_follow_up', 'products' => []],
                'product_cards' => [],
            ];
        }

        // Stage 4 final acceptance: a customer demanding an unverified lifetime
        // or multi-year assurance needs a contextual answer, not the generic intro.
        // This route grants NO warranty: it only lets the model explain uncertainty.
        if ($resolved['tool'] === null && ShoppingAssistantWarrantyAssuranceIntent::needsResponse($message)) {
            $resolved = [
                'tool' => 'conversation_follow_up',
                'context' => ['tool' => 'conversation_follow_up', 'products' => []],
                'product_cards' => [],
            ];
        }

        if ($resolved['tool'] === null) {
            return [
                'content' => $locale === 'fa'
                    ? 'می‌توانم در انتخاب محصولات آشپزخانه و خانه از کاتالوگ TKB کمک کنم. دنبال چه محصول یا کاربردی هستید؟'
                    : 'I can help you explore TKB’s kitchen and home collection. What are you hoping to find?',
                'product_cards' => [],
                'tool' => null,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'catalog_data' => [],
                'contact_links' => [],
                'contact_locations' => [],
                'remembered_product_ids' => [],
                'billing_status' => 'nonbillable',
            ];
        }

        if ($resolved['tool'] === 'get_store_information') {
            return [
                'content' => $locale === 'fa'
                    ? 'راه‌های ارتباطی تأییدشده و اطلاعات شعب فعال فروشگاه در ادامه آمده است.'
                    : 'Here are the verified contact options and active store locations.',
                'product_cards' => [],
                'tool' => $resolved['tool'],
                'input_tokens' => 0,
                'output_tokens' => 0,
                'catalog_data' => [],
                'contact_links' => $resolved['contact_links'] ?? $this->tools->contactLinks($locale),
                'contact_locations' => $resolved['contact_locations'] ?? [],
                'remembered_product_ids' => [],
                'billing_status' => 'nonbillable',
            ];
        }

        $isPriceRequest = $resolved['tool'] === 'get_pricing';
        $isMixedPriceRequest = $isPriceRequest && $this->requestsProductHelp($message);
        if ($isPriceRequest && ! $isMixedPriceRequest) {
            return [
                'content' => $this->pricingResponse($locale),
                'product_cards' => [],
                'tool' => $resolved['tool'],
                'input_tokens' => 0,
                'output_tokens' => 0,
                'catalog_data' => [],
                'contact_links' => $resolved['contact_links'] ?? $this->tools->contactLinks($locale),
                'contact_locations' => $resolved['contact_locations'] ?? [],
                'remembered_product_ids' => [],
                'billing_status' => 'nonbillable',
            ];
        }

        $resolved['context'] = $this->productKnowledge->enrich($resolved['context']);
        $resolved['context']['consultation'] = ShoppingAssistantConsultationPlanner::context(
            $consultationState,
            $message
        );

        // The server-selected product is authoritative over earlier prose.
        // Product details are re-read from the live, published catalog.
        $selectedCard = null;
        if ($selectedProductId !== null) {
            foreach ($resolved['context']['products'] ?? [] as $candidate) {
                if (
                    is_array($candidate)
                    && (int) ($candidate['id'] ?? 0) === $selectedProductId
                ) {
                    $selectedCard = $candidate;
                    break;
                }
            }
            if ($selectedCard !== null) {
                $resolved['context']['selected_product'] = [
                    'id' => $selectedProductId,
                    'name' => $selectedCard['name'] ?? '',
                    'selection_source' => 'server_resolved_ordinal',
                ];
            }
        }

        // Best-effort registration only; never wait for web research here.
        try {
            $this->researchTrigger->registerIfNeeded(
                $message,
                (string) $resolved['tool'],
                $resolved['context']
            );
        } catch (\Throwable $exception) {
            report($exception);
        }

        $contextLimit = max(0, (int) config('shopping_assistant.max_context_messages', 8));
        $maxCharacters = max(500, (int) config('shopping_assistant.max_context_characters', 5000));
        $context = [];
        $remaining = $maxCharacters;

        $recentHistory = array_slice($history, -$contextLimit);
        foreach (array_reverse($recentHistory) as $entry) {
            if (
                ! is_array($entry)
                || ! in_array($entry['role'] ?? null, ['user', 'assistant'], true)
                || ! is_string($entry['content'] ?? null)
            ) {
                continue;
            }

            $content = mb_substr($entry['content'], 0, $remaining);
            if ($content === '') {
                break;
            }
            $context[] = ['role' => $entry['role'], 'content' => $content];
            $remaining -= mb_strlen($content);
        }
        $context = array_reverse($context);

        $providerHistory = [...$context, ['role' => 'user', 'content' => $message]];
        $maxOutputTokens = min(
            1000,
            max(1, (int) config('shopping_assistant.max_output_tokens', 500))
        );
        $reservationId = $this->budgetGuard->reserve(
            $conversationId,
            $this->budgetGuard->estimateInputTokens($message, $context, $resolved['context']),
            $maxOutputTokens
        );

        if ((string) config('shopping_assistant.driver') === 'gapgpt' && (bool) config('shopping_assistant.daily_quota_enabled', false)) {
            try {
                $this->dailyQuota->admit($quotaSubject, $locale);
            } catch (ShoppingAssistantDailyQuotaExceeded $exception) {
                if ($reservationId !== null) {
                    $this->budgetGuard->releaseBeforeDispatch($reservationId);
                }

                throw $exception;
            } catch (ShoppingAssistantUnavailable $exception) {
                if ($reservationId !== null) {
                    throw $this->reconcileReservation($reservationId, $exception)
                        ->withReservationId($reservationId);
                }

                throw $exception;
            }
        }

        try {
            $completion = $this->provider->complete(
                $locale,
                $providerHistory,
                $resolved['context'],
                $maxOutputTokens
            );
        } catch (\Throwable $exception) {
            $failure = $exception instanceof ShoppingAssistantUnavailable
                ? $exception
                : new ShoppingAssistantUnavailable(
                    'The shopping assistant provider failed unexpectedly.',
                    previous: $exception,
                    diagnostics: [
                        'failure_stage' => 'provider_call',
                        'failure_category' => 'provider_unexpected_error',
                        'dispatch_state' => 'attempted',
                    ]
                );

            if ($reservationId !== null) {
                $failure = $this->reconcileReservation($reservationId, $failure);
            }

            throw $failure->withReservationId($reservationId);
        }

        if (
            $reservationId !== null
            && (
                ! is_numeric($completion['input_tokens'] ?? null)
                || ! is_numeric($completion['output_tokens'] ?? null)
                || (int) $completion['input_tokens'] < 1
                || (int) $completion['output_tokens'] < 1
            )
        ) {
            $failure = new ShoppingAssistantUnavailable(
                'Provider token usage is unavailable.',
                diagnostics: [
                    ...($completion['_provider_diagnostics'] ?? []),
                    'failure_stage' => 'provider_usage',
                    'failure_category' => 'provider_usage_unavailable',
                    'dispatch_state' => 'response_received',
                ]
            );
            throw $this->reconcileReservation($reservationId, $failure)
                ->withReservationId($reservationId);
        }
        if (
            ! is_string($completion['content'] ?? null)
            || trim($completion['content']) === ''
            || ! is_numeric($completion['input_tokens'] ?? null)
            || ! is_numeric($completion['output_tokens'] ?? null)
            || (int) $completion['input_tokens'] < 1
            || (int) $completion['output_tokens'] < 1
        ) {
            $failure = new ShoppingAssistantUnavailable(
                'Provider response or usage data is unavailable.',
                diagnostics: [
                    ...($completion['_provider_diagnostics'] ?? []),
                    'failure_stage' => 'provider_usage',
                    'failure_category' => 'provider_usage_unavailable',
                    'dispatch_state' => 'response_received',
                ]
            );
            if ($reservationId !== null) {
                throw $this->reconcileReservation($reservationId, $failure)
                    ->withReservationId($reservationId);
            }

            throw $failure;
        }

        $content = trim($completion['content']);
        $priceDisclosureBlocked = $this->containsPriceDisclosure($content);
        if ($priceDisclosureBlocked) {
            $content = $this->pricingResponse($locale);
        } elseif ($this->contradictsCatalogAvailability($content, $resolved['product_cards'])) {
            $content = $this->availabilityCorrection($resolved['product_cards'], $locale, $message);
        } else {
            $content = $this->plainCustomerText($content);
        }
        if ($isMixedPriceRequest && ! $priceDisclosureBlocked) {
            $content = $this->removeUnverifiedContactClaims($content);
            $content = trim($content) === ''
                ? $this->pricingResponse($locale)
                : trim($content) . "\n\n" . $this->pricingResponse($locale);
        }
        if ($resolved['tool'] === 'purchase_guidance') {
            $content = $this->removeUnverifiedContactClaims($content);
            $content = $this->appendVerifiedPurchaseGuidance(
                $content,
                $resolved['product_cards'],
                $locale
            );
        }

        // Never combine the explanation of a different listed product
        // with a server-verified link for the selected product.
        if (
            $selectedCard !== null
            && $this->mentionsDifferentListedProduct(
                $content,
                $selectedProductId,
                $previousProductIds
            )
        ) {
            $content = $this->selectedProductFallback($selectedCard, $locale);
        }

        $productCards = config('shopping_assistant.driver') === 'mock'
            ? $resolved['product_cards']
            : $this->cardsNamedInResponse($content, $resolved['product_cards']);

        // When the answer continues the same consultation, keep the verified
        // shortlist even if only one model was mentioned in this turn. Never
        // carry it into an unrelated new catalog search.
        $continuationIds = [];
        if ($isConsultationContinuation && $previousProductIds !== []
            && in_array($resolved['tool'], ['conversation_follow_up', 'search_products', 'get_product_details'], true)) {
            $trustedIds = $this->rememberedProductIds($resolved['context']['products'] ?? []);
            $continuationIds = array_values(array_intersect($previousProductIds, $trustedIds));
        }

        // When the provider uses the optional V2 contract, display only
        // server-verified IDs, never cards manufactured by the model.
        // Selected-product, pricing, and purchase-guidance flows keep
        // their existing strict routing and display rules.
        if (
            $selectedCard === null
            && in_array($resolved['tool'], ['search_products', 'conversation_follow_up'], true)
            && config('shopping_assistant.driver') !== 'mock'
            && is_array($completion['recommended_product_ids'] ?? null)
        ) {
            $productCards = ShoppingAssistantResponseContract::verifiedCards(
                $completion['recommended_product_ids'],
                $resolved['product_cards']
            );
        }

        if ($wantsLinkAndExplanation) {
            $verifiedLinks = $resolved['context']['products'] ?? [];
            $verifiedLinks = is_array($verifiedLinks)
                ? array_values(array_filter(
                    $verifiedLinks,
                    static fn ($card): bool => is_array($card)
                        && is_int($card['id'] ?? null)
                        && is_string($card['name'] ?? null)
                        && is_string($card['url'] ?? null)
                        && preg_match(
                            '~^(?:https://[^\s]+|/products/[^\s]+)$~',
                            $card['url']
                        ) === 1
                ))
                : [];

            // Never invent a URL or choose arbitrarily between products.
            if (count($verifiedLinks) === 1) {
                $linkCard = $verifiedLinks[0];

                $content = rtrim($content) . "\n\n"
                    . ($locale === 'fa'
                        ? 'لینک صفحه محصول «' . $linkCard['name'] . '»: '
                        : 'Product page for ' . $linkCard['name'] . ': ')
                    . $linkCard['url'];

                $productCards = [$linkCard];
            }
        }

        $estimatedInputTokens = max(
            1,
            (int) ceil(mb_strlen(json_encode(
                [$context, $message, $resolved['context']],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            ) ?: '') / 4)
        );
        $estimatedOutputTokens = max(1, (int) ceil(mb_strlen($completion['content']) / 4));

        return [
            ...$completion,
            'content' => $content,
            'input_tokens' => max(0, (int) ($completion['input_tokens'] ?? 0)) ?: $estimatedInputTokens,
            'output_tokens' => max(0, (int) ($completion['output_tokens'] ?? 0)) ?: $estimatedOutputTokens,
            'product_cards' => $productCards,
            'tool' => $resolved['tool'],
            'catalog_data' => array_intersect_key(
                $resolved['context'],
                array_flip(['categories', 'stores'])
            ),
            'contact_links' => (
                in_array($resolved['tool'], [
                    'purchase_guidance',
                    'get_pricing',
                ], true) || $priceDisclosureBlocked || $isMixedPriceRequest
            ) ? ($resolved['contact_links'] ?? $this->tools->contactLinks($locale)) : [],
            'contact_locations' => in_array(
                $resolved['tool'],
                ['purchase_guidance', 'get_pricing'],
                true
            ) ? ($resolved['contact_locations'] ?? []) : [],
            // The order a customer sees is the order ordinal follow-ups use.
            'remembered_product_ids' => array_slice(array_values(array_unique([
                ...$continuationIds,
                ...$this->rememberedProductIds(
                    $productCards !== []
                        ? $productCards
                        : ($resolved['context']['products'] ?? [])
                ),
            ])), 0, 5),
            'budget_reservation_id' => $reservationId,
            'billing_status' => 'success',
        ];
    }

    private function requestsProductHelp(string $message): bool
    {
        $normalized = \App\Support\SearchTextNormalizer::normalize($message);

        foreach ([
            'recommend', 'suggest', 'find', 'search', 'looking for', 'which product',
            'availability', 'available', 'in stock', 'compare', 'comparison', 'versus',
            'vs', 'which one', 'which is better', 'recommend', 'پیشنهاد', 'معرفی',
            'پیدا', 'موجود', 'قابل سفارش', 'مناسب', 'انتخاب',
        ] as $intent) {
            if (str_contains($normalized, $intent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $products
     * @return array<int, int>
     */
    private function rememberedProductIds(mixed $products): array
    {
        if (! is_array($products)) {
            return [];
        }

        return array_slice(array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $product): int => is_array($product)
                    ? (int) ($product['id'] ?? 0)
                    : 0,
                $products
            ),
            static fn (int $id): bool => $id > 0
        ))), 0, 5);
    }

    /**
     * @param array<int, array<string, mixed>> $cards
     * @return array<int, array<string, mixed>>
     */
    private function cardsNamedInResponse(string $content, array $cards): array
    {
        $candidateIds = array_values(array_unique(array_filter(
            array_map(static fn (array $card): int => (int) ($card['id'] ?? 0), $cards),
            static fn (int $id): bool => $id > 0
        )));
        if ($candidateIds === []) {
            return [];
        }

        // The candidate set comes exclusively from server-verified catalog results.
        // Never search the entire catalog or trust product IDs invented by the model.
        $products = Product::query()->whereKey($candidateIds)
            ->get(['id', 'name', 'brand'])->keyBy('id');
        $normalizedResponse = ' ' . SearchTextNormalizer::normalize($content) . ' ';
        $patterns = [];
        $aliasOwners = [];

        foreach ($cards as $card) {
            $id = (int) ($card['id'] ?? 0);
            $product = $products->get($id);
            if (! $product instanceof Product) {
                continue;
            }
            $names = json_decode((string) $product->getRawOriginal('name'), true);
            if (! is_array($names)) {
                continue;
            }
            foreach ($names as $name) {
                if (! is_string($name) || trim($name) === '') {
                    continue;
                }
                $full = SearchTextNormalizer::normalize($name);
                if ($full === '') {
                    continue;
                }
                $patterns[$id]['full'][$full] = true;
                $words = preg_split('/\s+/u', $full) ?: [];
                if (count($words) < 3) {
                    continue;
                }

                // Persian catalog labels commonly end in a brand + distinctive
                // model (e.g. «زویلینگ موشن»), while English names can start
                // with that combination (e.g. "Zwilling Motion Set of 5").
                if (preg_match('/[\x{0600}-\x{06FF}]/u', $full) === 1) {
                    $pair = array_slice($words, -2);
                } else {
                    $pair = array_slice($words, 0, 2);
                    $brand = SearchTextNormalizer::normalize((string) ($product->brand ?? ''));
                    if ($brand === '' || $pair[0] !== $brand) {
                        continue;
                    }
                }
                if (count($pair) !== 2
                    || preg_match('/^\p{L}{3,}$/u', $pair[0]) !== 1
                    || preg_match('/^\p{L}{4,}$/u', $pair[1]) !== 1) {
                    continue;
                }
                $alias = implode(' ', $pair);
                $patterns[$id]['alias'][$alias] = true;
                $aliasOwners[$alias][$id] = true;
            }
        }

        $positions = [];
        foreach ($cards as $card) {
            $id = (int) ($card['id'] ?? 0);
            if (! isset($patterns[$id])) {
                continue;
            }
            $position = PHP_INT_MAX;
            foreach (array_keys($patterns[$id]['full'] ?? []) as $full) {
                $found = mb_strpos($normalizedResponse, ' ' . $full . ' ');
                if ($found !== false) {
                    $position = min($position, $found);
                }
            }
            foreach (array_keys($patterns[$id]['alias'] ?? []) as $alias) {
                // If the same short name belongs to multiple candidates,
                // fail closed rather than show an arbitrary product card.
                if (count($aliasOwners[$alias] ?? []) !== 1) {
                    continue;
                }
                $found = mb_strpos($normalizedResponse, ' ' . $alias . ' ');
                if ($found !== false) {
                    $position = min($position, $found);
                }
            }
            if ($position !== PHP_INT_MAX) {
                $positions[$id] = $position;
            }
        }

        $namedCards = array_values(array_filter(
            $cards,
            static fn (array $card): bool => isset($positions[(int) ($card['id'] ?? 0)])
        ));
        usort($namedCards, static fn (array $a, array $b): int =>
            ($positions[(int) $a['id']] <=> $positions[(int) $b['id']])
                ?: ((int) $a['id'] <=> (int) $b['id'])
        );

        return $namedCards;
    }

    /**
     * Detect a different previously displayed product named in a single-
     * product answer. Missing or ambiguous names are not treated as a match.
     *
     * @param array<int, int> $previousProductIds
     */
    private function mentionsDifferentListedProduct(
        string $content,
        int $selectedProductId,
        array $previousProductIds
    ): bool {
        $otherIds = array_values(array_filter(
            array_map('intval', $previousProductIds),
            static fn (int $id): bool => $id > 0 && $id !== $selectedProductId
        ));
        if ($otherIds === []) {
            return false;
        }

        $normalizedContent = ' ' . SearchTextNormalizer::normalize($content) . ' ';
        $otherProducts = Product::query()->whereKey($otherIds)->get(['id', 'name']);
        foreach ($otherProducts as $product) {
            $names = json_decode((string) $product->getRawOriginal('name'), true);
            foreach (is_array($names) ? $names : [] as $name) {
                if (! is_string($name)) {
                    continue;
                }
                $normalizedName = SearchTextNormalizer::normalize($name);
                if (
                    $normalizedName !== ''
                    && str_contains($normalizedContent, ' ' . $normalizedName . ' ')
                ) {
                    return true;
                }
            }
        }
        return false;
    }

    /** @param array<string, mixed> $card */
    private function selectedProductFallback(array $card, string $locale): string
    {
        $name = (string) ($card['name'] ?? '');
        $availability = (string) ($card['availability'] ?? '');
        $status = match ($availability) {
            'in_stock' => $locale === 'fa' ? 'موجود' : 'in stock',
            'made_to_order' => $locale === 'fa' ? 'قابل سفارش' : 'made to order',
            'out_of_stock' => $locale === 'fa' ? 'ناموجود' : 'out of stock',
            default => $locale === 'fa' ? 'با موجودی نیازمند استعلام' : 'with availability to confirm',
        };

        if ($locale === 'fa') {
            return 'محصول انتخاب‌شده شما «' . $name . '» است و وضعیت آن در کاتالوگ «'
                . $status . '» ثبت شده است.'
                . ' برای جزئیات فنی بیشتر، اطلاعات صفحه محصول را بررسی کنید.';
        }

        return 'Your selected product is ' . $name . ' (' . $status . ').'
            . ' Check the product page for verified technical details.';
    }

    private function containsPriceDisclosure(string $content): bool
    {
        $amount = '[0-9۰-۹٠-٩]+(?:[,.٫٬][0-9۰-۹٠-٩]+)*(?:\s*(?:thousand|million|billion|هزار|میلیون|میلیارد))?';
        $currency = '(?:toman|tomans|تومان|rial|rials|ریال|usd|eur|gbp|irr|aed|cad|aud|chf|sar|jpy|cny|kwd|try|rub|درهم|ریال\s?سعودی|[$€£¥])';
        $financialTerm = '(?:\b(?:price|cost|pricing|priced(?:\s+at)?|estimate(?:d)?|quote)\b|قیمت|هزینه|برآورد)';
        $discountTerm = '(?:\b(?:discount|sale|off|offer|promotion(?:al)?)\b|تخفیف|فروش ویژه|حراج)';
        $approximationTerm = '(?:\b(?:around|about|approximately|roughly|approx\.?)\b|تقریباً|حدود|نزدیک)';
        $technicalUnit = '(?:%|percent\b|درصد|m\b|cm\b|mm\b|l\b|lit(?:er|re)s?\b|ml\b|kg\b|g\b|w\b|kw\b|v\b|hz\b|لیتر|میلی.?لیتر|سانتی.?متر|میلی.?متر|کیلوگرم|گرم|وات|ولت)';

        // Protect genuine measurements in approximate ranges, e.g.
        // 'حدود ۱۸ تا ۲۰ سانتی‌متر', from the generic unpriced-estimate
        // guard. Only the *approximation branch* sees this masked text;
        // explicit money/financial/discount detection still sees the original.
        $measurementRange = '/' . $approximationTerm
            . '[^\n.!?؟]{0,20}' . $amount
            . '(?:\s*(?:تا|الی|to|[-–])\s*' . $amount . ')?'
            . '\s*' . $technicalUnit . '/iu';
        $estimateOnlyText = preg_replace($measurementRange, '', $content) ?? $content;

        return preg_match('/' . $amount . '\s*' . $currency . '|' . $currency . '\s*' . $amount . '/iu', $content) === 1
            || preg_match('/' . $financialTerm . '[^\n.!?؟]{0,50}' . $amount . '|' . $amount . '[^\n.!?؟]{0,50}' . $financialTerm . '/iu', $content) === 1
            || preg_match('/' . $discountTerm . '[^\n.!?؟]{0,40}' . $amount . '\s*(?:%|percent|درصد)?|' . $amount . '\s*(?:%|percent|درصد)\s*[^\n.!?؟]{0,40}' . $discountTerm . '/iu', $content) === 1
            || preg_match('/' . $approximationTerm . '[^\n.!?؟]{0,20}' . $amount . '(?!\s*' . $technicalUnit . ')/iu', $estimateOnlyText) === 1;
    }

    /**
     * @param array<int, array<string, mixed>> $productCards
     */
    private function contradictsCatalogAvailability(string $content, array $productCards): bool
    {
        $normalized = \App\Support\SearchTextNormalizer::normalize($content);
        // LUNA_QUALIFIED_AVAILABILITY_V24
        // A *qualified*, product-specific uncertainty is not a stock denial.
        // Keep verified catalog stock authoritative; never waive broad claims.
        $hasVerifiedStock = collect($productCards)->contains(
            static fn (array $card): bool => ($card['availability'] ?? null) === 'in_stock'
        );
        if (! $hasVerifiedStock) {
            $qualified = preg_match(
                '/(?:موجودی|وضعیت موجودی)[^.!?؟\r\n]{0,100}(?:نیاز(?:مند)? به (?:تأیید|تایید|بررسی|استعلام)|باید[^.!?؟\r\n]{0,35}(?:تأیید|تایید|بررسی|استعلام) شود)/u',
                $content
            ) === 1;
            $absolute = preg_match(
                '/(?:هیچ|همه|تمام)[^.!?؟\r\n]{0,90}(?:ناموجود|موجود|قابل سفارش|در دسترس)|(?:ناموجود هستند|موجود نیستند|موجود است|موجودند|در انبار|out of stock|in stock|all (?:products|options)|no (?:products|options))/iu',
                $content
            ) === 1;
            if ($qualified && ! $absolute) {
                return false;
            }
        }
        $broadClaim = preg_match(
            '/\b(?:no|none|not\s+any)\b.{0,60}\b(?:products?|options?|items?)\b.{0,40}\b(?:available|in stock)\b|\b(?:all|every)\b.{0,80}\b(?:out of stock|unavailable|available|in stock)\b|\bno\b.{0,40}\b(?:in stock|available)\b/iu',
            $content
        ) === 1
            || preg_match(
                '/(?:هیچ|همه|تمام).{0,80}(?:موجود|قابل سفارش|در دسترس|ناموجود).{0,50}(?:نیست|نیستند|نداریم|نمی|ناموجود|هست|اند|موجودند)/u',
                $normalized
            ) === 1;

        if ($broadClaim) {
            return true;
        }

        if (! collect($productCards)->contains(
            static fn (array $product): bool => ($product['availability'] ?? null) === 'in_stock'
        )) {
            return false;
        }

        return preg_match(
            '/(?:محصول|گزینه).{0,50}(?:ندارم|ندارد|نیست).{0,60}(?:موجودی|موجود|در دسترس)|(?:موجودی|موجود|در دسترس).{0,60}(?:محصول|گزینه).{0,50}(?:ندارم|ندارد|نیست)/u',
            $normalized
        ) === 1
            || preg_match(
                '/(?:فهرست|لیست|کاتالوگ|اطلاعات).{0,100}(?:محصول|گزینه|موجودی).{0,100}(?:نشان نمی دهد|نمی دهد|قابل تایید نیست|مشخص نیست|ثبت نشده|ذکر نشده|ارائه نشده|درج نشده)|(?:نمی توانم|نمی توانیم|نمی شود).{0,60}(?:محصول|گزینه).{0,40}(?:موجود|موجودی|در دسترس)|(?:موجودی|موجود|در دسترس).{0,80}(?:نشان نمی دهد|نمی دهد|قابل تایید نیست|مشخص نیست|ثبت نشده|ذکر نشده|ارائه نشده|درج نشده)/u',
                $normalized
            ) === 1;
    }

    /**
     * @param array<int, array<string, mixed>> $productCards
     */
    private function availabilityCorrection(array $productCards, string $locale, string $message = ''): string
    {
        $product = collect($productCards)->first(
            static fn (array $card): bool => ($card['availability'] ?? null) === 'in_stock'
        );
        $name = trim((string) ($product['name'] ?? ''));

        if ($name === '') {
            // Keep the broad-stock-claim guard intact, but make a
            // corrected response relevant to the current question.
            if (preg_match('/وزن|چقدر سنگین|weight|how heavy/iu', $message) === 1) {
                return $locale === 'fa'
                    ? 'برای گفتن وزن دقیق، باید مدل موردنظرتان مشخص باشد و عدد وزن در اطلاعات معتبر محصول تأیید شود. اگر منظورتان یکی از دو گزینه قبلی است، نامش را بگویید تا اطلاعات همان مدل را بررسی کنیم؛ عددی حدس نمی‌زنم.'
                    : 'For an exact weight, I need the specific model and a verified product specification. Please identify which earlier option you mean; I will not guess a number.';
            }

            return $locale === 'fa'
                ? 'برای انتخاب دقیق‌تر، نیاز و اولویت‌هایی را که گفتید مبنا قرار می‌دهم؛ اگر نکته دیگری هست، بفرمایید.'
                : 'I’ll use the needs and priorities you’ve shared to guide the selection. Let me know if there is another detail to consider.';
        }

        return $locale === 'fa'
            ? 'طبق کاتالوگ فعلی، «' . $name . '» موجود است. اولویت‌هایی را که گفتید در ارزیابی گزینه‌ها لحاظ می‌کنم و فقط به مشخصات ثبت‌شده تکیه می‌کنم.'
            : 'The current catalog lists ' . $name . ' as in stock. I’ll assess it against the priorities you shared and use only its published details.';
    }

    private function pricingResponse(string $locale): string
    {
        return $locale === 'fa'
            ? 'قیمت روز و هرگونه پیشنهاد را باید مستقیماً از فروشگاه TKB تأیید کنید. از راه‌های ارتباطی رسمی زیر بپرسید؛ برای انتخاب محصول و ادامه مسیر خرید هم می‌توانم راهنمایی‌تان کنم.'
            : 'Current pricing and any offers need direct confirmation from TKB. Please use one of the verified contact options below; I can also help you choose and continue your purchase.';
    }

    private function plainCustomerText(string $content): string
    {
        $content = preg_replace('/```[^\n]*\n?|\n?```/u', '', $content) ?? $content;
        $content = preg_replace('/\[([^\]]+)\]\([^)]+\)/u', '$1', $content) ?? $content;
        $content = preg_replace('/(?:https?:\/\/|www\.)[^\s<>()]+/iu', '', $content) ?? $content;
        $content = preg_replace('~(?<![\w])/(?:contact|products?)(?:/[^\s]*)?~iu', '', $content) ?? $content;
        $content = preg_replace('/^\s{0,3}#{1,6}\s*/mu', '', $content) ?? $content;
        $content = preg_replace('/(\*\*|__)(.*?)\1/us', '$2', $content) ?? $content;
        $content = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/u', '$1', $content) ?? $content;
        $content = preg_replace('/`([^`\n]+)`/u', '$1', $content) ?? $content;

        return trim(preg_replace('/[ \t]{2,}/u', ' ', $content) ?? $content);
    }

    /**
     * @param array<int, array<string, mixed>> $productCards
     */
    private function appendVerifiedPurchaseGuidance(
        string $content,
        array $productCards,
        string $locale
    ): string {
        $lines = [];
        if ($productCards !== []) {
            $lines[] = $locale === 'fa'
                ? 'برای ادامه بررسی، کارت محصول انتخاب‌شده را باز کنید.'
                : 'To continue, open the selected product card.';
        } else {
            $lines[] = $locale === 'fa'
                ? 'اگر هنوز محصولی انتخاب نکرده‌اید، نوع محصول یا کاربرد موردنظرتان را بفرمایید تا گزینه‌های کاتالوگ را بررسی کنیم.'
                : 'If you have not chosen a product yet, tell me what you are looking for and I can help explore catalog options.';
        }

        $lines[] = $locale === 'fa'
            ? 'مراحل سفارش یا جزئیاتی که در کاتالوگ نیامده را از گزینه‌های تماس رسمی زیر تأیید کنید.'
            : 'Confirm ordering steps or details not listed in the catalog through the official contact options below.';

        $footer = implode("\n", $lines);

        return trim($content) === '' ? $footer : trim($content) . "\n\n" . $footer;
    }

    private function removeUnverifiedContactClaims(string $content): string
    {
        $sentences = preg_split('/(?<=[.!?؟])\s+|\R+/u', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $safeSentences = [];
        foreach ($sentences as $sentence) {
            if (preg_match(
                '/(?:https?:\/\/|www\.|[\w.+-]+@[\w.-]+\.[a-z]{2,}|'
                    . 'contact|call(?:ed)?|phone|telephone|email|address|'
                    . 'checkout|shopping cart|payment|pay\b|order(?:ing)?|delivery|shipping|'
                    . 'warranty|return policy|reserve(?:d)?|'
                    . 'تماس|تلفن|ایمیل|آدرس|پرداخت|سبد خرید|سفارش|ارسال|تحویل|'
                    . 'گارانتی|ضمانت|مرجوعی|رزرو|ارتباط)/iu',
                $sentence
            ) === 1) {
                continue;
            }

            $safeSentences[] = trim($sentence);
        }

        return trim(implode(' ', array_filter($safeSentences)));
    }

    private function reconcileReservation(
        int $reservationId,
        ShoppingAssistantUnavailable $failure
    ): ShoppingAssistantUnavailable {
        try {
            if (($failure->diagnostics()['dispatch_state'] ?? null) === 'not_dispatched') {
                $this->budgetGuard->releaseBeforeDispatch($reservationId);
            } else {
                $this->budgetGuard->markUnknown($reservationId);
            }
        } catch (ShoppingAssistantUnavailable $reconciliationFailure) {
            return new ShoppingAssistantUnavailable(
                'Budget reservation reconciliation failed.',
                previous: $failure,
                diagnostics: [
                    ...$reconciliationFailure->diagnostics(),
                    'dispatch_state' => $failure->diagnostics()['dispatch_state'],
                    'provider_http_status' => $failure->diagnostics()['provider_http_status'],
                    'elapsed_ms' => $failure->diagnostics()['elapsed_ms'],
                    'provider_request_id' => $failure->diagnostics()['provider_request_id'],
                    'failure_category' => 'reservation_reconciliation_failed',
                ],
                reservationId: $reservationId
            );
        }

        return $failure;
    }
}
