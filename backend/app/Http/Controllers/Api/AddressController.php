<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request
    ): JsonResource {
        $addresses = Address::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return JsonResource::collection(
            $addresses->map(
                fn (Address $address) => $this->transform(
                    $address
                )
            )
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
        $validated = $this->validateAddress(
            $request
        );

        $address = DB::transaction(
            function () use (
                $request,
                $validated
            ): Address {
                $isDefault = $validated['is_default'] ?? false;

                if (
                    $isDefault ||
                    ! Address::query()
                        ->where(
                            'user_id',
                            $request->user()->id
                        )
                        ->exists()
                ) {
                    Address::query()
                        ->where(
                            'user_id',
                            $request->user()->id
                        )
                        ->update([
                            'is_default' => false,
                        ]);

                    $isDefault = true;
                }

                return Address::create([
                    'user_id' => $request->user()->id,
                    'title' => $validated['title'] ?? null,
                    'recipient_name' => $validated['recipient_name'],
                    'phone' => $validated['phone'],
                    'address' => $validated['address'],
                    'city' => $validated['city'],
                    'state' => $validated['state'] ?? null,
                    'postal_code' => $validated['postal_code'],
                    'country' => $validated['country'],
                    'is_default' => $isDefault,
                ]);
            }
        );

        return response()->json([
            'message' => 'Address created successfully.',
            'data' => $this->transform($address),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function show(
        Request $request,
        Address $address
    ): JsonResource {
        $this->authorizeOwnership(
            $request,
            $address
        );

        return JsonResource::make(
            $this->transform($address)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        Address $address
    ): JsonResponse {
        $this->authorizeOwnership(
            $request,
            $address
        );

        $validated = $this->validateAddress(
            $request,
            true
        );

        DB::transaction(
            function () use (
                $request,
                $address,
                $validated
            ): void {
                $isDefault = $validated['is_default']
                    ?? $address->is_default;

                if ($isDefault) {
                    Address::query()
                        ->where(
                            'user_id',
                            $request->user()->id
                        )
                        ->whereKeyNot(
                            $address->id
                        )
                        ->update([
                            'is_default' => false,
                        ]);
                }

                $address->update(
                    array_merge(
                        collect($validated)
                            ->except('is_default')
                            ->all(),
                        [
                            'is_default' => $isDefault,
                        ]
                    )
                );
            }
        );

        return response()->json([
            'message' => 'Address updated successfully.',
            'data' => $this->transform(
                $address->fresh()
            ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Request $request,
        Address $address
    ): JsonResponse {
        $this->authorizeOwnership(
            $request,
            $address
        );

        DB::transaction(
            function () use (
                $request,
                $address
            ): void {
                $wasDefault = $address->is_default;

                $address->delete();

                if ($wasDefault) {
                    $replacement = Address::query()
                        ->where(
                            'user_id',
                            $request->user()->id
                        )
                        ->orderByDesc('id')
                        ->first();

                    if ($replacement) {
                        $replacement->update([
                            'is_default' => true,
                        ]);
                    }
                }
            }
        );

        return response()->json([
            'message' => 'Address deleted successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    protected function validateAddress(
        Request $request,
        bool $partial = false
    ): array {
        $required = $partial
            ? 'sometimes'
            : 'required';

        return $request->validate([
            'title' => [
                'nullable',
                'string',
                'max:100',
            ],
            'recipient_name' => [
                $required,
                'string',
                'max:200',
            ],
            'phone' => [
                $required,
                'string',
                'max:30',
            ],
            'address' => [
                $required,
                'string',
            ],
            'city' => [
                $required,
                'string',
                'max:100',
            ],
            'state' => [
                $required,
                'string',
                'max:100',
            ],
            'postal_code' => [
                $required,
                'string',
                'max:30',
            ],
            'country' => [
                $required,
                'string',
                'max:100',
            ],
            'is_default' => [
                'sometimes',
                'boolean',
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    protected function authorizeOwnership(
        Request $request,
        Address $address
    ): void {
        abort_unless(
            $address->user_id === $request->user()->id,
            404
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Response Transformer
    |--------------------------------------------------------------------------
    */

    protected function transform(
        Address $address
    ): array {
        return [
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
            'created_at' => $address->created_at,
            'updated_at' => $address->updated_at,
        ];
    }
}
