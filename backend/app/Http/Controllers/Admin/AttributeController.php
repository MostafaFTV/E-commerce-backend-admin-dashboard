<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute as ProductAttribute;
use App\Models\AttributeValue;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttributeController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {
    }

    public function index(
        Request $request
    ): JsonResponse {
        abort_unless(
            auth()->user()->hasPermission(
                'products.view'
            ),
            403
        );

        $search = trim(
            $request
                ->string('search')
                ->toString()
        );

        $attributes =
            ProductAttribute::query()
                ->with([
                    'values' =>
                        fn ($query) =>
                            $query->orderBy(
                                'id'
                            ),
                ])
                ->when(
                    $search !== '',
                    function ($query) use (
                        $search
                    ) {
                        $like =
                            '%' .
                            $search .
                            '%';

                        $query->where(
                            function (
                                $query
                            ) use ($like) {
                                $query
                                    ->where(
                                        'slug',
                                        'like',
                                        $like
                                    )
                                    ->orWhereRaw(
                                        "JSON_UNQUOTE(JSON_EXTRACT(`name`, '$.en')) LIKE ?",
                                        [$like]
                                    )
                                    ->orWhereRaw(
                                        "JSON_UNQUOTE(JSON_EXTRACT(`name`, '$.fa')) LIKE ?",
                                        [$like]
                                    );
                            }
                        );
                    }
                )
                ->orderByRaw(
                    "COALESCE(
                        JSON_UNQUOTE(
                            JSON_EXTRACT(
                                `name`,
                                '$.en'
                            )
                        ),
                        JSON_UNQUOTE(
                            JSON_EXTRACT(
                                `name`,
                                '$.fa'
                            )
                        ),
                        ''
                    )"
                )
                ->get();

        return response()->json([
            'data' =>
                $attributes
                    ->map(
                        fn (
                            ProductAttribute
                            $attribute
                        ) =>
                            $this
                                ->serializeAttribute(
                                    $attribute
                                )
                    )
                    ->values()
                    ->all(),
        ]);
    }

    public function store(
        Request $request
    ): JsonResponse {
        abort_unless(
            auth()->user()->hasAnyPermission([
                'products.create',
                'products.update',
            ]),
            403
        );

        $this->prepareLocalizedField(
            $request,
            'name'
        );

        $this->prepareLocalizedList(
            $request,
            'values'
        );

        if ($request->has('slug')) {
            $slug = trim(
                (string)
                    $request->input(
                        'slug'
                    )
            );

            $request->merge([
                'slug' =>
                    $slug !== ''
                        ? Str::lower(
                            $slug
                        )
                        : null,
            ]);
        }

        $validated =
            $request->validate([
                'name' => [
                    'required',
                    'array',
                ],

                'name.en' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'name.fa' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'slug' => [
                    'nullable',
                    'string',
                    'max:120',
                    'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                    'unique:attributes,slug',
                ],

                'type' => [
                    'required',
                    'in:text,color,number,select',
                ],

                'values' => [
                    'sometimes',
                    'array',
                ],

                'values.*' => [
                    'array',
                ],

                'values.*.en' => [
                    'nullable',
                    'string',
                    'max:150',
                ],

                'values.*.fa' => [
                    'nullable',
                    'string',
                    'max:150',
                ],
            ]);

        $name =
            $this->normalizeTranslations(
                $validated['name'],
                'name'
            );

        $values = [];

        foreach (
            $validated['values'] ?? []
            as $index => $rawValue
        ) {
            $values[] =
                $this
                    ->normalizeTranslations(
                        $rawValue,
                        "values.{$index}"
                    );
        }

        $attribute =
            DB::transaction(
                function () use (
                    $validated,
                    $name,
                    $values
                ) {
                    $attribute =
                        ProductAttribute::create([
                            'name' =>
                                $name,

                            'slug' =>
                                $validated['slug']
                                ?? $this
                                    ->generateSlug(
                                        $this
                                            ->translationForSlug(
                                                $name
                                            )
                                    ),

                            'type' =>
                                $validated['type'],
                        ]);

                    foreach (
                        $values
                        as $index =>
                            $translations
                    ) {
                        $this
                            ->assertUniqueValueTranslations(
                                $attribute->id,
                                $translations,
                                null,
                                "values.{$index}"
                            );

                        $attribute
                            ->values()
                            ->create([
                                'value' =>
                                    $translations,
                            ]);
                    }

                    return $attribute;
                }
            );

        $attribute->load('values');

        $this->activityLogService->log(
            action:
                'attributes.created',

            description:
                'Product attribute created.',

            properties: [
                'attribute' =>
                    $this
                        ->serializeAttribute(
                            $attribute
                        ),
            ],

            subject:
                $attribute,

            request:
                $request
        );

        return response()->json([
            'data' =>
                $this
                    ->serializeAttribute(
                        $attribute
                    ),
        ], 201);
    }

    public function update(
        Request $request,
        ProductAttribute $attribute
    ): JsonResponse {
        abort_unless(
            auth()->user()->hasPermission(
                'products.update'
            ),
            403
        );

        $this->prepareLocalizedField(
            $request,
            'name'
        );

        if ($request->has('slug')) {
            $slug = trim(
                (string)
                    $request->input(
                        'slug'
                    )
            );

            $request->merge([
                'slug' =>
                    $slug !== ''
                        ? Str::lower(
                            $slug
                        )
                        : null,
            ]);
        }

        $validated =
            $request->validate([
                'name' => [
                    'sometimes',
                    'array',
                ],

                'name.en' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'name.fa' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'slug' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:120',
                    'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',

                    Rule::unique(
                        'attributes',
                        'slug'
                    )->ignore(
                        $attribute->id
                    ),
                ],

                'type' => [
                    'sometimes',
                    'in:text,color,number,select',
                ],
            ]);

        $updates = [];

        if (
            array_key_exists(
                'name',
                $validated
            )
        ) {
            $nameUpdates =
                $this
                    ->normalizeTranslations(
                        $validated['name'],
                        'name',
                        false
                    );

            $updates['name'] =
                $this
                    ->mergeTranslations(
                        $attribute
                            ->getRawOriginal(
                                'name'
                            ),
                        $nameUpdates,
                        'name'
                    );
        }

        if (
            array_key_exists(
                'type',
                $validated
            )
        ) {
            $updates['type'] =
                $validated['type'];
        }

        if (
            array_key_exists(
                'slug',
                $validated
            )
        ) {
            if (
                $validated['slug'] !==
                null
            ) {
                $updates['slug'] =
                    $validated['slug'];
            } else {
                $translations =
                    $updates['name']
                    ?? $this
                        ->decodeTranslations(
                            $attribute
                                ->getRawOriginal(
                                    'name'
                                )
                        );

                $updates['slug'] =
                    $this->generateSlug(
                        $this
                            ->translationForSlug(
                                $translations
                            ),
                        $attribute
                    );
            }
        }

        if ($updates !== []) {
            $attribute->update(
                $updates
            );
        }

        $attribute
            ->refresh()
            ->load('values');

        $this->activityLogService->log(
            action:
                'attributes.updated',

            description:
                'Product attribute updated.',

            properties: [
                'attribute' =>
                    $this
                        ->serializeAttribute(
                            $attribute
                        ),
            ],

            subject:
                $attribute,

            request:
                $request
        );

        return response()->json([
            'data' =>
                $this
                    ->serializeAttribute(
                        $attribute
                    ),
        ]);
    }

    public function destroy(
        Request $request,
        ProductAttribute $attribute
    ): JsonResponse {
        abort_unless(
            auth()->user()->hasPermission(
                'products.update'
            ),
            403
        );

        $attribute->load('values');

        $inUse =
            $attribute
                ->values()
                ->whereHas('variants')
                ->exists();

        if ($inUse) {
            return response()->json([
                'message' =>
                    'Cannot delete an attribute that is used by product variants.',
            ], 422);
        }

        $metadata =
            $this->serializeAttribute(
                $attribute
            );

        $attribute->delete();

        $this->activityLogService->log(
            action:
                'attributes.deleted',

            description:
                'Product attribute deleted.',

            properties: [
                'attribute' =>
                    $metadata,
            ],

            subject:
                $attribute,

            request:
                $request
        );

        return response()->json([
            'message' =>
                'Attribute deleted successfully.',
        ]);
    }

    public function storeValue(
        Request $request,
        ProductAttribute $attribute
    ): JsonResponse {
        abort_unless(
            auth()->user()->hasAnyPermission([
                'products.create',
                'products.update',
            ]),
            403
        );

        $this->prepareLocalizedField(
            $request,
            'value'
        );

        $validated =
            $request->validate([
                'value' => [
                    'required',
                    'array',
                ],

                'value.en' => [
                    'nullable',
                    'string',
                    'max:150',
                ],

                'value.fa' => [
                    'nullable',
                    'string',
                    'max:150',
                ],
            ]);

        $translations =
            $this->normalizeTranslations(
                $validated['value'],
                'value'
            );

        $this
            ->assertUniqueValueTranslations(
                $attribute->id,
                $translations,
                null,
                'value'
            );

        $value =
            $attribute
                ->values()
                ->create([
                    'value' =>
                        $translations,
                ]);

        $this->activityLogService->log(
            action:
                'attribute_values.created',

            description:
                'Product attribute value created.',

            properties: [
                'attribute_id' =>
                    $attribute->id,

                'attribute_value' =>
                    $this
                        ->serializeValue(
                            $value
                        ),
            ],

            subject:
                $value,

            request:
                $request
        );

        return response()->json([
            'data' =>
                $this
                    ->serializeValue(
                        $value
                    ),
        ], 201);
    }

    public function updateValue(
        Request $request,
        AttributeValue $attributeValue
    ): JsonResponse {
        abort_unless(
            auth()->user()->hasPermission(
                'products.update'
            ),
            403
        );

        $this->prepareLocalizedField(
            $request,
            'value'
        );

        $validated =
            $request->validate([
                'value' => [
                    'required',
                    'array',
                ],

                'value.en' => [
                    'nullable',
                    'string',
                    'max:150',
                ],

                'value.fa' => [
                    'nullable',
                    'string',
                    'max:150',
                ],
            ]);

        $valueUpdates =
            $this->normalizeTranslations(
                $validated['value'],
                'value',
                false
            );

        $translations =
            $this->mergeTranslations(
                $attributeValue
                    ->getRawOriginal(
                        'value'
                    ),
                $valueUpdates,
                'value'
            );

        $this
            ->assertUniqueValueTranslations(
                $attributeValue
                    ->attribute_id,

                $translations,

                $attributeValue->id,

                'value'
            );

        $attributeValue->update([
            'value' =>
                $translations,
        ]);

        $attributeValue->refresh();

        $this->activityLogService->log(
            action:
                'attribute_values.updated',

            description:
                'Product attribute value updated.',

            properties: [
                'attribute_id' =>
                    $attributeValue
                        ->attribute_id,

                'attribute_value' =>
                    $this
                        ->serializeValue(
                            $attributeValue
                        ),
            ],

            subject:
                $attributeValue,

            request:
                $request
        );

        return response()->json([
            'data' =>
                $this
                    ->serializeValue(
                        $attributeValue
                    ),
        ]);
    }

    public function destroyValue(
        Request $request,
        AttributeValue $attributeValue
    ): JsonResponse {
        abort_unless(
            auth()->user()->hasPermission(
                'products.update'
            ),
            403
        );

        if (
            $attributeValue
                ->variants()
                ->exists()
        ) {
            return response()->json([
                'message' =>
                    'Cannot delete an attribute value that is used by product variants.',
            ], 422);
        }

        $metadata =
            $this->serializeValue(
                $attributeValue
            );

        $attributeValue->delete();

        $this->activityLogService->log(
            action:
                'attribute_values.deleted',

            description:
                'Product attribute value deleted.',

            properties: [
                'attribute_value' =>
                    $metadata,
            ],

            subject:
                $attributeValue,

            request:
                $request
        );

        return response()->json([
            'message' =>
                'Attribute value deleted successfully.',
        ]);
    }

    protected function prepareLocalizedField(
        Request $request,
        string $key
    ): void {
        if (! $request->has($key)) {
            return;
        }

        $value =
            $request->input($key);

        if (! is_string($value)) {
            return;
        }

        $value = trim($value);

        $request->merge([
            $key => [
                $this->detectLocale(
                    $value
                ) =>
                    $value,
            ],
        ]);
    }

    protected function prepareLocalizedList(
        Request $request,
        string $key
    ): void {
        if (! $request->has($key)) {
            return;
        }

        $values =
            $request->input($key);

        if (! is_array($values)) {
            return;
        }

        $normalized = [];

        foreach ($values as $value) {
            if (is_string($value)) {
                $value =
                    trim($value);

                $normalized[] = [
                    $this
                        ->detectLocale(
                            $value
                        ) =>
                            $value,
                ];

                continue;
            }

            $normalized[] =
                $value;
        }

        $request->merge([
            $key =>
                $normalized,
        ]);
    }

    protected function normalizeTranslations(
        array $translations,
        string $field,
        bool $requireOne = true
    ): array {
        $normalized = [];

        foreach (
            ['en', 'fa']
            as $locale
        ) {
            if (
                ! array_key_exists(
                    $locale,
                    $translations
                )
            ) {
                continue;
            }

            $value =
                $translations[$locale];

            if ($value === null) {
                $normalized[$locale] =
                    null;

                continue;
            }

            $value = trim(
                (string) $value
            );

            $normalized[$locale] =
                $value !== ''
                    ? $value
                    : null;
        }

        if (
            $requireOne &&
            ! $this
                ->hasTranslation(
                    $normalized
                )
        ) {
            throw ValidationException::withMessages([
                $field =>
                    'At least one English or Persian translation is required.',
            ]);
        }

        return $normalized;
    }

    protected function mergeTranslations(
        mixed $rawOriginal,
        array $updates,
        string $field
    ): array {
        $translations =
            array_replace(
                $this
                    ->decodeTranslations(
                        $rawOriginal
                    ),
                $updates
            );

        if (
            ! $this
                ->hasTranslation(
                    $translations
                )
        ) {
            throw ValidationException::withMessages([
                $field =>
                    'At least one English or Persian translation is required.',
            ]);
        }

        return $translations;
    }

    protected function decodeTranslations(
        mixed $value
    ): array {
        if (is_array($value)) {
            $decoded =
                $value;
        } elseif (
            is_string($value) &&
            trim($value) !== ''
        ) {
            $decoded =
                json_decode(
                    $value,
                    true
                );

            if (! is_array($decoded)) {
                $plain =
                    trim($value);

                return [
                    $this
                        ->detectLocale(
                            $plain
                        ) =>
                            $plain,
                ];
            }
        } else {
            return [];
        }

        $translations = [];

        foreach (
            ['en', 'fa']
            as $locale
        ) {
            if (
                ! array_key_exists(
                    $locale,
                    $decoded
                )
            ) {
                continue;
            }

            $translation =
                $decoded[$locale];

            if ($translation === null) {
                $translations[$locale] =
                    null;

                continue;
            }

            $translations[$locale] =
                trim(
                    (string)
                        $translation
                );
        }

        return $translations;
    }

    protected function hasTranslation(
        array $translations
    ): bool {
        foreach (
            ['en', 'fa']
            as $locale
        ) {
            if (
                isset(
                    $translations[$locale]
                ) &&
                trim(
                    (string)
                        $translations[$locale]
                ) !== ''
            ) {
                return true;
            }
        }

        return false;
    }

    protected function translationForSlug(
        array $translations
    ): string {
        foreach (
            ['en', 'fa']
            as $locale
        ) {
            $value =
                $translations[$locale]
                ?? null;

            if (
                is_string($value) &&
                trim($value) !== ''
            ) {
                return trim($value);
            }
        }

        return Str::random(12);
    }

    protected function detectLocale(
        string $value
    ): string {
        return preg_match(
            '/[\x{0600}-\x{06FF}]/u',
            $value
        ) === 1
            ? 'fa'
            : 'en';
    }

    protected function assertUniqueValueTranslations(
        int $attributeId,
        array $translations,
        ?int $ignoreId = null,
        string $field = 'value'
    ): void {
        $columns = [
            'en' =>
                'value_en',

            'fa' =>
                'value_fa',
        ];

        foreach (
            $columns
            as $locale => $column
        ) {
            $value =
                $translations[$locale]
                ?? null;

            if (
                ! is_string($value) ||
                trim($value) === ''
            ) {
                continue;
            }

            $query =
                AttributeValue::query()
                    ->where(
                        'attribute_id',
                        $attributeId
                    )
                    ->where(
                        $column,
                        trim($value)
                    );

            if ($ignoreId !== null) {
                $query->where(
                    'id',
                    '!=',
                    $ignoreId
                );
            }

            if ($query->exists()) {
                throw ValidationException::withMessages([
                    "{$field}.{$locale}" =>
                        "This {$locale} attribute value already exists.",
                ]);
            }
        }
    }

    protected function serializeAttribute(
        ProductAttribute $attribute
    ): array {
        return [
            'id' =>
                $attribute->id,

            'name' =>
                $this
                    ->decodeTranslations(
                        $attribute
                            ->getRawOriginal(
                                'name'
                            )
                    ),

            'slug' =>
                $attribute->slug,

            'type' =>
                $attribute->type,

            'values' =>
                $attribute
                    ->relationLoaded(
                        'values'
                    )
                    ? $attribute
                        ->values
                        ->sortBy('id')
                        ->map(
                            fn (
                                AttributeValue
                                $value
                            ) =>
                                $this
                                    ->serializeValue(
                                        $value
                                    )
                        )
                        ->values()
                        ->all()
                    : [],

            'created_at' =>
                $attribute->created_at,

            'updated_at' =>
                $attribute->updated_at,
        ];
    }

    protected function serializeValue(
        AttributeValue $value
    ): array {
        return [
            'id' =>
                $value->id,

            'attribute_id' =>
                $value
                    ->attribute_id,

            'value' =>
                $this
                    ->decodeTranslations(
                        $value
                            ->getRawOriginal(
                                'value'
                            )
                    ),

            'created_at' =>
                $value->created_at,

            'updated_at' =>
                $value->updated_at,
        ];
    }

    protected function generateSlug(
        string $name,
        ?ProductAttribute $ignore = null
    ): string {
        $baseSlug =
            Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug =
                Str::lower(
                    Str::random(12)
                );
        }

        $slug =
            $baseSlug;

        $suffix = 2;

        while (
            ProductAttribute::query()
                ->where(
                    'slug',
                    $slug
                )
                ->when(
                    $ignore !== null,
                    fn ($query) =>
                        $query->where(
                            'id',
                            '!=',
                            $ignore->id
                        )
                )
                ->exists()
        ) {
            $slug =
                $baseSlug .
                '-' .
                $suffix;

            $suffix++;
        }

        return $slug;
    }
}