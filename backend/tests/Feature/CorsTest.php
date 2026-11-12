<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CorsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')
            ->get(
                '/api/cors-test',
                fn () => response()->json([
                    'message' => 'ok',
                ])
            );
    }

    public function test_allowed_frontend_origin_receives_cors_header(): void
    {
        $response = $this
            ->withHeader(
                'Origin',
                'http://localhost:3000'
            )
            ->getJson('/api/cors-test');

        $response->assertOk();

        $response->assertHeader(
            'Access-Control-Allow-Origin',
            'http://localhost:3000'
        );
    }

    public function test_second_allowed_frontend_origin_receives_cors_header(): void
    {
        $response = $this
            ->withHeader(
                'Origin',
                'http://127.0.0.1:3000'
            )
            ->getJson('/api/cors-test');

        $response->assertOk();

        $response->assertHeader(
            'Access-Control-Allow-Origin',
            'http://127.0.0.1:3000'
        );
    }

    public function test_unknown_origin_is_not_allowed(): void
    {
        $response = $this
            ->withHeader(
                'Origin',
                'https://attacker.example'
            )
            ->getJson('/api/cors-test');

        $response->assertOk();

        $this->assertFalse(
            $response->headers->has(
                'Access-Control-Allow-Origin'
            )
        );
    }

    public function test_preflight_request_is_allowed_for_configured_origin(): void
    {
        $response = $this
            ->withHeaders([
                'Origin' =>
                    'http://localhost:3000',

                'Access-Control-Request-Method' =>
                    'POST',

                'Access-Control-Request-Headers' =>
                    'Authorization, Content-Type',
            ])
            ->options('/api/cors-test');

        $response->assertNoContent();

        $response->assertHeader(
            'Access-Control-Allow-Origin',
            'http://localhost:3000'
        );

        $response->assertHeader(
            'Access-Control-Allow-Methods',
            'GET, POST, PUT, PATCH, DELETE, OPTIONS'
        );

        $response->assertHeader(
            'Access-Control-Allow-Headers',
            'Accept, Authorization, Content-Type, Origin, X-Requested-With'
        );
    }

    public function test_cors_allows_credentials_for_guest_cart_cookie(): void
    {
        $response = $this
            ->withHeader(
                'Origin',
                'http://localhost:3000'
            )
            ->getJson('/api/cors-test');

        $response->assertOk();

        $response->assertHeader(
            'Access-Control-Allow-Credentials',
            'true'
        );
    }
}