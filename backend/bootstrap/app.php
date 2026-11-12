<?php

use App\Exceptions\InventoryConflictException;
use App\Exceptions\OrderActionForbiddenException;
use App\Exceptions\OrderStatusTransitionException;
use App\Exceptions\PaymentGatewayException;
use App\Exceptions\PaymentRefundException;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',

        api: __DIR__ . '/../routes/api.php',

        commands: __DIR__ . '/../routes/console.php',

        health: '/up',

        then: function () {
            Route::prefix('api')
                ->middleware('api')
                ->group(
                    base_path('routes/api/admin.php')
                );
        },
    )

    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->encryptCookies(except: [
            \App\Services\CartService::GUEST_COOKIE,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Trusted Hosts
        |--------------------------------------------------------------------------
        |
        | Only hosts explicitly listed in TKB_ALLOWED_HOSTS are accepted.
        |
        | Local:
        | TKB_ALLOWED_HOSTS=localhost,127.0.0.1
        |
        | Production example:
        | TKB_ALLOWED_HOSTS=example.com,www.example.com
        |
        | Host values are converted to exact regular expressions.
        |
        */

        $middleware->trustHosts(
            at: function (): array {
                $hosts = array_values(
                    array_filter(
                        array_map(
                            'trim',
                            explode(
                                ',',
                                env(
                                    'TKB_ALLOWED_HOSTS',
                                    'localhost,127.0.0.1'
                                )
                            )
                        )
                    )
                );

                return array_values(
                    array_map(
                        static fn (string $host): string =>
                            '^' . preg_quote(
                                $host,
                                '/'
                            ) . '$',
                        $hosts
                    )
                );
            },
            subdomains: false
        );

        /*
        |--------------------------------------------------------------------------
        | Trusted Proxies
        |--------------------------------------------------------------------------
        |
        | No proxy is trusted by default.
        |
        | Production example:
        |
        | TKB_TRUSTED_PROXIES=10.0.0.10,10.0.0.11
        |
        | CIDR ranges are also supported:
        |
        | TKB_TRUSTED_PROXIES=10.0.0.0/8
        |
        | Only explicitly configured proxies may influence
        | forwarded request information such as scheme, host,
        | port and client IP.
        |
        */

        $trustedProxies = array_values(
            array_filter(
                array_map(
                    'trim',
                    explode(
                        ',',
                        env(
                            'TKB_TRUSTED_PROXIES',
                            ''
                        )
                    )
                )
            )
        );

        $middleware->trustProxies(
            at: $trustedProxies,
            headers:
                Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO
        );

        /*
        |--------------------------------------------------------------------------
        | API Middleware
        |--------------------------------------------------------------------------
        */

        $middleware->appendToGroup(
            'api',
            SetLocale::class
        );

        $middleware->appendToGroup(
            'api',
            SecurityHeaders::class
        );

        $middleware->alias([
            'role' =>
                \App\Http\Middleware\RoleMiddleware::class,

            'permission' =>
                \App\Http\Middleware\PermissionMiddleware::class,

            'active' =>
                EnsureActiveUser::class,
        ]);

        $middleware->redirectGuestsTo(
            function (Request $request) {

                if ($request->is('api/*')) {
                    abort(
                        response()->json([
                            'message' =>
                                'Unauthenticated.',
                        ], 401)
                    );
                }

                return route('login');
            }
        );
    })

    ->withExceptions(function (Exceptions $exceptions): void {

        /*
        |--------------------------------------------------------------------------
        | JSON Rendering
        |--------------------------------------------------------------------------
        */

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) =>
                $request->is('api/*') ||
                $request->expectsJson()
        );

        /*
        |--------------------------------------------------------------------------
        | Order Status Transition
        |--------------------------------------------------------------------------
        */

        $exceptions->render(
            function (
                OrderStatusTransitionException $exception,
                Request $request
            ) {
                if (
                    ! $request->is('api/*') &&
                    ! $request->expectsJson()
                ) {
                    return null;
                }

                return response()->json([
                    'message' =>
                        'Invalid order status transition.',

                    'errors' => [
                        'status' => [
                            $exception->getMessage(),
                        ],
                    ],
                ], 422);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Order Action Authorization
        |--------------------------------------------------------------------------
        */

        $exceptions->render(
            function (
                OrderActionForbiddenException $exception,
                Request $request
            ) {
                if (
                    ! $request->is('api/*') &&
                    ! $request->expectsJson()
                ) {
                    return null;
                }

                return response()->json([
                    'message' =>
                        'You are not allowed to perform this action.',

                    'errors' => [
                        'order' => [
                            $exception->getMessage(),
                        ],
                    ],
                ], 403);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Inventory Conflict
        |--------------------------------------------------------------------------
        */

        $exceptions->render(
            function (
                InventoryConflictException $exception,
                Request $request
            ) {
                if (
                    ! $request->is('api/*') &&
                    ! $request->expectsJson()
                ) {
                    return null;
                }

                return response()->json([
                    'message' =>
                        'Inventory conflict.',

                    'errors' => [
                        'inventory' => [
                            $exception->getMessage(),
                        ],
                    ],
                ], 409);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Payment Gateway Failure
        |--------------------------------------------------------------------------
        |
        | Gateway creation failures are upstream dependency failures.
        | Do not expose raw gateway responses.
        |
        */

        $exceptions->render(
            function (
                PaymentGatewayException $exception,
                Request $request
            ) {
                if (
                    ! $request->is('api/*') &&
                    ! $request->expectsJson()
                ) {
                    return null;
                }

                return response()->json([
                    'message' =>
                        'Payment gateway error.',

                    'errors' => [
                        'payment' => [
                            $exception->getMessage(),
                        ],
                    ],
                ], 502);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Payment Refund Failure
        |--------------------------------------------------------------------------
        */

        $exceptions->render(
            function (
                PaymentRefundException $exception,
                Request $request
            ) {
                if (
                    ! $request->is('api/*') &&
                    ! $request->expectsJson()
                ) {
                    return null;
                }

                return response()->json([
                    'message' =>
                        'Payment refund failed.',

                    'errors' => [
                        'payment' => [
                            $exception->getMessage(),
                        ],
                    ],
                ], 502);
            }
        );
    })

    ->create();