<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mobile' => [
                'required',
                'string',
                'regex:/^09[0-9]{9}$/',
            ],

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
            'mobile.required' =>
                'Mobile number is required.',

            'mobile.regex' =>
                'Mobile number must be a valid Iranian mobile number.',

            'code.required' =>
                'Verification code is required.',

            'code.digits' =>
                'Verification code must be 6 digits.',

            'password.required' =>
                'Password is required.',

            'password.confirmed' =>
                'Password confirmation does not match.',
        ];
    }
}