<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequestOtpRequest extends FormRequest
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
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required' =>
                'Mobile number is required.',

            'mobile.regex' =>
                'Mobile number must be a valid Iranian mobile number.',
        ];
    }
}