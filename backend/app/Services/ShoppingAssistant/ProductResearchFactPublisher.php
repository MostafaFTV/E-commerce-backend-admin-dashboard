<?php

namespace App\Services\ShoppingAssistant;

use App\Models\Product;
use App\Models\ShoppingAssistantProductKnowledge;
use App\Support\SearchTextNormalizer;
use Illuminate\Support\Facades\Http;

/** Fail-closed: only text actually present on allowlisted manufacturer pages can be approved. */
final class ProductResearchFactPublisher
{
    public function publish(Product $product, array $research): array
    {
        $answer = $research['answer'] ?? null;
        $cited = $research['citation_urls'] ?? null;
        if (! is_string($answer) || ! is_array($cited) || ($research['verification_status'] ?? '') !== 'unverified') {
            return $this->reject('invalid_research');
        }

        // Product research model response must contain one standalone JSON object.
        // No parsing of arbitrary prose into verified facts.
        $candidate = json_decode(trim($answer), true);
        if (! is_array($candidate) || ! is_array($candidate['facts'] ?? null)) {
            return $this->reject('structured_facts_missing');
        }
        $brand = trim((string) $product->getRawOriginal('brand'));
        $model = trim((string) ($candidate['model'] ?? ''));
        if ($brand === '' || $model === '' || mb_strlen($model) > 80 || ! $this->isExactCatalogIdentity($product, $model)) {
            return $this->reject('exact_catalog_model_unconfirmed');
        }

        $domains = config('shopping_assistant_research.official_domains', []);
        $allowed = is_array($domains) ? ($domains[mb_strtolower($brand)] ?? []) : [];
        if (! is_array($allowed) || $allowed === []) {
            return $this->reject('manufacturer_domain_not_configured');
        }
        $cited = array_values(array_filter($cited, 'is_string'));
        $acceptedFacts = [];
        $sources = [];
        $visited = [];
        foreach (array_slice($candidate['facts'], 0, 12) as $fact) {
            if (! is_array($fact)) {
                continue;
            }
            $name = trim((string) ($fact['name'] ?? ''));
            $value = trim((string) ($fact['value'] ?? ''));
            $url = trim((string) ($fact['source_url'] ?? ''));
            if ($name === '' || $value === '' || mb_strlen($name) > 80 || mb_strlen($value) > 150
                || ! in_array($url, $cited, true) || ! $this->isApprovedUrl($url, $allowed)
                || $this->restricted($name.' '.$value)) {
                continue;
            }
            if (! array_key_exists($url, $visited)) {
                try {
                    $response = Http::timeout(12)->withoutRedirecting()
                        ->withHeaders(['Accept' => 'text/html,text/plain'])
                        ->get($url);
                    $ctype = strtolower((string) $response->header('Content-Type'));
                    $visited[$url] = $response->successful()
                        && (str_contains($ctype, 'text/html') || str_contains($ctype, 'text/plain'))
                        && strlen($response->body()) <= 800000
                        ? html_entity_decode(strip_tags($response->body()), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                        : '';
                } catch (\Throwable) {
                    $visited[$url] = '';
                }
            }
            $page = $visited[$url];
            // Both exact model and literal fact VALUE must be visible on this source page.
            if (! $this->sameSourceContext($page, $model, $value)) {
                continue;
            }
            $acceptedFacts[] = [
                'name' => $name, 'value' => $value, 'source_url' => $url,
                'review_status' => 'approved',
            ];
            $sources[$url] = [
                'url' => $url, 'source_type' => 'manufacturer_documentation',
                'review_status' => 'approved',
            ];
        }
        if ($acceptedFacts === []) {
            return $this->reject('no_independently_confirmed_facts');
        }

        // Fail closed if product changes during research or if a record is already curated.
        $fresh = Product::query()->find($product->id);
        if (! $fresh || ! $fresh->updated_at?->equalTo($product->updated_at)
            || ! $this->isExactCatalogIdentity($fresh, $model)
            || ShoppingAssistantProductKnowledge::query()->where('product_id', $product->id)->exists()) {
            return $this->reject('product_changed_or_knowledge_exists');
        }
        ShoppingAssistantProductKnowledge::query()->create([
            'product_id' => $product->id,
            'manufacturer' => $brand,
            'model' => $model,
            'facts' => $acceptedFacts,
            'sources' => array_values($sources),
            'review_status' => 'approved',
            'verified_at' => now(),
        ]);
        return ['approved' => true, 'model' => $model, 'fact_count' => count($acceptedFacts)];
    }

    private function isExactCatalogIdentity(Product $product, string $model): bool
    {
        $names = $product->getRawOriginal('name');
        $translations = is_string($names) ? json_decode($names, true) : null;
        $labels = is_array($translations) ? array_values($translations) : [$names];
        $labels[] = $product->slug;
        foreach ($labels as $label) {
            if (is_string($label) && $this->tokenMatch($label, $model)) {
                return true;
            }
        }
        return false;
    }

    private function tokenMatch(string $haystack, string $needle): bool
    {
        $text = SearchTextNormalizer::normalize($haystack);
        $word = SearchTextNormalizer::normalize($needle);
        if ($word === '' || mb_strlen($word) < 3) {
            return false;
        }
        return preg_match('/(?<![\p{L}\p{N}])'.preg_quote($word, '/').'(?![\p{L}\p{N}])/u', $text) === 1;
    }

    private function sameSourceContext(string $page, string $model, string $value): bool
    {
        if (! $this->tokenMatch($page, $model) || ! $this->tokenMatch($page, $value)) {
            return false;
        }
        $normalized = SearchTextNormalizer::normalize($page);
        $model = SearchTextNormalizer::normalize($model);
        $value = SearchTextNormalizer::normalize($value);
        $modelAt = mb_strpos($normalized, $model);
        $valueAt = mb_strpos($normalized, $value);
        return $modelAt !== false && $valueAt !== false && abs($modelAt - $valueAt) <= 500;
    }

    private function isApprovedUrl(string $url, array $domains): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return false;
        }
        $parts = parse_url($url);
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || isset($parts['fragment'])) {
            return false;
        }
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }
        foreach ($domains as $domain) {
            if (! is_string($domain)) {
                continue;
            }
            $domain = strtolower(trim($domain));
            if ($domain !== '' && ($host === $domain || str_ends_with($host, '.'.$domain))) {
                return true;
            }
        }
        return false;
    }

    private function restricted(string $text): bool
    {
        return preg_match('/\b(?:price|cost|discount|sale|offer|stock|inventory|shipping|delivery|warranty|return|payment|availability)\b|قیمت|تخفیف|موجودی|ارسال|گارانتی|ضمانت|پرداخت|مرجوعی|[$€£¥]/iu', $text) === 1;
    }

    private function reject(string $reason): array
    {
        return ['approved' => false, 'reason' => $reason];
    }
}