<?php

namespace App\Services\ShoppingAssistant;

use Throwable;

final class ResearchFailureClassifier
{
    public static function classify(Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (preg_match('/Research provider failed: HTTP ([0-9]{3})/', $message, $matches) === 1) {
            return match ((int) $matches[1]) {
                401, 403 => 'provider_authentication',
                429 => 'provider_rate_limited',
                408, 504 => 'provider_timeout',
                400, 404, 422 => 'provider_request_rejected',
                default => 'provider_http_error',
            };
        }

        if (str_contains($message, 'Research provider is not configured')) {
            return 'provider_configuration';
        }

        if (str_contains($message, 'Product research is disabled')) {
            return 'research_disabled';
        }

        if (str_contains($message, 'Invalid manufacturer or model')) {
            return 'invalid_product_identity';
        }

        if (str_contains($message, 'Research response is incomplete')) {
            return 'provider_incomplete_response';
        }

        if (str_contains($message, 'Research lacks completed search and cited evidence')) {
            return 'missing_search_or_citations';
        }

        if (str_contains(strtolower($message), 'timed out') ||
            str_contains(strtolower($message), 'timeout')) {
            return 'network_timeout';
        }

        if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
            return 'network_connection';
        }

        return 'unexpected_research_error';
    }

    /** Safe, non-sensitive stage suitable for persistence and monitoring. */
    public static function stage(\Throwable $exception): string
    {
        $category = self::classify($exception);
        return match ($category) {
            'provider_authentication', 'provider_rate_limited', 'provider_timeout',
            'provider_request_rejected', 'provider_http_error', 'provider_configuration',
            'network_timeout', 'network_connection' => 'provider_transport',
            'provider_incomplete_response', 'missing_search_or_citations' => 'provider_response',
            'invalid_product_identity' => 'product_identity',
            'research_disabled' => 'research_configuration',
            default => 'research_execution',
        };
    }
}