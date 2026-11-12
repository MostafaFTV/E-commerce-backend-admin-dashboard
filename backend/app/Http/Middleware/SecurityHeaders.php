<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Add baseline security headers to application responses.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $response = $next($request);

        /*
         * Prevent MIME-type sniffing.
         */
        $response->headers->set(
            'X-Content-Type-Options',
            'nosniff'
        );

        /*
         * Prevent the application from being embedded
         * in frames/iframes.
         */
        $response->headers->set(
            'X-Frame-Options',
            'DENY'
        );

        /*
         * Limit referrer information sent to other origins.
         */
        $response->headers->set(
            'Referrer-Policy',
            'strict-origin-when-cross-origin'
        );

        /*
         * Restrict browser capabilities that the application
         * does not currently need.
         */
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()'
        );

        /*
         * HSTS should only be sent over HTTPS.
         *
         * During local HTTP development, do not send HSTS.
         */
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}