<?php

namespace App\Services\ShoppingAssistant;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\Category;
use App\Models\ContactPageContent;
use App\Models\Product;
use App\Models\Store;
use App\Support\ProductSearchIndexer;
use App\Support\ProductSearchRelevanceRanker;
use App\Support\SearchTextNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ShoppingCatalogTools
{
    /**
     * @return array{tool: string|null, context: array<string, mixed>, product_cards: array<int, array<string, mixed>>}
     */
    public function answerContext(
        string $message,
        string $locale,
        array $previousProductIds = []
    ): array
    {
        $normalized = SearchTextNormalizer::normalize($message);
        // A standalone brand name is also a valid catalog request.
        $requestedBrand = $this->resolveRequestedBrand($normalized);
        $usesPreviousProduct = $previousProductIds !== []
            && (
                $this->referencesPreviousProduct($normalized)
                || $this->mentionsPreviousProduct($normalized, $previousProductIds)
            );

        if ($this->containsAny($normalized, [
            'category', 'categories', 'دسته بندی', 'دسته بندی ها',
        ])) {
            $categories = Category::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->limit(40)
                ->get(['id', 'parent_id', 'name', 'slug'])
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'parent_id' => $category->parent_id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])
                ->all();

            return [
                'tool' => 'list_categories',
                'context' => ['tool' => 'list_categories', 'categories' => $categories],
                'product_cards' => [],
            ];
        }

        if ($this->isPriceRequest($normalized) && ! $this->requestsProductHelp($normalized)) {
            return $this->contextWithStores('get_pricing', [], $locale);
        }

        if ($this->isContactRequest($normalized)) {
            return $this->contextWithStores('get_store_information', [], $locale);
        }

        if ($this->isPurchaseGuidanceRequest($normalized)) {
            $identifier = $this->productIdentifier($message);
            if ($identifier !== null) {
                $product = $this->catalogProducts()
                    ->where(function (Builder $query) use ($identifier): void {
                        if (ctype_digit($identifier)) {
                            $query->whereKey((int) $identifier);
                        } else {
                            $query->where('slug', $identifier);
                        }
                    })
                    ->first();
                $products = $product ? [$product] : [];
            } else {
                $productIds = array_slice($previousProductIds, 0, 5);
                $products = $productIds === []
                    ? []
                    : $this->catalogProducts()
                        ->whereIn('id', $productIds)
                        ->get()
                        ->sortBy(fn (Product $product) => array_search(
                            (int) $product->id,
                            $productIds,
                            true
                        ))
                        ->values()
                        ->all();
            }

            if ($products !== [] || $this->searchTerms($normalized) === '') {
                return $this->contextWithStores(
                    'purchase_guidance',
                    $products,
                    $locale
                );
            }
        }

        if (
            ! $usesPreviousProduct
            && $this->productIdentifier($message) === null
            && $this->isBroadDiscoveryRequest($normalized)
        ) {
            return [
                'tool' => 'search_products',
                'context' => ['tool' => 'search_products', 'products' => []],
                'product_cards' => [],
            ];
        }

        if (! $this->isShoppingRequest($normalized) && ! $usesPreviousProduct && $requestedBrand === null) {
            return $this->emptyContext();
        }

        $identifier = $this->productIdentifier($message);
        $tool = $this->isPurchaseGuidanceRequest($normalized)
            ? 'purchase_guidance'
            : ($this->isPriceRequest($normalized)
            ? 'get_pricing'
            : ($this->containsAny($normalized, [
                'stock', 'available', 'availability', 'in stock', 'موجودی', 'موجود', 'ناموجود',
            ]) ? 'check_stock' : 'search_products'));

        if ($usesPreviousProduct) {
            $products = $this->catalogProducts()
                ->whereIn('id', array_slice($previousProductIds, 0, 5))
                ->get()
                ->sortBy(fn (Product $product) => array_search(
                    (int) $product->id,
                    $previousProductIds,
                    true
                ))
                ->values()
                ->all();

            // Singular follow-ups refer to the most recently presented product.
            // Comparisons and plural questions retain the complete product list.
            if (
                preg_match('/\b(?:is it|does it|has it|its)\b/iu', $normalized) === 1
                && preg_match('/\b(?:which|compare|between|both|them)\b/iu', $normalized) !== 1
            ) {
                $products = array_slice($products, 0, 1);
            }

            $tool = $tool === 'search_products' ? 'get_product_details' : $tool;
        } elseif ($identifier !== null) {
            $product = $this->catalogProducts()
                ->where(function (Builder $query) use ($identifier): void {
                    if (ctype_digit($identifier)) {
                        $query->whereKey((int) $identifier);
                    } else {
                        $query->where('slug', $identifier);
                    }
                })
                ->first();

            $products = $product ? [$product] : [];
            $tool = $tool === 'search_products' ? 'get_product_details' : $tool;
        } else {
            $queryText = $this->searchTerms($normalized);
            $categoryScopeIds = $this->cookwareCategoryScope($normalized);
            $query = $this->catalogProducts();
            // Scope exact-brand requests before applying a candidate cap.
            // This avoids dropping relevant non-featured products from a large catalog.
            if ($requestedBrand !== null) {
                $query->where('brand', $requestedBrand);
            }
            if ($categoryScopeIds !== []) {
                $query->whereIn('category_id', $categoryScopeIds);
            }

            if ($queryText === '' && $categoryScopeIds === [] && $requestedBrand === null) {
                $products = [];
                $cards = [];

                return [
                    'tool' => $tool,
                    'context' => ['tool' => $tool, 'products' => []],
                    'product_cards' => $cards,
                ];
            }

            if ($queryText !== '' && $requestedBrand === null) {
                $terms = array_slice(explode(' ', $queryText), 0, 8);
                $query->where(function (Builder $productQuery) use ($terms): void {
                    foreach ($terms as $term) {
                        $productQuery->orWhere('search_text', 'like', '%' . $term . '%')
                            ->orWhere('search_compact', 'like', '%' . str_replace(' ', '', $term) . '%')
                            ->orWhere('short_description->en', 'like', '%' . $term . '%')
                            ->orWhere('short_description->fa', 'like', '%' . $term . '%')
                            ->orWhereHas('variants.attributeValues', fn (Builder $attributeValue) => $attributeValue
                                ->where('value', 'like', '%' . $term . '%'))
                            ->orWhereHas('category', fn (Builder $category) => $category
                                ->where('name->en', 'like', '%' . $term . '%')
                                ->orWhere('name->fa', 'like', '%' . $term . '%'));
                    }
                });
            }

            $candidates = $query->orderByDesc('is_featured')
                ->orderByDesc('id')
                ->limit($requestedBrand !== null ? 500 : 100)
                ->get();

            if ($requestedBrand !== null) {
                // Brand hits have already been verified by an exact DB filter.
                // Secondary product-name terms may improve ranking, but must not
                // exclude other products belonging to the requested brand.
                $brandNormalized = SearchTextNormalizer::normalize($requestedBrand);
                $remainingTerms = array_values(array_filter(
                    explode(' ', $queryText),
                    fn (string $term): bool => mb_strlen($term) >= 2
                        && ! str_contains($brandNormalized, $term)
                        && ! in_array($term, ['brand', 'برند', 'زویلینگ', 'زوئيلينگ', 'zwilling'], true)
                ));
                $candidates = $candidates->sortBy(function (Product $product) use ($remainingTerms): array {
                    $searchable = SearchTextNormalizer::normalize(implode(' ', [
                        ...ProductSearchIndexer::candidates($product),
                        $this->searchableVariantAttributes($product),
                    ]));
                    $hits = collect($remainingTerms)->filter(
                        fn (string $term): bool => str_contains($searchable, $term)
                    )->count();

                    return [-$hits, $this->availabilityRank($product), $product->is_featured ? 0 : 1, (int) $product->id];
                });
            } elseif ($queryText !== '') {
                $terms = explode(' ', $queryText);
                $candidates = $candidates
                    ->map(function (Product $product) use ($terms, $queryText): array {
                        $productName = SearchTextNormalizer::normalize(
                            implode(' ', [
                                ...ProductSearchIndexer::candidates($product),
                                $this->searchableVariantAttributes($product),
                            ])
                        );
                        $categoryName = SearchTextNormalizer::normalize(
                            (string) $product->category?->name
                        );
                        $description = SearchTextNormalizer::normalize(
                            (string) $product->short_description
                        );
                        $hits = collect($terms)->filter(fn (string $term): bool =>
                            str_contains($productName . ' ' . $categoryName . ' ' . $description, $term)
                        )->count();
                        $rank = ProductSearchRelevanceRanker::score($product, $queryText);
                        if ($rank === 1000 && $hits > 0) {
                            $rank = collect($terms)->contains(
                                fn (string $term): bool => str_contains($categoryName, $term)
                            ) ? 60 : 80;
                        }

                        return ['product' => $product, 'rank' => $rank, 'hits' => $hits];
                    })
                    ->filter(fn (array $candidate) => $candidate['hits'] > 0);

                $maxHits = (int) ($candidates->max('hits') ?? 0);
                $minimumHits = $categoryScopeIds !== []
                    ? 1
                    : min($maxHits, max(1, (int) ceil(count($terms) / 2)));
                $candidates = $candidates
                    ->filter(fn (array $candidate) => $candidate['hits'] >= $minimumHits)
                    ->sortBy(fn (array $candidate) => [
                        $candidate['rank'],
                        -$candidate['hits'],
                        $this->availabilityRank($candidate['product']),
                    ])
                    ->pluck('product');
            } else {
                $candidates = $candidates->sortBy(fn (Product $product) => [
                    $this->availabilityRank($product),
                    $product->is_featured ? 0 : 1,
                ]);
            }

            if (
                $this->requestsAvailableRecommendations($normalized)
                && ! $this->isAlternativeRequest($normalized)
            ) {
                $candidates = $candidates->filter(
                    fn (Product $product): bool => in_array(
                        $this->productAvailability($product),
                        ['in_stock', 'made_to_order'],
                        true
                    )
                );
            }

            // Initial discovery shows at most three choices; previous-product
            // references and alternative flows retain their existing semantics.
            $products = $candidates->take(min(3, max(1, (int) config('shopping_assistant.max_products', 5))))->all();
        }

        if (! $usesPreviousProduct) {
            $explicitlyNamedProducts = array_values(array_filter(
                $products,
                fn (Product $product): bool => $this->productNameAppearsInMessage(
                    $product,
                    $normalized
                )
            ));
            if ($explicitlyNamedProducts !== []) {
                $products = $explicitlyNamedProducts;
            }
        }

        if ($this->isAlternativeRequest($normalized) && $products !== []) {
            $existingProducts = collect($products);
            $categoryIds = $existingProducts
                ->pluck('category_id')
                ->filter()
                ->unique()
                ->flatMap(fn (int $categoryId): array => $this->categoryFamilyIds($categoryId))
                ->unique()
                ->values();
            $existingIds = $existingProducts->pluck('id')->all();
            $alternatives = $categoryIds->isEmpty()
                ? collect()
                : $this->catalogProducts()
                    ->whereIn('category_id', $categoryIds)
                    ->whereNotIn('id', $existingIds)
                    ->get()
                    ->filter(fn (Product $product) => in_array(
                        $this->availabilityRank($product),
                        [0, 1],
                        true
                    ))
                    ->sortBy(fn (Product $product) => $this->availabilityRank($product));
            $products = $existingProducts
                ->concat($alternatives)
                ->unique('id')
                ->sortBy(fn (Product $product) => $this->availabilityRank($product))
                ->take((int) config('shopping_assistant.max_products', 5))
                ->values()
                ->all();
        }

        if (
            $this->requestsAvailableRecommendations($normalized)
            && ! $this->isAlternativeRequest($normalized)
        ) {
            $products = array_values(array_filter(
                $products,
                fn (Product $product): bool => in_array(
                    $this->productAvailability($product),
                    ['in_stock', 'made_to_order'],
                    true
                )
            ));
        }

        if ($tool === 'purchase_guidance') {
            return $this->contextWithStores($tool, $products, $locale);
        }

        $cards = array_map(fn (Product $product) => $this->productCard($product, $locale), $products);
        // A verified exact-brand catalog request is product discovery even
        // without verbs such as "recommend" or "compare". Keep cards tied to
        // real product IDs rather than requiring a recommendation keyword.
        $showProductCards = $requestedBrand !== null || $this->shouldShowProductCards(
            $normalized,
            $usesPreviousProduct,
            $tool
        );

        return [
            'tool' => $tool,
            'context' => [
                'tool' => $tool,
                'products' => $cards,
            ],
            'product_cards' => $showProductCards ? $cards : [],
        ];
    }

    private function shouldShowProductCards(
        string $normalized,
        bool $usesPreviousProduct,
        string $tool
    ): bool {
        if (in_array($tool, ['get_store_information', 'list_categories'], true)) {
            return false;
        }

        return $usesPreviousProduct
            || $this->requestsProductHelp($normalized)
            || $this->isAlternativeRequest($normalized)
            || $this->containsAny($normalized, [
                'stock', 'in stock', 'available', 'availability', 'do you have',
                'have you got', 'موجود', 'موجودی', 'دارید', 'قابل سفارش',
            ]);
    }

    private function emptyContext(): array
    {
        return [
            'tool' => null,
            'context' => ['tool' => null, 'products' => []],
            'product_cards' => [],
        ];
    }

    private function activeProducts(): Builder
    {
        return Product::query()
            ->withCount('variants')
            ->where('status', ProductStatus::ACTIVE->value)
            ->whereHas('category', fn (Builder $category) => $category->where('is_active', true));
    }

    private function catalogProducts(): Builder
    {
        return $this->activeProducts()
            ->with(['category', 'images', 'variants' => fn ($query) => $query
                ->whereIn('status', [
                    VariantStatus::ACTIVE->value,
                    VariantStatus::MADE_TO_ORDER->value,
                ])
                ->with(['inventory', 'attributeValues.attribute'])
                ->orderBy('sort_order')]);
    }

    private function productCard(Product $product, string $locale): array
    {
        $availability = $this->productAvailability($product);
        $variants = $product->variants
            ->map(function ($variant): array {
                $attributes = $variant->attributeValues
                    ->map(fn ($attributeValue) => [
                        'name' => $this->safeProductText($attributeValue->attribute?->name),
                        'value' => $this->safeProductText($attributeValue->value),
                    ])
                    ->filter(fn (array $attribute) => $attribute['name'] !== null
                        && $attribute['value'] !== null
                        && ! $this->isFinancialAttribute(
                            $attribute['name'],
                            $attribute['value']
                        ))
                    ->unique(fn (array $attribute) => mb_strtolower($attribute['name']))
                    ->take(8)
                    ->values()
                    ->all();

                return [
                    'availability' => $variant->status === VariantStatus::MADE_TO_ORDER
                        ? 'made_to_order'
                        : (($variant->inventory?->availableQuantity() ?? 0) > 0
                            ? 'in_stock'
                            : 'out_of_stock'),
                    'attributes' => $attributes,
                ];
            })
            ->values()
            ->all();

        $image = $product->images->first();
        $customerUrl = rtrim((string) config('shopping_assistant.customer_url'), '/');

        return [
            'id' => (int) $product->id,
            'name' => $this->safeProductText($product->name),
            'description' => $this->safeProductText($product->short_description),
            'brand' => $this->safeProductText($product->brand),
            'slug' => $product->slug,
            'url' => $customerUrl !== ''
                ? $customerUrl . '/products/' . rawurlencode($product->slug)
                : '/products/' . rawurlencode($product->slug),
            'image' => $image ? $this->productImageUrl($image->image_path) : null,
            'image_alt' => $this->safeProductText($image?->alt_text),
            'category' => $this->safeProductText($product->category?->name),
            'variants' => $variants,
            'availability' => $availability,
            'available' => match ($availability) {
                'in_stock', 'made_to_order' => true,
                'contact_store' => null,
                default => false,
            },
            'locale' => $locale,
        ];
    }

    private function isFinancialAttribute(string $name, string $value): bool
    {
        return preg_match(
            '/price|cost|discount|sale|offer|coupon|promotion|rebate|quote|savings?|قیمت|هزینه|تخفیف|فروش ویژه|حراج/iu',
            $name . ' ' . $value
        ) === 1
            || preg_match('/\b[0-9۰-۹٠-٩]+(?:[,.٫٬][0-9۰-۹٠-٩]+)?\s?%\s?(?:off|discount|sale)\b/iu', $value) === 1;
    }

    private function searchableVariantAttributes(Product $product): string
    {
        $attributes = [];

        foreach ($product->variants as $variant) {
            foreach ($variant->attributeValues as $attributeValue) {
                $name = $this->safeProductText($attributeValue->attribute?->name);
                $value = $this->safeProductText($attributeValue->value);

                if (
                    $name === null
                    || $value === null
                    || $this->isFinancialAttribute($name, $value)
                ) {
                    continue;
                }

                $attributes[] = $name;
                $attributes[] = $value;
            }
        }

        return implode(' ', $attributes);
    }

    private function productAvailability(Product $product): string
    {
        $activeVariants = $product->variants->filter(
            fn ($variant) => $variant->status === VariantStatus::ACTIVE
        );
        $madeToOrder = $product->variants->contains(
            fn ($variant) => $variant->status === VariantStatus::MADE_TO_ORDER
        );
        $availableUnits = $activeVariants->sum(
            fn ($variant) => $variant->inventory?->availableQuantity() ?? 0
        );

        if ((int) $product->variants_count > 0 && $product->variants->isEmpty()) {
            return 'unavailable';
        }

        if ($availableUnits > 0) {
            return 'in_stock';
        }

        if ($madeToOrder) {
            return 'made_to_order';
        }

        if ($activeVariants->isNotEmpty()) {
            return 'out_of_stock';
        }

        return 'contact_store';
    }

    private function availabilityRank(Product $product): int
    {
        return match ($this->productAvailability($product)) {
            'in_stock' => 0,
            'made_to_order' => 1,
            'contact_store' => 2,
            'out_of_stock' => 3,
            default => 4,
        };
    }

    /**
     * @param array<int, Product> $products
     * @return array{tool: string, context: array<string, mixed>, product_cards: array<int, array<string, mixed>>}
     */
    private function contextWithStores(string $tool, array $products, string $locale): array
    {
        $cards = array_map(fn (Product $product) => $this->productCard($product, $locale), $products);
        $stores = Store::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(20)
            ->get([
                'id', 'name', 'name_en', 'description', 'description_en',
                'address', 'address_en', 'city', 'city_en', 'floor_unit',
                'floor_unit_en', 'opening_hours', 'opening_hours_en',
                'phone', 'email', 'slug',
            ])
            ->map(fn (Store $store) => [
                'name' => $this->safePublicContactValue(
                    $locale === 'fa' ? $store->name : ($store->name_en ?? $store->name)
                ),
                'description' => $this->safeProductText($locale === 'fa'
                    ? $store->description
                    : ($store->description_en ?? $store->description)),
                'address' => $this->safeProductText($locale === 'fa'
                    ? $store->address
                    : ($store->address_en ?? $store->address)),
                'city' => $this->safePublicContactValue(
                    $locale === 'fa' ? $store->city : ($store->city_en ?? $store->city)
                ),
                'floor_unit' => $this->safePublicContactValue(
                    $locale === 'fa' ? $store->floor_unit : ($store->floor_unit_en ?? $store->floor_unit)
                ),
                'opening_hours' => $this->safeOpeningHours(
                    $locale === 'fa' ? $store->opening_hours : $store->opening_hours_en
                ),
                'phone' => $this->validatedPhoneNumber($store->phone),
                'email' => $this->validatedEmail($store->email),
                'url' => url('/stores/' . $store->slug),
            ])
            ->all();

        return [
            'tool' => $tool,
            'context' => [
                'tool' => $tool,
                'products' => $cards,
                'contact_channels' => $this->publicContactChannels($locale),
                'stores' => $stores,
            ],
            'product_cards' => $cards,
            'contact_links' => $this->contactLinks($locale, $stores),
            'contact_locations' => $stores,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $stores
     * @return array<int, array{kind: string, label: string, value: string|null, href: string}>
     */
    public function contactLinks(string $locale, array $stores = []): array
    {
        $links = [[
            'kind' => 'contact_page',
            'label' => $locale === 'fa' ? 'صفحه تماس با ما' : 'Contact page',
            'value' => null,
            'href' => '/contact?locale=' . $locale,
        ]];

        $contact = ContactPageContent::query()
            ->where('content_key', ContactPageContent::KEY)
            ->where('is_active', true)
            ->first([
                'phone_value', 'phone_url',
                'instagram_value', 'instagram_url',
                'telegram_value', 'telegram_url',
                'whatsapp_value', 'whatsapp_url',
            ]);

        if ($contact !== null) {
            $channels = [
                [
                    'kind' => 'phone',
                    'label' => $locale === 'fa' ? 'تلفن' : 'Phone',
                    'value' => $contact->phone_value,
                    'href' => $this->validatedPhoneHref($contact->phone_value, $contact->phone_url),
                ],
                [
                    'kind' => 'instagram',
                    'label' => 'Instagram',
                    'value' => $contact->instagram_value,
                    'href' => $this->validatedSocialHref('instagram', $contact->instagram_url),
                ],
                [
                    'kind' => 'telegram',
                    'label' => 'Telegram',
                    'value' => $contact->telegram_value,
                    'href' => $this->validatedSocialHref('telegram', $contact->telegram_url),
                ],
                [
                    'kind' => 'whatsapp',
                    'label' => 'WhatsApp',
                    'value' => $contact->whatsapp_value,
                    'href' => $this->validatedSocialHref('whatsapp', $contact->whatsapp_url),
                ],
            ];

            foreach ($channels as $channel) {
                if ($channel['href'] === null) {
                    continue;
                }

                $value = $this->safePublicContactValue($channel['value']);
                if ($value !== null && filter_var($value, FILTER_VALIDATE_URL) !== false) {
                    $value = null;
                }
                $links[] = [
                    ...$channel,
                    'value' => $value,
                ];
            }
        }

        foreach ($stores as $store) {
            if (! is_array($store)) {
                continue;
            }

            $name = $this->safePublicContactValue($store['name'] ?? null);
            $phone = $this->validatedPhoneNumber($store['phone'] ?? null);
            $email = $this->validatedEmail($store['email'] ?? null);
            foreach ([
                [
                    'kind' => 'phone',
                    'label' => ($name === null ? '' : $name . ' · ')
                        . ($locale === 'fa' ? 'تلفن' : 'Phone'),
                    'value' => $phone,
                    'href' => $phone === null ? null : 'tel:' . preg_replace('/[^+0-9]/', '', $phone),
                ],
                [
                    'kind' => 'email',
                    'label' => ($name === null ? '' : $name . ' · ')
                        . ($locale === 'fa' ? 'ایمیل' : 'Email'),
                    'value' => $email,
                    'href' => $email === null ? null : 'mailto:' . $email,
                ],
            ] as $channel) {
                if ($channel['href'] !== null) {
                    $links[] = $channel;
                }
            }
        }

        $unique = [];
        foreach ($links as $link) {
            if (! isset($unique[$link['href']])) {
                $unique[$link['href']] = $link;
            }
        }

        return array_values($unique);
    }

    /**
     * @return array<int, array{kind: string, label: string, value: string|null, href: string}>
     */
    private function publicContactChannels(string $locale): array
    {
        return array_slice($this->contactLinks($locale), 1);
    }

    private function validatedPhoneHref(mixed $value, mixed $publishedUrl): ?string
    {
        $phone = $this->validatedPhoneNumber($value);
        if ($phone === null) {
            return null;
        }

        if (is_string($publishedUrl) && str_starts_with(strtolower($publishedUrl), 'tel:')) {
            $publishedNumber = $this->validatedPhoneNumber(substr($publishedUrl, 4));
            if ($publishedNumber === null || preg_replace('/\D/', '', $publishedNumber) !== preg_replace('/\D/', '', $phone)) {
                return null;
            }
        }

        return 'tel:' . preg_replace('/[^+0-9]/', '', $phone);
    }

    private function validatedPhoneNumber(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $phone = trim($value);
        $digitCount = preg_match_all('/[0-9]/', $phone);
        if (
            preg_match('/^\+?[0-9][0-9().\s-]*[0-9]$/D', $phone) !== 1
            || ! is_int($digitCount)
            || $digitCount < 7
            || $digitCount > 20
        ) {
            return null;
        }

        return $phone;
    }

    private function validatedEmail(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $email = trim($value);

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    private function validatedSocialHref(string $kind, mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);
        if (preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            return null;
        }

        if ($kind === 'telegram' && preg_match('/^tg:\/\/resolve\?phone=\+?[0-9]{7,20}$/i', $url) === 1) {
            return $url;
        }

        $parts = parse_url($url);
        if (
            ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
        ) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        $valid = match ($kind) {
            'instagram' => in_array($host, ['instagram.com', 'www.instagram.com'], true)
                && preg_match('~^/[A-Za-z0-9._]+/?$~', $path) === 1,
            'telegram' => in_array($host, ['t.me', 'www.t.me'], true)
                && preg_match('~^/[A-Za-z0-9_+-]+/?$~', $path) === 1,
            'whatsapp' => in_array($host, ['wa.me', 'www.wa.me'], true)
                && preg_match('~^/[0-9]{7,20}/?$~', $path) === 1,
            default => false,
        };

        return $valid ? $url : null;
    }

    private function safePublicContactValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $safe = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', trim($value)) ?? '';

        return $safe === '' ? null : mb_substr($safe, 0, 200);
    }

    /**
     * @return array<int, string>
     */
    private function safeOpeningHours(mixed $value): array
    {
        $hours = is_array($value) ? $value : (is_string($value) ? [$value] : []);

        return array_values(array_filter(array_map(
            fn (mixed $item): ?string => $this->safePublicContactValue($item),
            $hours
        )));
    }

    private function safeProductText(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $sentences = preg_split('/(?<=[.!?؟])\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [$value];
        $safeSentences = [];

        foreach ($sentences as $sentence) {
            if (
                preg_match('/(?:price|cost|discount|sale|coupon|promotion|rebate|quote|special offer|promotional offer|قیمت|هزینه|تخفیف|فروش ویژه|حراج)/iu', $sentence) === 1
                || preg_match('/\b[0-9۰-۹٠-٩]+(?:[,.٫٬][0-9۰-۹٠-٩]+)?\s?%\s?(?:off|discount|sale)\b|\b(?:save|savings)\b.{0,40}[0-9۰-۹٠-٩]+(?:[,.٫٬][0-9۰-۹٠-٩]+)?\s?%/iu', $sentence) === 1
            ) {
                continue;
            }

            $sentence = preg_replace(
                '/(?:[$€£¥]\s*[0-9۰-۹٠-٩][0-9۰-۹٠-٩,.٫٬]*|[0-9۰-۹٠-٩][0-9۰-۹٠-٩,.٫٬]*\s*(?:toman|tomans|تومان|rial|rials|ریال|usd|eur|gbp|irr|aed|cad|aud|chf|sar|jpy|cny|kwd|try|rub|درهم))/iu',
                '',
                $sentence
            ) ?? $sentence;
            $safeSentences[] = trim($sentence);
        }

        $text = trim(implode(' ', array_filter($safeSentences)));

        return $text === '' ? null : $text;
    }

    /**
     * Resolve a brand already present in the public catalog.
     * Aliases never create brands or product facts: they only identify an
     * existing canonical brand which is then filtered at the database.
     */
    private function resolveRequestedBrand(string $normalized): ?string
    {
        $aliases = [
            'زویلینگ' => 'zwilling',
            'زوییلینگ' => 'zwilling',
            'زوئيلينگ' => 'zwilling',
            'زوئیلینگ' => 'zwilling',
        ];
        $intent = $normalized;
        foreach ($aliases as $alias => $latin) {
            $intent = str_replace(SearchTextNormalizer::normalize($alias), $latin, $intent);
        }

        // A bounded brand vocabulary is sourced from active catalog data.
        $brands = Product::query()
            ->where('status', ProductStatus::ACTIVE->value)
            ->whereNotNull('brand')
            ->distinct()
            ->limit(500)
            ->pluck('brand');

        foreach ($brands as $brand) {
            $name = SearchTextNormalizer::normalize((string) $brand);
            if ($name !== '' && mb_strlen($name) >= 3
                && preg_match('/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?![\p{L}\p{N}])/u', $intent) === 1) {
                return (string) $brand;
            }
            // Canonical brands such as "Zwilling J.A. Henckels" may be
            // requested by their distinctive first word.
            $first = explode(' ', $name)[0] ?? '';
            if (mb_strlen($first) >= 5
                && preg_match('/(?<![\p{L}\p{N}])'.preg_quote($first, '/').'(?![\p{L}\p{N}])/u', $intent) === 1) {
                return (string) $brand;
            }
        }

        return null;
    }

    private function isShoppingRequest(string $normalized): bool
    {
        return $this->containsAny($normalized, [
            'product', 'products', 'buy', 'purchase', 'shopping', 'recommend', 'suggest',
            'link', 'url', 'website', 'page', 'browse', 'details', 'specifications',
            'کتابچه', 'لینک', 'لینکش', 'آدرس محصول', 'صفحه محصول', 'مشخصات',
            'find', 'search', 'looking for', 'have', 'choose', 'choosing', 'help me choose',
            'compare', 'comparison', 'versus', 'vs', 'which is better', 'which one',
            'suitability', 'suitable', 'suit', 'fits', 'price', 'cost', 'pricing',
            'discount', 'sale', 'offer', 'quote', 'how much', 'stock', 'available',
            'availability', 'catalog', 'کالا', 'محصول', 'خرید', 'پیشنهاد', 'جستجو',
            'پیدا', 'قیمت', 'هزینه', 'تخفیف', 'فروش ویژه', 'حراج', 'موجود',
            'ناموجود', 'فروش', 'دارید', 'میخوام', 'می خواهم',
            'cookware', 'pot', 'pots', 'saucepan', 'saucepans', 'pan', 'pans',
            'wok', 'قابلمه', 'دیگ', 'تابه', 'ماهیتابه', 'ظروف پخت',
        ]);
    }

    private function isContactRequest(string $normalized): bool
    {
        return $this->containsAny($normalized, [
            'contact', 'phone', 'telephone', 'email', 'address', 'location',
            'branch', 'branches', 'instagram', 'telegram', 'whatsapp', 'messaging',
            'call the store', 'reach the store', 'contact details', 'contact information',
            'store hours', 'opening hours', 'business hours',
            'شماره تماس', 'شماره تلفن', 'تلفن', 'ایمیل', 'آدرس', 'نشانی',
            'موقعیت', 'شعبه', 'شعبه ها', 'تماس', 'ارتباط', 'واتساپ', 'تلگرام',
            'اینستاگرام', 'پیام رسان', 'ساعت کاری', 'ساعت فعالیت',
        ]);
    }

    private function requestsProductHelp(string $normalized): bool
    {
        return $this->containsAny($normalized, [
            'recommend', 'suggest', 'find', 'search', 'looking for', 'show me',
            'compare', 'comparison', 'versus', 'which one', 'which is better',
            'difference', 'differences', 'available options', 'in stock',
            'options', 'option', 'which', 'do you have', 'have you got',
            'پیشنهاد', 'معرفی', 'پیدا', 'دنبال', 'مقایسه', 'تفاوت',
            'چه گزینه', 'کدام بهتر', 'کدوم بهتر', 'موجود', 'گزینه',
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function cookwareCategoryScope(string $normalized): array
    {
        if (! $this->containsAny($normalized, [
            'cookware', 'cooking pots', 'pots and pans', 'pot', 'pots',
            'saucepan', 'saucepans', 'frying pan', 'frying pans', 'pan', 'pans',
            'wok', 'قابلمه', 'دیگ', 'تابه', 'ماهیتابه', 'ظروف پخت', 'ظرف پخت',
        ])) {
            return [];
        }

        $categories = Category::query()
            ->where('is_active', true)
            ->get(['id', 'parent_id', 'name', 'slug']);
        $root = $categories->first(function (Category $category): bool {
            if ($category->parent_id !== null) {
                return false;
            }

            $names = $this->localizedCategoryNames($category);

            return in_array('cookware', $names, true)
                || in_array('ظروف پخت', $names, true)
                || in_array('ظروف پخت و پز', $names, true);
        });

        if ($root === null) {
            return [];
        }

        $categoryIds = [$root->id];
        $parentIds = [$root->id];
        $includeGrills = $this->containsAny($normalized, ['grill', 'grills', 'گریل']);

        while ($parentIds !== []) {
            $children = $categories->whereIn('parent_id', $parentIds);
            $parentIds = [];
            foreach ($children as $child) {
                $names = $this->localizedCategoryNames($child);
                if (! $includeGrills && $this->containsAny(implode(' ', $names), ['grill', 'grills', 'گریل'])) {
                    continue;
                }

                $categoryIds[] = (int) $child->id;
                $parentIds[] = (int) $child->id;
            }
        }

        return array_values(array_unique($categoryIds));
    }

    /**
     * @return array<int, string>
     */
    private function localizedCategoryNames(Category $category): array
    {
        $rawName = $category->getRawOriginal('name');
        $translations = is_string($rawName) ? json_decode($rawName, true) : null;
        $names = is_array($translations) ? array_values($translations) : [(string) $category->name];
        $names[] = (string) $category->slug;

        return array_values(array_filter(array_map(
            static fn (mixed $name): string => is_string($name)
                ? SearchTextNormalizer::normalize($name)
                : '',
            $names
        )));
    }

    /**
     * @return array<int, int>
     */
    private function categoryFamilyIds(int $categoryId): array
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->get(['id', 'parent_id'])
            ->keyBy('id');
        $category = $categories->get($categoryId);
        if ($category === null) {
            return [$categoryId];
        }

        while ($category->parent_id !== null) {
            $parent = $categories->get($category->parent_id);
            if ($parent === null) {
                break;
            }
            $category = $parent;
        }

        $familyIds = [(int) $category->id];
        $parentIds = $familyIds;
        while ($parentIds !== []) {
            $children = $categories->whereIn('parent_id', $parentIds);
            $parentIds = [];
            foreach ($children as $child) {
                $id = (int) $child->id;
                if (! in_array($id, $familyIds, true)) {
                    $familyIds[] = $id;
                    $parentIds[] = $id;
                }
            }
        }

        return $familyIds;
    }

    private function isBroadDiscoveryRequest(string $normalized): bool
    {
        return $this->containsAny($normalized, [
            'not decided what type', 'not sure what type', 'have not decided what type',
            'haven t decided what type', 'not sure where to start', 'help me narrow it down',
            'undecided what type', 'هنوز نمی دانم چه نوع', 'نمی دانم چه نوع',
        ]);
    }

    private function isAlternativeRequest(string $normalized): bool
    {
        return $this->containsAny($normalized, [
            'alternative', 'instead', 'replacement', 'similar product', 'similar option',
            'another option', 'جایگزین', 'مشابه', 'گزینه مشابه', 'گزینه جایگزین',
        ]);
    }

    private function productNameAppearsInMessage(Product $product, string $normalized): bool
    {
        $rawName = json_decode((string) $product->getRawOriginal('name'), true);
        $names = is_array($rawName) ? array_values($rawName) : [];
        $names[] = $product->slug;

        foreach ($names as $name) {
            if (! is_string($name)) {
                continue;
            }
            $candidate = SearchTextNormalizer::normalize($name);
            if (mb_strlen($candidate) >= 5 && str_contains($normalized, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function isPurchaseGuidanceRequest(string $normalized): bool
    {
        return $this->containsAny($normalized, [
            'how do i buy', 'how can i buy', 'how should i buy', 'how to buy', 'how do i order',
            'how can i order', 'how to order', 'place an order', 'purchase process',
            'how do i purchase', 'how can i purchase', 'how should i purchase', 'how to purchase',
            'purchase this product', 'checkout', 'چطور بخرم', 'چگونه بخرم',
            'چطور سفارش', 'چگونه سفارش', 'روش خرید', 'نحوه خرید', 'ثبت سفارش',
            'سفارش بدهم', 'خرید را چطور', 'خرید رو چطور',
            'want to order', 'would like to order', 'place an order', 'place order',
            'مراحل خرید', 'ادامه مراحل خرید', 'راهنمایی خرید', 'راهنمایی برای خرید',
        ]);
    }

    private function requestsAvailableRecommendations(string $normalized): bool
    {
        $isRecommendationRequest = $this->containsAny($normalized, [
            'recommend', 'suggest', 'looking for', 'options', 'option',
            'پیشنهاد', 'گزینه', 'دنبال',
        ]);

        $isAvailabilityRequest = preg_match(
            '/(?<![\p{L}\p{N}])(?:in stock|available|availability)(?![\p{L}\p{N}])/u',
            $normalized
        ) === 1 || $this->containsAny($normalized, ['موجود', 'موجودی']);

        return $isRecommendationRequest && $isAvailabilityRequest;
    }

    private function isPriceRequest(string $normalized): bool
    {
        return $this->containsAny($normalized, [
            'price', 'cost', 'pricing', 'discount', 'sale', 'coupon',
            'promotion', 'rebate', 'quote', 'how much', 'special offer',
            'promotional offer', 'current offers', 'good deal', 'best deal',
            'قیمت', 'چنده', 'هزینه',
            'تخفیف', 'فروش ویژه', 'حراج', 'مبلغ', 'آفر',
        ]);
    }

    private function referencesPreviousProduct(string $normalized): bool
    {
        return $this->containsAny($normalized, [
            'this product', 'that product', 'same product', 'previous option',
            'why recommend', 'why did you', 'it is suitable', 'this one',
            'this model', 'that model', 'this option', 'that option', 'would this',
            'would it', 'does it', 'will it', 'is it', 'has it', 'its ', 'is it suitable', 'is this suitable',
            'compare these', 'compare them', 'which one', 'which is better',
            'what about that', 'between them', 'the other option', 'which would you', 'which one would you', 'for a beginner', 'for beginners',
            'send me the link', 'give me the link', 'its link', 'product link',
            'link to it', 'link for it', 'that one', 'the first', 'the second',
            'the third', 'first one', 'second one', 'third one',
            'لینکش', 'لینک محصول', 'لینک بده', 'لینک بدهید',
            'لینک اون', 'لینک این', 'اولی', 'دومی', 'سومی',
            'همین محصول', 'این محصول', 'همون محصول', 'همان محصول', 'گزینه قبلی',
            'چرا پیشنهاد', 'چرا گزینه', 'چرا این', 'این گزینه', 'همین گزینه',
            'بین این دو', 'کدام بهتر', 'کدوم بهتر', 'مقایسه این', 'مقایسه شون',
            'آن یکی', 'اون یکی', 'گزینه دیگر', 'گزینه دیگه', 'برای مبتدی', 'کدومو پیشنهاد',
        ]);
    }

    private function mentionsPreviousProduct(string $normalized, array $previousProductIds): bool
    {
        $products = Product::query()
            ->whereKey(array_slice($previousProductIds, 0, 5))
            ->where('status', ProductStatus::ACTIVE->value)
            ->get(['name', 'brand', 'slug']);
        $normalizedMessage = ' ' . $normalized . ' ';

        foreach ($products as $product) {
            $rawNames = json_decode((string) $product->getRawOriginal('name'), true);
            $candidates = [
                ...array_values(is_array($rawNames) ? $rawNames : []),
                $product->brand,
                $product->slug,
            ];

            foreach ($candidates as $candidate) {
                if (! is_string($candidate) || trim($candidate) === '') {
                    continue;
                }

                $normalizedCandidate = SearchTextNormalizer::normalize($candidate);
                if ($normalizedCandidate === '') {
                    continue;
                }

                if (str_contains($normalizedMessage, ' ' . $normalizedCandidate . ' ')) {
                    return true;
                }

                foreach (explode(' ', $normalizedCandidate) as $token) {
                    if (
                        mb_strlen($token) >= 5
                        && str_contains($normalizedMessage, ' ' . $token . ' ')
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function searchTerms(string $normalized): string
    {
        $stopWords = [
            'product', 'products', 'buy', 'purchase', 'shopping', 'recommend', 'suggest',
            'find', 'search', 'looking', 'for', 'have', 'you', 'do', 'the', 'a', 'an',
            'link', 'url', 'website', 'page', 'send', 'give', 'its', 'one',
            'first', 'second', 'third', 'direct', 'address', 'لینک', 'لینکش', 'صفحه', 'آدرس', 'اولی', 'دومی', 'سومی',
            'price', 'cost', 'stock', 'available', 'availability', 'catalog', 'please',
            'compare', 'comparison', 'versus', 'vs', 'which', 'better', 'suitable',
            'suitability', 'suit', 'fits', 'fit', 'concerned', 'concern', 'model',
            'choosing', 'choose', 'help', 'undecided', 'household',
            'to', 'there', 'am', 'verified', 'alternative', 'everyday', 'daily',
            'cooking', 'meal', 'meals', 'differences', 'difference', 'supported',
            'support', 'by', 'set', 'sets', 'cookware',
            'show', 'me', 'what', 'is', 'are', 'in', 'of', 'any', 'can', 'i', 'want',
            'need', 'family', 'two', 'person', 'people', 'household', 'suitable',
            'recommend', 'suggest', 'best', 'store', 'between', 'from', 'with', 'for',
            'کالا', 'محصول', 'محصولات',
            'خرید', 'پیشنهاد', 'جستجو', 'پیدا',
            'قیمت', 'موجود', 'ناموجود', 'فروش', 'دارید', 'لطفا', 'لطفاً', 'میخوام',
            'می', 'خواهم', 'خانواده', 'دو', 'نفره', 'مناسب', 'بهترین', 'برای',
            'چی', 'چه', 'را', 'یک', 'یه', 'من', 'به', 'از',
            'بین', 'های', 'ها', 'در', 'با', 'این', 'آن', 'که', 'هم',
            'دسته', 'بندی', 'فروشگاه', 'شعبه', 'آدرس', 'نشانی', 'ساعت', 'کاری',
            'how', 'order', 'process', 'contact', 'store', 'this', 'that', 'to',
            'purchase', 'checkout', 'continue', 'step', 'steps',
            'and', 'number', 'phone', 'telephone', 'email', 'your',
            'ادامه', 'مراحل', 'راهنمایی', 'کنید', 'کنم', 'می‌کنید', 'میکنید',
            'تماس', 'ارتباط', 'فروشگاه', 'سفارش', 'ثبت', 'بخرم', 'خرید',
            'انتخاب', 'و', 'ام', 'شماره', 'تلفن', 'ایمیل',
            'آشپزی', 'روزمره', 'دنبال', 'گزینه', 'گزینه‌ها', 'گزینهها',
            'می', 'کنید', 'میکنید', 'برای',
        ];

        $terms = collect(explode(' ', $normalized))
            ->reject(fn (string $term) => in_array($term, $stopWords, true))
            ->filter(fn (string $term) => mb_strlen($term) >= 2 && ! ctype_digit($term))
            ->values();

        if ($this->containsAny($normalized, [
            'cookware', 'cooking pots', 'pots and pans', 'pot', 'pots',
            'saucepan', 'saucepans', 'frying pan', 'frying pans', 'pan', 'pans',
            'wok', 'قابلمه', 'دیگ', 'تابه', 'ماهیتابه', 'ظروف پخت', 'ظرف پخت',
        ])) {
            $terms = $terms->merge([
                'cookware', 'pot', 'pots', 'pan', 'pans', 'saucepan', 'wok',
                'قابلمه', 'تابه', 'ماهیتابه',
            ]);
        }

        return $terms->unique()->take(12)->implode(' ');
    }

    private function productIdentifier(string $message): ?string
    {
        if (preg_match('/(?:product|item)\s*#?\s*(\d+)/iu', $message, $matches)) {
            return $matches[1];
        }

        if (preg_match('/محصول(?:\s+شماره)?\s*(\d+)/u', $message, $matches)) {
            return $matches[1];
        }

        if (preg_match('~(?:/products?/|محصول:)([a-z0-9]+(?:-[a-z0-9]+)*)~iu', $message, $matches)) {
            return strtolower($matches[1]);
        }

        return null;
    }

    private function containsAny(string $text, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (str_contains($text, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function productImageUrl(string $path): string
    {
        return str_starts_with($path, '/storage/')
            ? url($path)
            : Storage::disk('public')->url($path);
    }
}
