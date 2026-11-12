<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ContactMessageRateLimit
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $ipHash = hash(
            'sha256',
            (string) $request->ip()
        );

        $contact = mb_strtolower(
            trim(
                (string) $request->input(
                    'contact',
                    ''
                )
            )
        );

        $limits = [
            [
                'key' =>
                    'contact-message:ip-minute:'
                    . $ipHash,

                'attempts' => 5,

                'decay' => 60,
            ],

            [
                'key' =>
                    'contact-message:ip-hour:'
                    . $ipHash,

                'attempts' => 20,

                'decay' => 3600,
            ],
        ];

        if ($contact !== '') {
            $limits[] = [
                'key' =>
                    'contact-message:contact-hour:'
                    . hash(
                        'sha256',
                        $contact
                    ),

                'attempts' => 5,

                'decay' => 3600,
            ];
        }

        foreach ($limits as $limit) {
            if (
                RateLimiter::tooManyAttempts(
                    $limit['key'],
                    $limit['attempts']
                )
            ) {
                $retryAfter =
                    RateLimiter::availableIn(
                        $limit['key']
                    );

                return response()->json([
                    'message' =>
                        'Too many contact requests. Please try again later.',

                    'retry_after' =>
                        $retryAfter,
                ], 429)->header(
                    'Retry-After',
                    (string) $retryAfter
                );
            }
        }

        foreach ($limits as $limit) {
            RateLimiter::hit(
                $limit['key'],
                $limit['decay']
            );
        }

        return $next($request);
    }
}