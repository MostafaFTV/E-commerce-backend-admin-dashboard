<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyRegistrationOtpRequest extends FormRequest
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
        ];
    }
}