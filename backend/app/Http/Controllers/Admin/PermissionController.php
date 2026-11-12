<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PermissionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | System Permissions
    |--------------------------------------------------------------------------
    |
    | These permissions are part of the application's authorization contract.
    | Their slugs are referenced directly by middleware and business logic.
    |
    | System permissions:
    | - cannot be renamed at slug level
    | - cannot be deleted
    | - may still have their display name and description updated
    |
    |--------------------------------------------------------------------------
    */

    protected const SYSTEM_PERMISSIONS = [
        'users.view',
        'users.manage',

        'roles.manage',
        'permissions.manage',

        'categories.view',
        'categories.create',
        'categories.update',
        'categories.delete',

        'products.view',
        'products.create',
        'products.update',
        'products.delete',

        'collections.view',
        'collections.create',
        'collections.update',
        'collections.delete',

        'inventory.view',
        'inventory.manage',

        'orders.view',
        'orders.manage',
        'orders.update_status',
        'orders.cancel',
        'orders.return',

        'customers.view',
        'customers.manage',

        'cms.manage',
        'menus.manage',
        'homepage.manage',

        'stores.view',
        'stores.create',
        'stores.update',
        'stores.delete',

        'contact_messages.view',

        'activity_logs.view',

        'shipping_methods.view',
        'shipping_methods.create',
        'shipping_methods.update',
        'shipping_methods.delete',
    ];

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index(): AnonymousResourceCollection
    {
        $permissions = Permission::query()
            ->withCount('roles')
            ->orderBy('id')
            ->paginate(50);

        return JsonResource::collection(
            $permissions
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function show(
        Permission $permission
    ): JsonResource {
        $permission->load([
            'roles',
        ]);

        $permission->loadCount([
            'roles',
        ]);

        return JsonResource::make(
            $this->transformPermission(
                $permission
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
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'slug' => [
                'required',
                'string',
                'max:150',
                'regex:/^[a-z0-9_]+(\.[a-z0-9_]+)*$/',
                'unique:permissions,slug',
            ],

            'description' => [
                'nullable',
                'string',
            ],
        ]);

        $permission = Permission::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' =>
                $validated['description'] ?? null,
        ]);

        $permission->loadCount([
            'roles',
        ]);

        return response()->json([
            'message' => 'Permission created successfully.',
            'data' => $this->transformPermission(
                $permission
            ),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        Permission $permission
    ): JsonResource {
        /*
        |--------------------------------------------------------------------------
        | System Permission Protection
        |--------------------------------------------------------------------------
        |
        | System permission slugs are part of the application's authorization
        | contract and therefore cannot be changed.
        |
        |--------------------------------------------------------------------------
        */

        if (
            $this->isSystemPermission(
                $permission
            ) &&
            $request->has('slug') &&
            $request->input('slug') !== $permission->slug
        ) {
            throw ValidationException::withMessages([
                'slug' => [
                    'System permission slugs cannot be changed.',
                ],
            ]);
        }

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],

            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                'regex:/^[a-z0-9_]+(\.[a-z0-9_]+)*$/',
                Rule::unique(
                    'permissions',
                    'slug'
                )->ignore($permission->id),
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ]);

        DB::transaction(
            function () use (
                $permission,
                $validated
            ): void {
                $permission->update(
                    $validated
                );
            }
        );

        $permission->load([
            'roles',
        ]);

        $permission->loadCount([
            'roles',
        ]);

        return JsonResource::make([
            'message' => 'Permission updated successfully.',
            'data' => $this->transformPermission(
                $permission
            ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Permission $permission
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | System Permission Protection
        |--------------------------------------------------------------------------
        |
        | System permissions must remain available because application routes
        | and authorization checks depend on their stable slugs.
        |
        |--------------------------------------------------------------------------
        */

        if (
            $this->isSystemPermission(
                $permission
            )
        ) {
            throw ValidationException::withMessages([
                'permission' => [
                    'System permissions cannot be deleted.',
                ],
            ]);
        }

        if (
            $permission->roles()->exists()
        ) {
            throw ValidationException::withMessages([
                'permission' => [
                    'This permission cannot be deleted while it is assigned to a role.',
                ],
            ]);
        }

        DB::transaction(
            function () use (
                $permission
            ): void {
                $permission->delete();
            }
        );

        return response()->json([
            'message' => 'Permission deleted successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function isSystemPermission(
        Permission $permission
    ): bool {
        return in_array(
            $permission->slug,
            self::SYSTEM_PERMISSIONS,
            true
        );
    }

    protected function transformPermission(
        Permission $permission
    ): array {
        return [
            'id' => $permission->id,
            'name' => $permission->name,
            'slug' => $permission->slug,
            'description' => $permission->description,
            'roles_count' => $permission->roles_count ?? 0,

            'roles' => $permission->relationLoaded('roles')
                ? $permission->roles
                    ->map(
                        fn ($role) => [
                            'id' => $role->id,
                            'name' => $role->name,
                            'slug' => $role->slug,
                        ]
                    )
                    ->values()
                    ->all()
                : [],

            'created_at' => $permission->created_at,
            'updated_at' => $permission->updated_at,
        ];
    }
}