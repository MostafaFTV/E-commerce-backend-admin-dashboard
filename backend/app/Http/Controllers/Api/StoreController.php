<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class StoreController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $validated = $request->validate([
            'per_page' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:48',
            ],
            'page' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:10000',
            ],

            'locale' => [
                'sometimes',
                'nullable',
                'string',
                'in:fa,en',
            ],
        ]);

        $perPage = $validated['per_page'] ?? 20;
        $page = $validated['page'] ?? null;

        $query = Store::query()
            ->where(
                'is_active',
                true
            )
            ->orderBy(
                'sort_order'
            )
            ->orderBy(
                'id'
            );

        if ($page !== null) {
            $stores = $query->paginate(
                $perPage,
                ['*'],
                'page',
                $page
            );
        } else {
            $stores = $query->paginate(
                $perPage
            );
        }

        return StoreResource::collection(
            $stores
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function show(
        string $slug
    ): StoreResource {
        /*
         * Public store slugs must use the canonical
         * lowercase URL-safe format.
         */
        if (
            ! preg_match(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $slug
            )
        ) {
            throw ValidationException::withMessages([
                'slug' => [
                    'The selected slug is invalid.',
                ],
            ]);
        }

        $store = Store::query()
            ->where(
                'slug',
                $slug
            )
            ->where(
                'is_active',
                true
            )
            ->firstOrFail();

        return new StoreResource(
            $store
        );
    }
}