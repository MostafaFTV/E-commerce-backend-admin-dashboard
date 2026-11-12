<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->transform($user),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'last_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'birth_date' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
            'marketing_sms' => [
                'sometimes',
                'required',
                'boolean',
            ],
            'preferred_category_ids' => [
                'sometimes',
                'array',
            ],
            'preferred_category_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
            ],
        ]);

        // Contact identifiers require their separate verification flows.
        foreach (['email', 'phone'] as $field) {
            if (
                array_key_exists($field, $validated)
                && $validated[$field] !== $user->{$field}
            ) {
                throw ValidationException::withMessages([
                    $field => [
                        'Contact details cannot be changed through this endpoint.',
                    ],
                ]);
            }
        }

        unset($validated['email'], $validated['phone']);

        $hasCategories = array_key_exists(
            'preferred_category_ids',
            $validated
        );

        $categoryIds = $hasCategories
            ? $validated['preferred_category_ids']
            : [];

        unset($validated['preferred_category_ids']);

        DB::transaction(function () use (
            $user,
            $validated,
            $hasCategories,
            $categoryIds
        ): void {
            $user->update($validated);

            if ($hasCategories) {
                $user->preferredCategories()->sync($categoryIds);
            }
        });

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data' => $this->transform($user->fresh()),
        ]);
    }

    protected function transform(User $user): array
    {
        $user->loadMissing('preferredCategories');

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status?->value,
            'email_verified_at' => $user->email_verified_at,
            'phone_verified_at' => $user->phone_verified_at,
            'birth_date' => $user->birth_date?->format('Y-m-d'),
            'preferred_category_ids' => $user->preferredCategories
                ->pluck('id')
                ->sort()
                ->values()
                ->all(),
            'marketing_sms' => (bool) $user->marketing_sms,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}