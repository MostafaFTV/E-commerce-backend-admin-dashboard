<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedHostsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')
            ->get(
                '/api/host-test',
                fn () => response()->json([
                    'host' => request()->getHost(),
                    'scheme' => request()->getScheme(),
                ])
            );
    }

    public function test_allowed_localhost_is_accepted(): void
    {
        $response = $this->getJson(
            'http://localhost/api/host-test'
        );

        $response->assertOk();

        $response->assertJsonPath(
            'host',
            'localhost'
        );
    }

    public function test_allowed_loopback_host_is_accepted(): void
    {
        $response = $this->getJson(
            'http://127.0.0.1/api/host-test'
        );

        $response->assertOk();

        $response->assertJsonPath(
            'host',
            '127.0.0.1'
        );
    }

    public function test_unit_test_environment_does_not_enforce_trusted_hosts(): void
    {
        $response = $this->getJson(
            'http://attacker.example/api/host-test'
        );

        /*
        |--------------------------------------------------------------------------
        | Laravel behavior
        |--------------------------------------------------------------------------
        |
        | Laravel 13 intentionally skips trusted-host enforcement while
        | running unit tests. Production behavior is enforced by the
        | TrustHosts middleware configured in bootstrap/app.php.
        |
        */

        $response->assertOk();

        $response->assertJsonPath(
            'host',
            'attacker.example'
        );
    }
}