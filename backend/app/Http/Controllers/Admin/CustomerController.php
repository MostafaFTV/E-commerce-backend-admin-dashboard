<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $query = User::query()
            ->whereHas(
                'roles',
                function ($roleQuery) {
                    $roleQuery->where(
                        'slug',
                        'customer'
                    );
                }
            )
            ->withCount([
                'addresses',
                'orders',
            ])
            ->latest('id');

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = $request
                ->string('search')
                ->toString();

            $query->where(function ($customerQuery) use ($search) {
                $customerQuery
                    ->where(
                        'first_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'last_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'email',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'phone',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Status Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('status')) {
            $status = $request
                ->string('status')
                ->toString();

            if (UserStatus::tryFrom($status)) {
                $query->where(
                    'status',
                    $status
                );
            }
        }

        return JsonResource::collection(
            $query->paginate(20)
        )->additional([
            'meta' => [
                'available_statuses' => collect(
                    UserStatus::cases()
                )
                    ->map(
                        fn (UserStatus $status) => $status->value
                    )
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function show(
        User $user
    ): JsonResource {
        $this->ensureCustomer(
            $user
        );

        $user->load([
            'roles',
            'preferredCategories',
            'addresses' => function ($query) {
                $query
                    ->orderByDesc('is_default')
                    ->orderByDesc('id');
            },
            'orders' => function ($query) {
                $query
                    ->with([
                        'shippingMethod',
                        'items',
                    ])
                    ->latest('id');
            },
        ]);

        return JsonResource::make([
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status?->value,
            'email_verified_at' => $user->email_verified_at,
            'phone_verified_at' => $user->phone_verified_at,
            'birth_date' => $user->birth_date?->format('Y-m-d'),
            'marketing_sms' => (bool) $user->marketing_sms,
            'preferred_category_ids' => $user->preferredCategories
                ->pluck('id')
                ->sort()
                ->values()
                ->all(),
            'preferred_categories' => $user->preferredCategories
                ->sortBy('id')
                ->map(function ($category) {
                    $translations = json_decode(
                        (string) $category->getRawOriginal('name'),
                        true
                    );

                    if (!is_array($translations)) {
                        $translations = [];
                    }

                    return [
                        'id' => $category->id,
                        'slug' => $category->slug,
                        'name_en' => $translations['en'] ?? null,
                        'name_fa' => $translations['fa'] ?? null,
                    ];
                })
                ->values()
                ->all(),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,

            'addresses' => $user->addresses
                ->map(
                    fn ($address) => [
                        'id' => $address->id,
                        'title' => $address->title,
                        'recipient_name' => $address->recipient_name,
                        'phone' => $address->phone,
                        'address' => $address->address,
                        'city' => $address->city,
                        'state' => $address->state,
                        'postal_code' => $address->postal_code,
                        'country' => $address->country,
                        'is_default' => (bool) $address->is_default,
                    ]
                )
                ->values()
                ->all(),

            'orders' => $user->orders
                ->map(
                    fn ($order) => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status?->value,
                        'subtotal' => (float) $order->subtotal,
                        'discount' => (float) $order->discount,
                        'shipping_cost' => (float) $order->shipping_cost,
                        'tax' => (float) $order->tax,
                        'total' => (float) $order->total,
                        'currency' => $order->currency,
                        'created_at' => $order->created_at,
                    ]
                )
                ->values()
                ->all(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Customer
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        User $user
    ): JsonResource {
        $this->ensureCustomer(
            $user
        );

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
                Rule::unique(
                    'users',
                    'email'
                )->ignore($user->id),
            ],

            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
                Rule::unique(
                    'users',
                    'phone'
                )->ignore($user->id),
            ],

            'status' => [
                'sometimes',
                'required',
                Rule::enum(
                    UserStatus::class
                ),
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Status Transition
        |--------------------------------------------------------------------------
        |
        | If a customer changes from an active account to an inactive or
        | suspended state, immediately revoke all existing Sanctum tokens.
        | This provides defense in depth in addition to EnsureActiveUser.
        |
        |--------------------------------------------------------------------------
        */

        $currentStatus = $user->status;

        $newStatus = array_key_exists(
            'status',
            $validated
        )
            ? $validated['status']
            : $currentStatus;

        $shouldRevokeTokens =
            $currentStatus === UserStatus::ACTIVE &&
            $newStatus !== UserStatus::ACTIVE;

        $user->update(
            $validated
        );

        if ($shouldRevokeTokens) {
            $user->tokens()->delete();
        }

        return JsonResource::make([
            'message' => 'Customer updated successfully.',
            'data' => $this->transformCustomer(
                $user->fresh()
            ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function ensureCustomer(
        User $user
    ): void {
        abort_unless(
            $user->hasRole('customer'),
            404
        );
    }

    protected function transformCustomer(
        User $user
    ): array {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status?->value,
            'email_verified_at' => $user->email_verified_at,
            'phone_verified_at' => $user->phone_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
