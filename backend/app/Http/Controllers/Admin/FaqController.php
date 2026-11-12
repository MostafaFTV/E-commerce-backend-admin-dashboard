<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FaqContent;
use App\Models\FaqGroup;
use App\Models\FaqQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FaqController extends Controller
{
    public function show(): JsonResponse
    {
        $faq =
            $this->faq();

        return response()->json([
            'data' =>
                $this->transform(
                    $faq
                ),
        ]);
    }

    public function update(
        Request $request
    ): JsonResponse {
        $validated =
            $request->validate([
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

                'intro' => [
                    'nullable',
                    'string',
                ],

                'intro_en' => [
                    'nullable',
                    'string',
                ],

                'is_active' => [
                    'required',
                    'boolean',
                ],

                'groups' => [
                    'required',
                    'array',
                ],

                'groups.*.id' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],

                'groups.*.title' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'groups.*.title_en' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'groups.*.sort_order' => [
                    'required',
                    'integer',
                    'min:0',
                ],

                'groups.*.is_active' => [
                    'required',
                    'boolean',
                ],

                'groups.*.questions' => [
                    'required',
                    'array',
                ],

                'groups.*.questions.*.id' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],

                'groups.*.questions.*.question' => [
                    'required',
                    'string',
                    'max:500',
                ],

                'groups.*.questions.*.question_en' => [
                    'required',
                    'string',
                    'max:500',
                ],

                'groups.*.questions.*.answer' => [
                    'required',
                    'string',
                ],

                'groups.*.questions.*.answer_en' => [
                    'required',
                    'string',
                ],

                'groups.*.questions.*.sort_order' => [
                    'required',
                    'integer',
                    'min:0',
                ],

                'groups.*.questions.*.is_active' => [
                    'required',
                    'boolean',
                ],
            ]);

        $faq =
            $this->faq();

        DB::transaction(
            function () use (
                $faq,
                $validated
            ): void {
                $faq->update([
                    'title' =>
                        $validated['title'],

                    'title_en' =>
                        $validated['title_en'],

                    'intro' =>
                        $validated['intro'] ??
                        null,

                    'intro_en' =>
                        $validated['intro_en'] ??
                        null,

                    'is_active' =>
                        $validated['is_active'],
                ]);

                $existingGroups =
                    $faq
                        ->groups()
                        ->with('questions')
                        ->get()
                        ->keyBy('id');

                $keptGroupIds = [];

                foreach (
                    $validated['groups']
                    as $groupData
                ) {
                    $groupId =
                        $groupData['id'] ??
                        null;

                    if ($groupId !== null) {
                        $group =
                            $existingGroups->get(
                                $groupId
                            );

                        if (! $group) {
                            throw ValidationException::withMessages([
                                'groups' => [
                                    'One or more FAQ groups are invalid.',
                                ],
                            ]);
                        }

                        $group->update([
                            'title' =>
                                $groupData['title'],

                            'title_en' =>
                                $groupData['title_en'],

                            'sort_order' =>
                                $groupData['sort_order'],

                            'is_active' =>
                                $groupData['is_active'],
                        ]);
                    } else {
                        $group =
                            $faq
                                ->groups()
                                ->create([
                                    'title' =>
                                        $groupData['title'],

                                    'title_en' =>
                                        $groupData['title_en'],

                                    'sort_order' =>
                                        $groupData['sort_order'],

                                    'is_active' =>
                                        $groupData['is_active'],
                                ]);
                    }

                    $keptGroupIds[] =
                        $group->id;

                    $existingQuestions =
                        $group
                            ->questions()
                            ->get()
                            ->keyBy('id');

                    $keptQuestionIds = [];

                    foreach (
                        $groupData['questions']
                        as $questionData
                    ) {
                        $questionId =
                            $questionData['id'] ??
                            null;

                        if ($questionId !== null) {
                            $question =
                                $existingQuestions->get(
                                    $questionId
                                );

                            if (! $question) {
                                throw ValidationException::withMessages([
                                    'groups' => [
                                        'One or more FAQ questions are invalid.',
                                    ],
                                ]);
                            }

                            $question->update([
                                'question' =>
                                    $questionData['question'],

                                'question_en' =>
                                    $questionData['question_en'],

                                'answer' =>
                                    $questionData['answer'],

                                'answer_en' =>
                                    $questionData['answer_en'],

                                'sort_order' =>
                                    $questionData['sort_order'],

                                'is_active' =>
                                    $questionData['is_active'],
                            ]);
                        } else {
                            $question =
                                $group
                                    ->questions()
                                    ->create([
                                        'question' =>
                                            $questionData['question'],

                                        'question_en' =>
                                            $questionData['question_en'],

                                        'answer' =>
                                            $questionData['answer'],

                                        'answer_en' =>
                                            $questionData['answer_en'],

                                        'sort_order' =>
                                            $questionData['sort_order'],

                                        'is_active' =>
                                            $questionData['is_active'],
                                    ]);
                        }

                        $keptQuestionIds[] =
                            $question->id;
                    }

                    $deleteQuestions =
                        $group
                            ->questions();

                    if (
                        count(
                            $keptQuestionIds
                        ) > 0
                    ) {
                        $deleteQuestions
                            ->whereNotIn(
                                'id',
                                $keptQuestionIds
                            );
                    }

                    $deleteQuestions
                        ->delete();
                }

                $deleteGroups =
                    $faq
                        ->groups();

                if (
                    count(
                        $keptGroupIds
                    ) > 0
                ) {
                    $deleteGroups
                        ->whereNotIn(
                            'id',
                            $keptGroupIds
                        );
                }

                $deleteGroups
                    ->delete();
            }
        );

        $faq =
            $this->faq();

        return response()->json([
            'message' =>
                'FAQ content updated successfully.',

            'data' =>
                $this->transform(
                    $faq
                ),
        ]);
    }

    private function faq(): FaqContent
    {
        return FaqContent::query()
            ->where(
                'content_key',
                FaqContent::KEY
            )
            ->with([
                'groups' =>
                    function ($query): void {
                        $query
                            ->orderBy(
                                'sort_order'
                            )
                            ->orderBy(
                                'id'
                            );
                    },

                'groups.questions' =>
                    function ($query): void {
                        $query
                            ->orderBy(
                                'sort_order'
                            )
                            ->orderBy(
                                'id'
                            );
                    },
            ])
            ->firstOrFail();
    }

    private function transform(
        FaqContent $faq
    ): array {
        return [
            'id' =>
                $faq->id,

            'content_key' =>
                $faq->content_key,

            'title' =>
                $faq->title,

            'title_en' =>
                $faq->title_en,

            'intro' =>
                $faq->intro,

            'intro_en' =>
                $faq->intro_en,

            'is_active' =>
                (bool) $faq->is_active,

            'groups' =>
                $faq
                    ->groups
                    ->map(
                        function (
                            FaqGroup $group
                        ): array {
                            return [
                                'id' =>
                                    $group->id,

                                'title' =>
                                    $group->title,

                                'title_en' =>
                                    $group->title_en,

                                'sort_order' =>
                                    $group->sort_order,

                                'is_active' =>
                                    (bool) $group->is_active,

                                'questions' =>
                                    $group
                                        ->questions
                                        ->map(
                                            function (
                                                FaqQuestion $question
                                            ): array {
                                                return [
                                                    'id' =>
                                                        $question->id,

                                                    'question' =>
                                                        $question->question,

                                                    'question_en' =>
                                                        $question->question_en,

                                                    'answer' =>
                                                        $question->answer,

                                                    'answer_en' =>
                                                        $question->answer_en,

                                                    'sort_order' =>
                                                        $question->sort_order,

                                                    'is_active' =>
                                                        (bool) $question->is_active,
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

            'created_at' =>
                $faq->created_at,

            'updated_at' =>
                $faq->updated_at,
        ];
    }
}