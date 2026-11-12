<?php

namespace Tests\Feature;

use Illuminate\Http\Middleware\TrustProxies as TrustProxiesMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')
            ->get(
                '/api/proxy-test',
                fn () => response()->json([
                    'scheme' => request()->getScheme(),
                    'secure' => request()->isSecure(),
                    'host' => request()->getHost(),
                    'ip' => request()->ip(),
                ])
            );
    }

    public function test_forwarded_https_is_ignored_when_proxy_is_not_trusted(): void
    {
        $response = $this
            ->withHeader(
                'X-Forwarded-Proto',
                'https'
            )
            ->getJson(
                '/api/proxy-test'
            );

        $response->assertOk();

        $response->assertJsonPath(
            'scheme',
            'http'
        );

        $response->assertJsonPath(
            'secure',
            false
        );
    }

    public function test_forwarded_host_is_ignored_when_proxy_is_not_trusted(): void
    {
        $baseline = $this
            ->getJson(
                '/api/proxy-test'
            );

        $baseline->assertOk();

        $response = $this
            ->withHeader(
                'X-Forwarded-Host',
                'attacker.example'
            )
            ->getJson(
                '/api/proxy-test'
            );

        $response->assertOk();

        $this->assertSame(
            $baseline->json('host'),
            $response->json('host')
        );

        $this->assertNotSame(
            'attacker.example',
            $response->json('host')
        );
    }

    public function test_forwarded_for_cannot_replace_client_ip_when_proxy_is_not_trusted(): void
    {
        $response = $this
            ->withHeader(
                'X-Forwarded-For',
                '203.0.113.50'
            )
            ->getJson(
                '/api/proxy-test'
            );

        $response->assertOk();

        $response->assertJsonPath(
            'ip',
            '127.0.0.1'
        );
    }

    public function test_forwarded_for_is_used_when_proxy_is_explicitly_trusted(): void
    {
        $headers =
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO;

        $configuredProxies =
            array_values(
                array_filter(
                    array_map(
                        'trim',
                        explode(
                            ',',
                            (string) env(
                                'TKB_TRUSTED_PROXIES',
                                ''
                            )
                        )
                    )
                )
            );

        try {
            TrustProxiesMiddleware::at([
                '10.0.0.10',
            ]);

            TrustProxiesMiddleware::withHeaders(
                $headers
            );

            $response = $this
                ->withServerVariables([
                    'REMOTE_ADDR' =>
                        '10.0.0.10',
                ])
                ->withHeader(
                    'X-Forwarded-For',
                    '203.0.113.50'
                )
                ->getJson(
                    '/api/proxy-test'
                );

            $response->assertOk();

            $response->assertJsonPath(
                'ip',
                '203.0.113.50'
            );
        } finally {
            TrustProxiesMiddleware::flushState();

            TrustProxiesMiddleware::at(
                $configuredProxies
            );

            TrustProxiesMiddleware::withHeaders(
                $headers
            );
        }
    }

    public function test_forwarded_for_resolves_original_client_through_trusted_proxy_chain(): void
    {
        $headers =
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO;

        $configuredProxies =
            array_values(
                array_filter(
                    array_map(
                        'trim',
                        explode(
                            ',',
                            (string) env(
                                'TKB_TRUSTED_PROXIES',
                                ''
                            )
                        )
                    )
                )
            );

        try {
            /*
             * Request path:
             *
             * Client      203.0.113.50
             * Load Balancer 10.0.0.10
             * Nginx         10.0.0.20
             * Laravel
             */
            TrustProxiesMiddleware::at([
                '10.0.0.10',
                '10.0.0.20',
            ]);

            TrustProxiesMiddleware::withHeaders(
                $headers
            );

            $response = $this
                ->withServerVariables([
                    'REMOTE_ADDR' =>
                        '10.0.0.20',
                ])
                ->withHeader(
                    'X-Forwarded-For',
                    '203.0.113.50, 10.0.0.10'
                )
                ->getJson(
                    '/api/proxy-test'
                );

            $response->assertOk();

            $response->assertJsonPath(
                'ip',
                '203.0.113.50'
            );
        } finally {
            TrustProxiesMiddleware::flushState();

            TrustProxiesMiddleware::at(
                $configuredProxies
            );

            TrustProxiesMiddleware::withHeaders(
                $headers
            );
        }
    }}