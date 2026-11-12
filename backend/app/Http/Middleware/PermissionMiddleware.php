<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
        ...$permissions
    ): Response {
        $user = $request->user();

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

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'Forbidden: Missing permission',
            'errors' => [
                'authorization' => [
                    'Missing required permission.',
                ],
            ],
        ], 403);
    }
}