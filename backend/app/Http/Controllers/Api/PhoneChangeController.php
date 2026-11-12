<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PhoneChangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PhoneChangeController extends Controller
{
    public function __construct(private readonly PhoneChangeService $service) {}

    public function requestOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['new_phone' => ['required', 'string', 'max:40']]);
        $result = $this->service->request($request->user(), $data['new_phone']);

        return response()->json(['message' => 'Verification code sent.', 'data' => $result]);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['challenge_id' => ['required', 'string', 'size:48', 'regex:/^[a-f0-9]+$/']]);
        $result = $this->service->resend($request->user(), $data['challenge_id']);

        return response()->json(['message' => 'Verification code sent.', 'data' => $result]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_id' => ['required', 'string', 'size:48', 'regex:/^[a-f0-9]+$/'],
            'code' => ['required', 'digits:6'],
        ]);
        $user = $this->service->verify($request->user(), $data['challenge_id'], $data['code']);

        return response()->json([
            'message' => 'Phone number changed successfully.',
            'data' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status?->value,
                'phone_verified_at' => $user->phone_verified_at,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ]);
    }
}
