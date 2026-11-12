<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactPageContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactPageContentController extends Controller
{
    public function show(): JsonResponse
    {
        $content =
            ContactPageContent::query()
                ->where(
                    'content_key',
                    ContactPageContent::KEY
                )
                ->firstOrFail();

        return response()->json([
            'data' =>
                $this->transform(
                    $content
                ),
        ]);
    }

    public function update(
        Request $request
    ): JsonResponse {
        $validated =
            $request->validate([
                'eyebrow' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'eyebrow_en' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'title' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'title_en' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'instagram_label' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'instagram_label_en' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'instagram_value' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'instagram_url' => [
                    'nullable',
                    'url',
                    'max:2048',
                ],

                'telegram_label' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'telegram_label_en' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'telegram_value' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'telegram_url' => [
                    'nullable',
                    'string',
                    'max:2048',
                ],

                'whatsapp_label' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'whatsapp_label_en' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'whatsapp_value' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'whatsapp_url' => [
                    'nullable',
                    'url',
                    'max:2048',
                ],

                'phone_label' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'phone_label_en' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'phone_value' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'phone_url' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'is_active' => [
                    'required',
                    'boolean',
                ],
            ]);

        $content =
            ContactPageContent::query()
                ->where(
                    'content_key',
                    ContactPageContent::KEY
                )
                ->firstOrFail();

        $content->update(
            $validated
        );

        $content->refresh();

        return response()->json([
            'message' =>
                'Contact content updated successfully.',

            'data' =>
                $this->transform(
                    $content
                ),
        ]);
    }

    private function transform(
        ContactPageContent $content
    ): array {
        return [
            'id' =>
                $content->id,

            'content_key' =>
                $content->content_key,

            'eyebrow' =>
                $content->eyebrow,

            'eyebrow_en' =>
                $content->eyebrow_en,

            'title' =>
                $content->title,

            'title_en' =>
                $content->title_en,

            'instagram_label' =>
                $content->instagram_label,

            'instagram_label_en' =>
                $content->instagram_label_en,

            'instagram_value' =>
                $content->instagram_value,

            'instagram_url' =>
                $content->instagram_url,

            'telegram_label' =>
                $content->telegram_label,

            'telegram_label_en' =>
                $content->telegram_label_en,

            'telegram_value' =>
                $content->telegram_value,

            'telegram_url' =>
                $content->telegram_url,

            'whatsapp_label' =>
                $content->whatsapp_label,

            'whatsapp_label_en' =>
                $content->whatsapp_label_en,

            'whatsapp_value' =>
                $content->whatsapp_value,

            'whatsapp_url' =>
                $content->whatsapp_url,

            'phone_label' =>
                $content->phone_label,

            'phone_label_en' =>
                $content->phone_label_en,

            'phone_value' =>
                $content->phone_value,

            'phone_url' =>
                $content->phone_url,

            'is_active' =>
                (bool) $content->is_active,

            'created_at' =>
                $content->created_at,

            'updated_at' =>
                $content->updated_at,
        ];
    }
}