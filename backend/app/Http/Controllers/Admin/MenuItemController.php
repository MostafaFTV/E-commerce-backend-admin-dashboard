<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MenuItemType;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Stevebauman\Purify\Facades\Purify;

class MenuItemController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request,
        Menu $menu
    ): JsonResponse {
        $validated = $request->validate([
            'parent_id' => [
                'nullable',
                'integer',
                'exists:menu_items,id',
            ],

            'title' => [
                'required',
                'array',
            ],

            'title.en' => [
                'required',
                'string',
                'max:150',
            ],

            'title.fa' => [
                'required',
                'string',
                'max:150',
            ],

            'type' => [
                'required',
                Rule::enum(
                    MenuItemType::class
                ),
            ],

            'url' => [
                'nullable',
                'string',
                'max:500',

                function (
                    string $attribute,
                    mixed $value,
                    Closure $fail
                ): void {
                    $this->validateUrlValue(
                        $value,
                        $fail
                    );
                },
            ],

            'category_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
            ],

            'product_id' => [
                'nullable',
                'integer',
                'exists:products,id',
            ],

            'collection_id' => [
                'nullable',
                'integer',
                'exists:collections,id',
            ],

            'page_id' => [
                'nullable',
                'integer',
                'exists:pages,id',
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $this->validateParent(
            $menu,
            $validated['parent_id'] ?? null
        );

        $this->validateTarget(
            $validated
        );

        $validated['title'] =
            $this->sanitizeTranslations(
                $validated['title']
            );

        if (
            array_key_exists(
                'url',
                $validated
            )
            && $validated['url'] !== null
        ) {
            $validated['url'] =
                trim(
                    $validated['url']
                );
        }

        $item = DB::transaction(
            fn () => $menu->items()->create([
                ...$validated,

                'sort_order' =>
                    $validated['sort_order']
                    ?? 0,

                'is_active' =>
                    $validated['is_active']
                    ?? true,
            ])
        );

        $item->load([
            'category',
            'product',
            'collection',
            'page',
            'parent',
        ]);

        return response()->json([
            'message' =>
                'Menu item created successfully.',

            'data' =>
                $this->transformItem(
                    $item
                ),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function show(
        Menu $menu,
        MenuItem $item
    ): JsonResource {
        $this->ensureItemBelongsToMenu(
            $menu,
            $item
        );

        $item->load([
            'category',
            'product',
            'collection',
            'page',
            'parent',
        ]);

        return JsonResource::make(
            $this->transformItem(
                $item
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        Menu $menu,
        MenuItem $item
    ): JsonResource {
        $this->ensureItemBelongsToMenu(
            $menu,
            $item
        );

        $validated = $request->validate([
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:menu_items,id',
            ],

            'title' => [
                'sometimes',
                'required',
                'array',
            ],

            'title.en' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],

            'title.fa' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],

            'type' => [
                'sometimes',
                'required',
                Rule::enum(
                    MenuItemType::class
                ),
            ],

            'url' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',

                function (
                    string $attribute,
                    mixed $value,
                    Closure $fail
                ): void {
                    $this->validateUrlValue(
                        $value,
                        $fail
                    );
                },
            ],

            'category_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:categories,id',
            ],

            'product_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:products,id',
            ],

            'collection_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:collections,id',
            ],

            'page_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:pages,id',
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $currentTitle =
            $this->getCurrentTranslations(
                $item,
                'title'
            );

        if (
            array_key_exists(
                'title',
                $validated
            )
        ) {
            $validated['title'] =
                $this->sanitizeTranslations(
                    array_merge(
                        $currentTitle,
                        $validated['title']
                    )
                );
        }

        if (
            array_key_exists(
                'url',
                $validated
            )
            && $validated['url'] !== null
        ) {
            $validated['url'] =
                trim(
                    $validated['url']
                );
        }

        $merged = array_merge(
            [
                'parent_id' =>
                    $item->parent_id,

                'type' =>
                    $item->type?->value,

                'url' =>
                    $item->url,

                'category_id' =>
                    $item->category_id,

                'product_id' =>
                    $item->product_id,

                'collection_id' =>
                    $item->collection_id,

                'page_id' =>
                    $item->page_id,
            ],

            $validated
        );

        $this->validateParent(
            $menu,
            $merged['parent_id'] ?? null,
            $item
        );

        $this->validateTarget(
            $merged
        );

        DB::transaction(
            fn () => $item->update(
                $validated
            )
        );

        return JsonResource::make([
            'message' =>
                'Menu item updated successfully.',

            'data' =>
                $this->transformItem(
                    $item->fresh([
                        'category',
                        'product',
                        'collection',
                        'page',
                        'parent',
                    ])
                ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Menu $menu,
        MenuItem $item
    ): JsonResponse {
        $this->ensureItemBelongsToMenu(
            $menu,
            $item
        );

        if (
            $item->children()->exists()
        ) {
            return response()->json([
                'message' =>
                    'This menu item cannot be deleted while it contains child items.',
            ], 422);
        }

        DB::transaction(
            fn () => $item->delete()
        );

        return response()->json([
            'message' =>
                'Menu item deleted successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Menu Ownership / IDOR Protection
    |--------------------------------------------------------------------------
    */

    protected function ensureItemBelongsToMenu(
        Menu $menu,
        MenuItem $item
    ): void {
        if (
            (int) $item->menu_id !==
            (int) $menu->id
        ) {
            abort(404);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Parent Validation
    |--------------------------------------------------------------------------
    */

    protected function validateParent(
        Menu $menu,
        ?int $parentId,
        ?MenuItem $currentItem = null
    ): void {
        if (
            $parentId === null
        ) {
            return;
        }

        if (
            $currentItem
            && (int) $currentItem->id ===
                (int) $parentId
        ) {
            throw ValidationException::withMessages([
                'parent_id' => [
                    'A menu item cannot be its own parent.',
                ],
            ]);
        }

        $parent = MenuItem::query()
            ->whereKey($parentId)
            ->first();

        if (
            ! $parent
            || (int) $parent->menu_id !==
                (int) $menu->id
        ) {
            throw ValidationException::withMessages([
                'parent_id' => [
                    'The selected parent item does not belong to this menu.',
                ],
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Target Validation
    |--------------------------------------------------------------------------
    */

    protected function validateTarget(
        array $data
    ): void {
        $type =
            $data['type'] ?? null;

        if (
            ! $type
        ) {
            return;
        }

        $type =
            $type instanceof MenuItemType
                ? $type
                : MenuItemType::tryFrom(
                    $type
                );

        if (
            ! $type
        ) {
            return;
        }

        $fields = [
            'url',
            'category_id',
            'product_id',
            'collection_id',
            'page_id',
        ];

        $requiredField = match ($type) {
            MenuItemType::URL =>
                'url',

            MenuItemType::CATEGORY =>
                'category_id',

            MenuItemType::PRODUCT =>
                'product_id',

            MenuItemType::COLLECTION =>
                'collection_id',

            MenuItemType::PAGE =>
                'page_id',
        };

        if (
            ! array_key_exists(
                $requiredField,
                $data
            )
            || blank(
                $data[$requiredField]
            )
        ) {
            throw ValidationException::withMessages([
                $requiredField => [
                    "The {$requiredField} field is required for menu item type {$type->value}.",
                ],
            ]);
        }

        foreach (
            $fields as $field
        ) {
            if (
                $field !== $requiredField
                && array_key_exists(
                    $field,
                    $data
                )
                && filled(
                    $data[$field]
                )
            ) {
                throw ValidationException::withMessages([
                    $field => [
                        "The {$field} field must be empty when menu item type is {$type->value}.",
                    ],
                ]);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | URL Validation
    |--------------------------------------------------------------------------
    */

    protected function validateUrlValue(
        mixed $value,
        Closure $fail
    ): void {
        if (
            $value === null
        ) {
            return;
        }

        $value = trim(
            (string) $value
        );

        if (
            $value === ''
        ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Control / Dangerous Characters
        |--------------------------------------------------------------------------
        */

        if (
            preg_match(
                '/[\x00-\x1F\x7F\\\\<>"]/',
                $value
            )
        ) {
            $fail(
                'The menu item URL contains invalid characters.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Protocol-relative URLs
        |--------------------------------------------------------------------------
        */

        if (
            str_starts_with(
                $value,
                '//'
            )
        ) {
            $fail(
                'Protocol-relative URLs are not allowed.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Internal Paths
        |--------------------------------------------------------------------------
        */

        if (
            str_starts_with(
                $value,
                '/'
            )
        ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | External URLs
        |--------------------------------------------------------------------------
        */

        $scheme =
            parse_url(
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
                'The menu item URL must be an internal path or an HTTP(S) URL.'
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
                'The menu item URL must be a valid URL.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Translation Sanitization
    |--------------------------------------------------------------------------
    */

    protected function sanitizeTranslations(
        mixed $value
    ): array {
        if (
            ! is_array($value)
        ) {
            return [];
        }

        $sanitized = [];

        foreach (
            [
                'en',
                'fa',
            ] as $locale
        ) {
            if (
                ! array_key_exists(
                    $locale,
                    $value
                )
            ) {
                continue;
            }

            if (
                $value[$locale] === null
            ) {
                $sanitized[$locale] =
                    null;

                continue;
            }

            $sanitized[$locale] =
                trim(
                    strip_tags(
                        Purify::config('banner')->clean(
                            (string) $value[$locale]
                        )
                    )
                );
        }

        return $sanitized;
    }

    /*
    |--------------------------------------------------------------------------
    | Current Translation Values
    |--------------------------------------------------------------------------
    */

    protected function getCurrentTranslations(
        MenuItem $item,
        string $attribute
    ): array {
        $raw =
            $item->getRawOriginal(
                $attribute
            );

        if (
            $raw === null
        ) {
            return [];
        }

        if (
            is_array($raw)
        ) {
            return $raw;
        }

        $decoded =
            json_decode(
                $raw,
                true
            );

        if (
            ! is_array($decoded)
        ) {
            return [];
        }

        return $decoded;
    }

    /*
    |--------------------------------------------------------------------------
    | Response Transformation
    |--------------------------------------------------------------------------
    */

    protected function transformItem(
        MenuItem $item
    ): array {
        return [
            'id' =>
                $item->id,

            'menu_id' =>
                $item->menu_id,

            'parent_id' =>
                $item->parent_id,

            'locale' =>
                app()->getLocale(),

            'direction' =>
                request()
                    ->attributes
                    ->get(
                        'direction',
                        'ltr'
                    ),

            'title' =>
                $item->title,

            'type' =>
                $item->type?->value,

            'url' =>
                $item->url,

            'category_id' =>
                $item->category_id,

            'product_id' =>
                $item->product_id,

            'collection_id' =>
                $item->collection_id,

            'page_id' =>
                $item->page_id,

            'sort_order' =>
                $item->sort_order,

            'is_active' =>
                (bool) $item->is_active,

            'created_at' =>
                $item->created_at,

            'updated_at' =>
                $item->updated_at,
        ];
    }
}
