<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaqContent;
use App\Models\FaqGroup;
use App\Models\FaqQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(
        Request $request
    ): JsonResponse {
        $faq =
            FaqContent::query()
                ->where(
                    'content_key',
                    FaqContent::KEY
                )
                ->where(
                    'is_active',
                    true
                )
                ->firstOrFail();

        $locale =
            app()->getLocale();

        $groups =
            FaqGroup::query()
                ->where(
                    'faq_content_id',
                    $faq->id
                )
                ->where(
                    'is_active',
                    true
                )
                ->orderBy(
                    'sort_order'
                )
                ->orderBy(
                    'id'
                )
                ->get();

        $groupIds =
            $groups
                ->pluck('id');

        $questions =
            FaqQuestion::query()
                ->whereIn(
                    'faq_group_id',
                    $groupIds
                )
                ->where(
                    'is_active',
                    true
                )
                ->orderBy(
                    'sort_order'
                )
                ->orderBy(
                    'id'
                )
                ->get()
                ->groupBy(
                    'faq_group_id'
                );

        return response()->json([
            'data' => [
                'title' =>
                    $this->localized(
                        $faq->title,
                        $faq->title_en,
                        $locale
                    ),

                'intro' =>
                    $this->localized(
                        $faq->intro,
                        $faq->intro_en,
                        $locale
                    ),

                'groups' =>
                    $groups
                        ->map(
                            function (
                                FaqGroup $group
                            ) use (
                                $questions,
                                $locale
                            ): array {
                                return [
                                    'id' =>
                                        $group->id,

                                    'title' =>
                                        $this->localized(
                                            $group->title,
                                            $group->title_en,
                                            $locale
                                        ),

                                    'sort_order' =>
                                        $group->sort_order,

                                    'questions' =>
                                        collect(
                                            $questions->get(
                                                $group->id,
                                                collect()
                                            )
                                        )
                                            ->map(
                                                function (
                                                    FaqQuestion $question
                                                ) use (
                                                    $locale
                                                ): array {
                                                    return [
                                                        'id' =>
                                                            $question->id,

                                                        'question' =>
                                                            $this->localized(
                                                                $question->question,
                                                                $question->question_en,
                                                                $locale
                                                            ),

                                                        'answer' =>
                                                            $this->localized(
                                                                $question->answer,
                                                                $question->answer_en,
                                                                $locale
                                                            ),

                                                        'sort_order' =>
                                                            $question->sort_order,
                                                    ];
                                                }
                                            )
                                            ->values()
                                            ->all(),
                                ];
                            }
                        )
                        ->values()
                        ->all(),
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