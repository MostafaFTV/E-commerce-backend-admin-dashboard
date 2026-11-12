<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactPageContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactPageContentController extends Controller
{
    public function index(
        Request $request
    ): JsonResponse {
        $content =
            ContactPageContent::query()
                ->where(
                    'content_key',
                    ContactPageContent::KEY
                )
                ->where(
                    'is_active',
                    true
                )
                ->firstOrFail();

        $locale =
            app()->getLocale();

        return response()->json([
            'data' => [
                'id' =>
                    $content->id,

                'locale' =>
                    $locale,

                'direction' =>
                    $request->attributes->get(
                        'direction',
                        'ltr'
                    ),

                'eyebrow' =>
                    $this->localized(
                        $content->eyebrow,
                        $content->eyebrow_en,
                        $locale
                    ),

                'title' =>
                    $this->localized(
                        $content->title,
                        $content->title_en,
                        $locale
                    ),

                'instagram' => [
                    'label' =>
                        $this->localized(
                            $content->instagram_label,
                            $content->instagram_label_en,
                            $locale
                        ),

                    'value' =>
                        $content->instagram_value,

                    'url' =>
                        $content->instagram_url,
                ],

                'telegram' => [
                    'label' =>
                        $this->localized(
                            $content->telegram_label,
                            $content->telegram_label_en,
                            $locale
                        ),

                    'value' =>
                        $content->telegram_value,

                    'url' =>
                        $content->telegram_url,
                ],

                'whatsapp' => [
                    'label' =>
                        $this->localized(
                            $content->whatsapp_label,
                            $content->whatsapp_label_en,
                            $locale
                        ),

                    'value' =>
                        $content->whatsapp_value,

                    'url' =>
                        $content->whatsapp_url,
                ],

                'phone' => [
                    'label' =>
                        $this->localized(
                            $content->phone_label,
                            $content->phone_label_en,
                            $locale
                        ),

                    'value' =>
                        $content->phone_value,

                    'url' =>
                        $content->phone_url,
                ],
            ],
        ]);
    }

    private function localized(
        ?string $fa,
        ?string $en,
        string $locale
    ): ?string {
        if (
            $locale === 'en'
            && $en !== null
            && trim($en) !== ''
        ) {
            return $en;
        }

        return $fa;
    }
}