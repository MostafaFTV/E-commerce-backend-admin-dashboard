<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AdminUserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    */

    public function login(
        LoginRequest $request
    ): JsonResponse {
        $result =
            $this->authService->adminLogin(
                $request->identifier,
                $request->password
            );

        return response()->json([
            'message' =>
                'Login successful',

            'user' =>
                new AdminUserResource(
                    $result['user']
                ),

            'token' =>
                $result['token'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Current Admin
    |--------------------------------------------------------------------------
    */

    public function me(
        Request $request
    ): JsonResponse {
        $user = $request
            ->user()
            ->load(
                'roles.permissions'
            );

        return response()->json([
            'user' =>
                new AdminUserResource(
                    $user
                ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    public function logout(
        Request $request
    ): JsonResponse {
        $this->authService->logout(
            $request->user()
        );

        return response()->json([
            'message' =>
                'Logged out successfully',
        ]);
    }
}
