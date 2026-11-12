<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ConfirmChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'digits:6',
            ],

            'password' => [
                'required',
                'string',
                Password::min(8),
                'confirmed',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' =>
                'Verification code is required.',

            'code.digits' =>
                'Verification code must be 6 digits.',

            'password.required' =>
                'New password is required.',

            'password.confirmed' =>
                'Password confirmation does not match.',
        ];
    }
}