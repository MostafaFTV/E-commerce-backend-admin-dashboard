<?php

namespace App\Http\Requests;

use App\Models\Category;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('category');

        $categoryId = $category instanceof Category
            ? $category->id
            : $category;

        return [
            /*
            |--------------------------------------------------------------------------
            | Parent
            |--------------------------------------------------------------------------
            */

            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:categories,id',

                Rule::notIn([
                    $categoryId,
                ]),
            ],

            /*
            |--------------------------------------------------------------------------
            | Basic Information
            |--------------------------------------------------------------------------
            */

            'name' => [
                'sometimes',
                'array',
            ],

            'name.en' => [
                'sometimes',
                'string',
                'max:150',
            ],

            'name.fa' => [
                'sometimes',
                'string',
                'max:150',
            ],

            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(
                    'categories',
                    'slug'
                )->ignore($categoryId),
            ],

            'description' => [
                'sometimes',
                'nullable',
                'array',
            ],

            'description.en' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'description.fa' => [
                'sometimes',
                'nullable',
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | Media
            |--------------------------------------------------------------------------
            */

            /*
            |--------------------------------------------------------------------------
            | SEO Metadata
            |--------------------------------------------------------------------------
            */
            'seo_title' => [
                'sometimes',
                'nullable',
                'array:fa,en',
            ],

            'seo_title.fa' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'seo_title.en' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'seo_description' => [
                'sometimes',
                'nullable',
                'array:fa,en',
            ],

            'seo_description.fa' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],

            'seo_description.en' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],

            'seo_image' => [
                'sometimes',
                'nullable',
                'string',
                'max:2048',
            ],

            'image' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',

                function (
                    string $attribute,
                    mixed $value,
                    Closure $fail
                ): void {
                    if ($value === null) {
                        return;
                    }

                    $value = trim(
                        (string) $value
                    );

                    if ($value === '') {
                        return;
                    }

                    if (
                        preg_match(
                            '/[\x00-\x1F\x7F\\\\<>"\']/',
                            $value
                        )
                    ) {
                        $fail(
                            'Category image contains invalid characters.'
                        );

                        return;
                    }

                    if (
                        str_contains(
                            $value,
                            '..'
                        )
                    ) {
                        $fail(
                            'Category image contains an invalid path.'
                        );

                        return;
                    }

                    if (
                        str_starts_with(
                            $value,
                            '//'
                        )
                    ) {
                        $fail(
                            'Protocol-relative image URLs are not allowed.'
                        );

                        return;
                    }

                    if (
                        str_starts_with(
                            $value,
                            '/'
                        )
                    ) {
                        return;
                    }

                    $scheme = parse_url(
                        $value,
                        PHP_URL_SCHEME
                    );

                    if (
                        ! in_array(
                            strtolower(
                                (string) $scheme
                            ),
                            [
                                'http',
                                'https',
                            ],
                            true
                        )
                    ) {
                        $fail(
                            'Category image must be an internal path or an HTTP(S) URL.'
                        );

                        return;
                    }

                    if (
                        filter_var(
                            $value,
                            FILTER_VALIDATE_URL
                        ) === false
                    ) {
                        $fail(
                            'Category image must be a valid URL.'
                        );
                    }
                },
            ],

            /*
            |--------------------------------------------------------------------------
            | Management
            |--------------------------------------------------------------------------
            */

            'sort_order' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'parent_id.integer' =>
                'Parent category must be a valid category.',

            'parent_id.exists' =>
                'Parent category does not exist.',

            'name.array' =>
                'Category name must contain translations.',

            'name.en.string' =>
                'English category name must be a string.',

            'name.en.max' =>
                'English category name may not exceed 150 characters.',

            'name.fa.string' =>
                'Persian category name must be a string.',

            'name.fa.max' =>
                'Persian category name may not exceed 150 characters.',

            'slug.string' =>
                'Category slug must be a string.',

            'slug.regex' =>
                'Category slug must contain only lowercase letters, numbers and hyphens.',

            'slug.unique' =>
                'This slug is already used.',

            'description.array' =>
                'Category description must contain translations.',

            'description.en.string' =>
                'English category description must be a string.',

            'description.fa.string' =>
                'Persian category description must be a string.',

            'sort_order.integer' =>
                'Sort order must be a number.',

            'sort_order.min' =>
                'Sort order cannot be negative.',

            'is_active.boolean' =>
                'Active status must be true or false.',

            'image.string' =>
                'Category image must be a string.',

            'image.max' =>
                'Category image may not exceed 500 characters.',
        ];
    }
}