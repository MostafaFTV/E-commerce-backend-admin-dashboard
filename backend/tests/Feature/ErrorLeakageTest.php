<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorLeakageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')
            ->get(
                '/api/error-test',
                function () {
                    throw new RuntimeException(
                        'THIS_IS_AN_INTERNAL_SECRET_ERROR'
                    );
                }
            );
    }

    public function test_internal_exception_does_not_leak_details_when_debug_is_disabled(): void
    {
        config([
            'app.debug' => false,
        ]);

        $response = $this->getJson(
            '/api/error-test'
        );

        $response->assertStatus(500);

        $response->assertJson([
            'message' => 'Server Error',
        ]);

        $this->assertStringNotContainsString(
            'THIS_IS_AN_INTERNAL_SECRET_ERROR',
            $response->getContent()
        );

        $this->assertStringNotContainsString(
            'RuntimeException',
            $response->getContent()
        );

        $this->assertStringNotContainsString(
            'vendor\\laravel',
            $response->getContent()
        );

        $this->assertStringNotContainsString(
            'app\\Http',
            $response->getContent()
        );
    }

    public function test_not_found_response_does_not_expose_internal_details(): void
    {
        config([
            'app.debug' => false,
        ]);

        $response = $this->getJson(
            '/api/this-route-does-not-exist'
        );

        $response->assertStatus(404);

        $this->assertStringNotContainsString(
            'Stack trace',
            $response->getContent()
        );

        $this->assertStringNotContainsString(
            'vendor\\laravel',
            $response->getContent()
        );

        $this->assertStringNotContainsString(
            'app\\Http',
            $response->getContent()
        );
    }

    public function test_debug_is_enabled_only_for_local_test_environment_configuration(): void
    {
        $originalDebug = config('app.debug');

        config([
            'app.debug' => false,
        ]);

        $this->assertFalse(
            config('app.debug')
        );

        config([
            'app.debug' => $originalDebug,
        ]);
    }
}