<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')
            ->get(
                '/security-test',
                fn () => response()->json([
                    'message' => 'ok',
                ])
            );
    }

    public function test_api_response_contains_baseline_security_headers(): void
    {
        $response = $this->getJson(
            '/security-test'
        );

        $response->assertOk();

        $response->assertHeader(
            'X-Content-Type-Options',
            'nosniff'
        );

        $response->assertHeader(
            'X-Frame-Options',
            'DENY'
        );

        $response->assertHeader(
            'Referrer-Policy',
            'strict-origin-when-cross-origin'
        );

        $response->assertHeader(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()'
        );
    }

    public function test_api_response_does_not_send_hsts_over_http(): void
    {
        $response = $this->getJson(
            '/security-test'
        );

        $response->assertOk();

        $this->assertFalse(
            $response->headers->has(
                'Strict-Transport-Security'
            )
        );
    }

    public function test_api_response_sends_hsts_over_https(): void
    {
        $response = $this->getJson(
            'https://localhost/security-test'
        );

        $response->assertOk();

        $response->assertHeader(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains'
        );
    }
}