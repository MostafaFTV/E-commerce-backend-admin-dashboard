<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequestOtpRequest;
use App\Http\Requests\Auth\ConfirmChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequestOtpRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyRegistrationOtpRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\CartService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    */

    /**
     * Validate registration data and send OTP.
     */
    public function requestRegistrationOtp(
        RegisterRequest $request
    ) {
        $result =
            $this->authService
                ->requestRegistrationOtp(
                    $request->validated()
                );

        return response()->json([
            'message' =>
                'Verification code sent successfully.',

            'expires_in' =>
                $result['expires_in'],

            'retry_after' =>
                $result['retry_after'],
        ]);
    }

    /**
     * Verify registration OTP and create user.
     */
    public function verifyRegistrationOtp(
        VerifyRegistrationOtpRequest $request
    ) {
        $result =
            $this->authService
                ->verifyRegistrationOtp(
                    $request->input('mobile'),
                    $request->input('code')
                );

        return response()->json([
            'message' =>
                'Registered successfully.',

            'user' =>
                new UserResource(
                    $result['user']
                ),

            'token' =>
                $result['token'],
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    */

    public function login(
        LoginRequest $request
    ) {
        $result =
            $this->authService->login(
                $request->identifier,
                $request->password,
                $request->header(
                    CartService::GUEST_HEADER
                )
                ?: $request->cookie(
                    CartService::GUEST_COOKIE
                )
            );

        return response()->json([
            'message' =>
                'Login successful',

            'user' =>
                new UserResource(
                    $result['user']
                ),

            'token' =>
                $result['token'],
        ])
            ->withoutCookie(
                CartService::GUEST_COOKIE
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Login With OTP
    |--------------------------------------------------------------------------
    */

    public function requestLoginOtp(
        \App\Http\Requests\Auth\LoginRequestOtpRequest $request
    ) {
        $result = $this->authService->requestLoginOtp(
            $request->input('mobile')
        );

        return response()->json([
            'message' =>
                'If the account is eligible, a verification code has been sent.',

            'expires_in' =>
                $result['expires_in'],

            'retry_after' =>
                $result['retry_after'],
        ]);
    }

    public function verifyLoginOtp(
        \App\Http\Requests\Auth\VerifyLoginOtpRequest $request
    ) {
        $result = $this->authService->verifyLoginOtp(
            $request->input('mobile'),
            $request->input('code'),
            $request->header(
                CartService::GUEST_HEADER
            ) ?: $request->cookie(
                CartService::GUEST_COOKIE
            )
        );

        return response()->json([
            'message' =>
                'Login successful',

            'user' =>
                new UserResource(
                    $result['user']
                ),

            'token' =>
                $result['token'],
        ])->withoutCookie(
            CartService::GUEST_COOKIE
        );
    }
    /*
    |--------------------------------------------------------------------------
    | Forgot Password
    |--------------------------------------------------------------------------
    */

    /**
     * Request password-reset OTP.
     */
    public function requestForgotPasswordOtp(
        ForgotPasswordRequestOtpRequest $request
    ) {
        $result =
            $this->authService
                ->requestForgotPasswordOtp(
                    $request->input('mobile')
                );

        return response()->json([
            'message' =>
                'If the account exists, a verification code has been sent.',

            'expires_in' =>
                $result['expires_in'],

            'retry_after' =>
                $result['retry_after'],
        ]);
    }

    /**
     * Reset password using OTP.
     */
    public function resetPassword(
        ResetPasswordRequest $request
    ) {
        $this->authService
            ->resetPassword(
                $request->input('mobile'),
                $request->input('code'),
                $request->input('password')
            );

        return response()->json([
            'message' =>
                'Password reset successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Change Password
    |--------------------------------------------------------------------------
    */

    /**
     * Request OTP for changing password.
     */
    public function requestChangePasswordOtp(
        ChangePasswordRequestOtpRequest $request
    ) {
        $result =
            $this->authService
                ->requestChangePasswordOtp(
                    $request->user(),
                    $request->input(
                        'current_password'
                    )
                );

        return response()->json([
            'message' =>
                'Verification code sent successfully.',

            'expires_in' =>
                $result['expires_in'],

            'retry_after' =>
                $result['retry_after'],
        ]);
    }

    /**
     * Confirm password change.
     */
    public function confirmChangePassword(
        ConfirmChangePasswordRequest $request
    ) {
        $this->authService
            ->confirmChangePassword(
                $request->user(),
                $request->input('code'),
                $request->input('password')
            );

        return response()->json([
            'message' =>
                'Password changed successfully. Please log in again.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    public function logout(
        Request $request
    ) {
        $this->authService->logout(
            $request->user()
        );

        return response()->json([
            'message' =>
                'Logged out successfully',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Current User
    |--------------------------------------------------------------------------
    */

    public function me(
        Request $request
    ) {
        $user = $request
            ->user()
            ->load('roles');

        return response()->json([
            'user' =>
                new UserResource($user),
        ]);
    }
}