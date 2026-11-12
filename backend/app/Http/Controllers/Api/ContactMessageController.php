<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactMessageController extends Controller
{
    public function store(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'full_name' => [
                'required',
                'string',
                'max:150',
            ],

            'contact' => [
                'required',
                'string',
                'max:255',
            ],

            'subject' => [
                'required',
                'string',
                Rule::in([
                    'product',
                    'order',
                    'returns',
                    'press',
                    'other',
                ]),
            ],

            'message' => [
                'required',
                'string',
                'max:5000',
            ],

            'locale' => [
                'required',
                'string',
                Rule::in([
                    'en',
                    'fa',
                ]),
            ],
        ]);

        $contactMessage =
            ContactMessage::create([
                ...$validated,
                'status' => 'new',
            ]);

        return response()->json([
            'message' =>
                'Contact message received successfully.',

            'data' => [
                'id' =>
                    $contactMessage->id,

                'status' =>
                    $contactMessage->status,
            ],
        ], 201);
    }
}