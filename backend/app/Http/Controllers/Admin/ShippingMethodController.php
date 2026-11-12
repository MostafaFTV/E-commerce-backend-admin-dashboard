<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShippingMethodResource;
use App\Models\ShippingMethod;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ShippingMethodController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $shippingMethods = ShippingMethod::query()
            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $search = $request
                        ->string('search')
                        ->toString();

                    $query->where(function ($searchQuery) use ($search) {
                        $searchQuery
                            ->where(
                                'name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'description',
                                'like',
                                "%{$search}%"
                            );
                    });
                }
            )
            ->when(
                $request->filled('is_active'),
                function ($query) use ($request) {
                    $query->where(
                        'is_active',
                        $request->boolean('is_active')
                    );
                }
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(
                min(
                    max(
                        $request->integer(
                            'per_page',
                            20
                        ),
                        1
                    ),
                    100
                )
            );

        return ShippingMethodResource::collection(
            $shippingMethods
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function show(
        ShippingMethod $shippingMethod
    ): ShippingMethodResource {
        return new ShippingMethodResource(
            $shippingMethod
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request
    ): JsonResponse {
        $validated = $this->validateShippingMethod(
            $request
        );

        $shippingMethod = ShippingMethod::create([
            'name' =>
                $validated['name'],

            'description' =>
                $validated['description']
                ?? null,

            'price' =>
                $validated['price']
                ?? 0,

            'estimated_days' =>
                $validated['estimated_days']
                ?? null,

            'is_active' =>
                $validated['is_active'],

            'sort_order' =>
                $validated['sort_order']
                ?? 0,
        ]);

        $this->activityLogService->log(
            action: 'shipping_methods.created',
            description: 'Shipping method created.',
            properties: [
                'shipping_method' => [
                    'id' => $shippingMethod->id,
                    'name' => $shippingMethod->name,
                ],
            ],
            subject: $shippingMethod,
            request: $request
        );

        return (new ShippingMethodResource(
            $shippingMethod
        ))
            ->response()
            ->setStatusCode(201);
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        ShippingMethod $shippingMethod
    ): ShippingMethodResource {
        $validated = $this->validateShippingMethod(
            $request,
            true
        );

        $updateData = [];

        foreach (
            [
                'name',
                'description',
                'price',
                'estimated_days',
                'is_active',
                'sort_order',
            ] as $field
        ) {
            if (
                array_key_exists(
                    $field,
                    $validated
                )
            ) {
                $updateData[$field] =
                    $validated[$field];
            }
        }

        if ($updateData !== []) {
            $shippingMethod->update(
                $updateData
            );
        }

        $changes = $shippingMethod->getChanges();

        $shippingMethod->refresh();

        if ($changes !== []) {
            $this->activityLogService->log(
                action: 'shipping_methods.updated',
                description: 'Shipping method updated.',
                properties: [
                    'shipping_method' => [
                        'id' => $shippingMethod->id,
                        'name' => $shippingMethod->name,
                    ],
                    'changed_fields' => array_keys(
                        $changes
                    ),
                    'changes' => $changes,
                ],
                subject: $shippingMethod,
                request: $request
            );
        }

        return new ShippingMethodResource(
            $shippingMethod
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function destroy(
        ShippingMethod $shippingMethod
    ): JsonResponse {
        if (
            $shippingMethod
                ->orders()
                ->exists()
        ) {
            $shippingMethod->update([
                'is_active' => false,
            ]);

            $this->activityLogService->log(
                action: 'shipping_methods.deactivated',
                description: 'Shipping method deactivated because it has historical orders.',
                properties: [
                    'shipping_method' => [
                        'id' => $shippingMethod->id,
                        'name' => $shippingMethod->name,
                    ],
                    'reason' => 'historical_orders',
                ],
                subject: $shippingMethod
            );

            return response()->json([
                'message' =>
                    'This shipping method has historical orders and was deactivated instead of deleted.',
                'deleted' => false,
                'deactivated' => true,
            ]);
        }

        $shippingMethodMetadata = [
            'shipping_method' => [
                'id' => $shippingMethod->id,
                'name' => $shippingMethod->name,
            ],
        ];

        $shippingMethod->delete();

        $this->activityLogService->log(
            action: 'shipping_methods.deleted',
            description: 'Shipping method deleted.',
            properties: $shippingMethodMetadata,
            subject: $shippingMethod
        );

        return response()->json([
            'message' =>
                'Shipping method deleted successfully.',
            'deleted' => true,
            'deactivated' => false,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    protected function validateShippingMethod(
        Request $request,
        bool $isUpdate = false
    ): array {
        return $request->validate([
            'name' => [
                $isUpdate
                    ? 'sometimes'
                    : 'required',

                'string',
                'max:150',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'price' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],

            'estimated_days' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'is_active' => [
                $isUpdate
                    ? 'sometimes'
                    : 'required',

                'boolean',
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
                'max:4294967295',
            ],
        ]);
    }
}
