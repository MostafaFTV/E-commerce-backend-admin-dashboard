<?php

namespace Tests\Feature;

use App\Services\ShoppingAssistant\ResearchFailureClassifier;
use Illuminate\Http\Client\ConnectionException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ResearchFailureClassifierTest extends TestCase
{
    public static function failures(): array
    {
        return [
            ['Research provider failed: HTTP 401', 'provider_authentication'],
            ['Research provider failed: HTTP 429', 'provider_rate_limited'],
            ['Research provider failed: HTTP 500', 'provider_http_error'],
            ['Research response is incomplete.', 'provider_incomplete_response'],
            ['Research lacks completed search and cited evidence.', 'missing_search_or_citations'],
            ['Research provider is not configured.', 'provider_configuration'],
            ['Invalid manufacturer or model.', 'invalid_product_identity'],
            ['Connection timed out', 'network_timeout'],
            ['sensitive unexpected text', 'unexpected_research_error'],
        ];
    }

    #[DataProvider('failures')]
    public function test_failure_categories(
        string $message,
        string $expected
    ): void {
        $this->assertSame(
            $expected,
            ResearchFailureClassifier::classify(
                new RuntimeException($message)
            )
        );
    }

    public function test_connection_exception_is_classified(): void
    {
        $this->assertSame(
            'network_connection',
            ResearchFailureClassifier::classify(
                new ConnectionException('Connection refused')
            )
        );
    }
}