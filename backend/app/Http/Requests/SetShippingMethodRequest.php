<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetShippingMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'shipping_method_id' => [
                'required',
                'integer',
                Rule::exists('shipping_methods', 'id')
                    ->where('is_active', true),
            ],
        ];
    }
}