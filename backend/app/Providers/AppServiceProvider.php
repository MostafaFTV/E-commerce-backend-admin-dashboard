<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Contracts\ShoppingAssistantProvider;
use App\Services\CartService;
use App\Services\Payments\FakePaymentGateway;
use App\Services\ShoppingAssistant\GapGptShoppingAssistantProvider;
use App\Services\ShoppingAssistant\MockShoppingAssistantProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Payment Gateway Binding
        |--------------------------------------------------------------------------
        |
        | TKB currently uses the fake payment gateway for development and
        | automated tests. The interface keeps PaymentService decoupled from
        | the concrete gateway implementation.
        |
        | Replace the implementation here when a real PSP is integrated.
        |
        |--------------------------------------------------------------------------
        */

        $this->app->bind(
            PaymentGatewayInterface::class,
            FakePaymentGateway::class
        );

        $this->app->bind(
            ShoppingAssistantProvider::class,
            function ($app): ShoppingAssistantProvider {
                return match (config('shopping_assistant.driver', 'mock')) {
                    'mock' => $app->make(MockShoppingAssistantProvider::class),
                    'gapgpt' => $app->make(GapGptShoppingAssistantProvider::class),
                    default => throw new \InvalidArgumentException(
                        'Unsupported SHOPPING_ASSISTANT_DRIVER value.'
                    ),
                };
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Login Rate Limiter
        |--------------------------------------------------------------------------
        |
        | Protect login from brute-force attacks.
        |
        | 10 requests per minute per IP
        | 5 requests per minute per identifier + IP
        |
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'login',
            function (Request $request) {
                $identifier = mb_strtolower(
                    trim(
                        (string) $request->input('identifier')
                    )
                );

                return [
                    Limit::perMinute(10)->by(
                        'login-ip:' . $request->ip()
                    ),

                    Limit::perMinute(5)->by(
                        'login-identifier-ip:'
                        . sha1(
                            $identifier
                            . '|'
                            . $request->ip()
                        )
                    ),
                ];
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Register Rate Limiter
        |--------------------------------------------------------------------------
        |
        | Prevent mass account creation from a single IP.
        |
        | 5 requests per minute per IP
        |
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'register',
            function (Request $request) {
                return Limit::perMinute(5)->by(
                    'register-ip:' . $request->ip()
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Shared OTP Sending Rate Limiter
        |--------------------------------------------------------------------------
        |
        | All three OTP send routes MUST use throttle:otp-send.
        | Laravel namespaces throttle cache keys by limiter name, so using
        | distinct names would bypass the intended shared IP allowance.
        |
        | 15 requests / 10 minutes / IP
        | 60 requests / hour / IP
        | 200 requests / day / IP
        |
        |--------------------------------------------------------------------------
        */

        RateLimiter::for('otp-send', function (Request $request) {
            $ip = (string) $request->ip();

            return [
                Limit::perMinutes(10, 15)->by('otp-send-ip-10m:' . $ip),
                Limit::perHour(60)->by('otp-send-ip-1h:' . $ip),
                Limit::perDay(200)->by('otp-send-ip-24h:' . $ip),
            ];
        });

        /*
        |--------------------------------------------------------------------------
        | Register OTP Verify Rate Limiter
        |--------------------------------------------------------------------------
        |
        | Protect OTP verification from brute-force attempts.
        |
        | 10 requests per minute per IP
        | 10 requests per minute per phone + IP
        |
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'otp-register-verify',
            function (Request $request) {
                $phone = trim(
                    (string) $request->input('mobile')
                );

                return [
                    Limit::perMinute(10)->by(
                        'otp-register-verify-ip:'
                        . $request->ip()
                    ),

                    Limit::perMinute(10)->by(
                        'otp-register-verify-phone-ip:'
                        . sha1(
                            $phone
                            . '|'
                            . $request->ip()
                        )
                    ),
                ];
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Forgot Password OTP Verify Rate Limiter
        |--------------------------------------------------------------------------
        |
        | Protect password reset verification from brute-force attempts.
        |
        | 10 requests per minute per IP
        | 10 requests per minute per phone + IP
        |
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'otp-forgot-password-verify',
            function (Request $request) {
                $phone = trim(
                    (string) $request->input('mobile')
                );

                return [
                    Limit::perMinute(10)->by(
                        'otp-forgot-password-verify-ip:'
                        . $request->ip()
                    ),

                    Limit::perMinute(10)->by(
                        'otp-forgot-password-verify-phone-ip:'
                        . sha1(
                            $phone
                            . '|'
                            . $request->ip()
                        )
                    ),
                ];
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Login OTP Verify Rate Limiter
        |--------------------------------------------------------------------------
        |
        | Maximum 10 requests per minute per IP and phone + IP.
        |
        */

        RateLimiter::for(
            'otp-login-verify',
            function (Request $request) {
                $phone = trim(
                    (string) $request->input('mobile')
                );

                return [
                    Limit::perMinute(10)->by(
                        'otp-login-verify-ip:'
                        . $request->ip()
                    ),

                    Limit::perMinute(10)->by(
                        'otp-login-verify-phone-ip:'
                        . sha1(
                            $phone
                            . '|'
                            . $request->ip()
                        )
                    ),
                ];
            }
        );
        /*
        |--------------------------------------------------------------------------
        | Change Password OTP Request Rate Limiter
        |--------------------------------------------------------------------------
        |
        | Only authenticated active users can reach this endpoint.
        |
        | Shared IP limiter: 15/10min, 60/hour, 200/day
        |
        |--------------------------------------------------------------------------
        */

        // Sending uses the shared OTP/IP limiter registered above.

        /*
        |--------------------------------------------------------------------------
        | Change Password OTP Verify Rate Limiter
        |--------------------------------------------------------------------------
        |
        | 10 requests per minute per user + IP
        |
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'otp-change-password-verify',
            function (Request $request) {
                $user = $request->user('sanctum');

                return Limit::perMinute(10)->by(
                    'otp-change-password-verify-user:'
                    . ($user?->id ?? 'guest')
                    . '|ip:'
                    . $request->ip()
                );
            }
        );

        RateLimiter::for('phone-change-verify', function (Request $request) {
            $user = $request->user('sanctum');
            return Limit::perMinute(10)->by(
                'phone-change-verify-user:' . ($user?->id ?? 'guest')
                . '|ip:' . $request->ip()
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Payment Callback Rate Limiter
        |--------------------------------------------------------------------------
        |
        | Payment callback must remain publicly accessible because
        | the payment gateway may call it without Sanctum authentication.
        |
        | 30 requests per minute per IP
        |
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'payment-callback',
            function (Request $request) {
                return Limit::perMinute(30)->by(
                    'payment-callback-ip:' . $request->ip()
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Cart Read Rate Limiter
        |--------------------------------------------------------------------------
        |
        | Cart can be accessed by both guests and authenticated users.
        |
        | Authenticated:
        |   user ID + IP
        |
        | Guest with token:
        |   guest token + IP
        |
        | Guest without token:
        |   IP fallback
        |
        | 60 requests per minute
        |
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'cart-read',
            function (Request $request) {
                $user = $request->user('sanctum');

                if ($user) {
                    return Limit::perMinute(60)->by(
                        'cart-read-user:'
                        . $user->id
                        . '|ip:'
                        . $request->ip()
                    );
                }

                $guestToken = $request->header(
                    CartService::GUEST_HEADER
                );

                if (
                    ! is_string($guestToken)
                    || trim($guestToken) === ''
                ) {
                    $guestToken = $request->cookie(
                        CartService::GUEST_COOKIE
                    );
                }

                if (
                    is_string($guestToken)
                    && trim($guestToken) !== ''
                ) {
                    return Limit::perMinute(60)->by(
                        'cart-read-guest:'
                        . hash(
                            'sha256',
                            $guestToken
                        )
                        . '|ip:'
                        . $request->ip()
                    );
                }

                return Limit::perMinute(60)->by(
                    'cart-read-ip:' . $request->ip()
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Cart Write Rate Limiter
        |--------------------------------------------------------------------------
        |
        | Applies to adding, updating and deleting cart items.
        |
        | Authenticated:
        |   user ID + IP
        |
        | Guest with token:
        |   guest token + IP
        |
        | Guest without token:
        |   IP fallback
        |
        | 30 requests per minute
        |
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'cart-write',
            function (Request $request) {
                $user = $request->user('sanctum');

                if ($user) {
                    return Limit::perMinute(30)->by(
                        'cart-write-user:'
                        . $user->id
                        . '|ip:'
                        . $request->ip()
                    );
                }

                $guestToken = $request->header(
                    CartService::GUEST_HEADER
                );

                if (
                    ! is_string($guestToken)
                    || trim($guestToken) === ''
                ) {
                    $guestToken = $request->cookie(
                        CartService::GUEST_COOKIE
                    );
                }

                if (
                    is_string($guestToken)
                    && trim($guestToken) !== ''
                ) {
                    return Limit::perMinute(30)->by(
                        'cart-write-guest:'
                        . hash(
                            'sha256',
                            $guestToken
                        )
                        . '|ip:'
                        . $request->ip()
                    );
                }

                return Limit::perMinute(30)->by(
                    'cart-write-ip:' . $request->ip()
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Wishlist Read Rate Limiter
        |--------------------------------------------------------------------------
        |
        | Wishlist is available only to authenticated active users.
        |
        | 60 requests per minute per user + IP
        |
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'wishlist-read',
            function (Request $request) {
                $user = $request->user('sanctum');

                return Limit::perMinute(60)->by(
                    'wishlist-read-user:'
                    . ($user?->id ?? 'guest')
                    . '|ip:'
                    . $request->ip()
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Wishlist Write Rate Limiter
        |--------------------------------------------------------------------------
        |
        | Applies to:
        | - adding wishlist items
        | - removing wishlist items
        | - clearing wishlist
        |
        | 30 requests per minute per user + IP
        |
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'wishlist-write',
            function (Request $request) {
                $user = $request->user('sanctum');

                return Limit::perMinute(30)->by(
                    'wishlist-write-user:'
                    . ($user?->id ?? 'guest')
                    . '|ip:'
                    . $request->ip()
                );
            }
        );

        RateLimiter::for('shopping-assistant', function (Request $request) {
            return [
                Limit::perMinute(8)->by('shopping-assistant-ip-minute:' . $request->ip()),
                Limit::perHour(50)->by('shopping-assistant-ip-hour:' . $request->ip()),
            ];
        });
    }
}
