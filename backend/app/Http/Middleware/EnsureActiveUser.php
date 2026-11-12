<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Authentication Check
        |--------------------------------------------------------------------------
        */

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'errors' => [
                    'authentication' => [
                        'Authentication is required.',
                    ],
                ],
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Active Account Check
        |--------------------------------------------------------------------------
        */

        if ($user->status !== UserStatus::ACTIVE) {
            return response()->json([
                'message' => 'Account is not active.',
                'errors' => [
                    'authorization' => [
                        'Account is not active.',
                    ],
                ],
            ], 403);
        }

        return $next($request);
    }
}
