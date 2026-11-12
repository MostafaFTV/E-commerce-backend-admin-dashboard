<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index(): AnonymousResourceCollection
    {
        $roles = Role::query()
            ->withCount('users')
            ->with([
                'permissions',
            ])
            ->orderBy('id')
            ->paginate(20);

        return JsonResource::collection(
            $roles
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function show(
        Role $role
    ): JsonResource {
        $role->load([
            'permissions',
        ]);

        $role->loadCount([
            'users',
        ]);

        return JsonResource::make(
            $this->transformRole(
                $role
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
                'max:100',
            ],

            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_]+$/',
                'unique:roles,slug',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'permission_ids' => [
                'sometimes',
                'array',
            ],

            'permission_ids.*' => [
                'integer',
                'distinct',
                'exists:permissions,id',
            ],
        ]);

        $role = DB::transaction(
            function () use (
                $validated
            ): Role {
                $role = Role::create([
                    'name' => $validated['name'],
                    'slug' => $validated['slug'],
                    'description' =>
                        $validated['description'] ?? null,
                ]);

                if (
                    array_key_exists(
                        'permission_ids',
                        $validated
                    )
                ) {
                    $role->permissions()->sync(
                        $validated['permission_ids']
                    );
                }

                return $role;
            }
        );

        $role->load([
            'permissions',
        ]);

        $role->loadCount([
            'users',
        ]);

        return response()->json([
            'message' => 'Role created successfully.',
            'data' => $this->transformRole(
                $role
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
        Role $role
    ): JsonResource {
        $this->ensureRoleCanBeModified(
            $role,
            $request
        );

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],

            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique(
                    'roles',
                    'slug'
                )->ignore($role->id),
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'permission_ids' => [
                'sometimes',
                'array',
            ],

            'permission_ids.*' => [
                'integer',
                'distinct',
                'exists:permissions,id',
            ],
        ]);

        if (
            $role->slug === 'customer' &&
            array_key_exists(
                'permission_ids',
                $validated
            )
        ) {
            throw ValidationException::withMessages([
                'permission_ids' => [
                    'Customer role permissions cannot be changed.',
                ],
            ]);
        }

        DB::transaction(
            function () use (
                $role,
                $validated
            ): void {
                $role->update(
                    collect($validated)
                        ->except(
                            'permission_ids'
                        )
                        ->all()
                );

                if (
                    array_key_exists(
                        'permission_ids',
                        $validated
                    )
                ) {
                    $role->permissions()->sync(
                        $validated['permission_ids']
                    );
                }
            }
        );

        $role->load([
            'permissions',
        ]);

        $role->loadCount([
            'users',
        ]);

        return JsonResource::make([
            'message' => 'Role updated successfully.',
            'data' => $this->transformRole(
                $role
            ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Role $role
    ): JsonResponse {
        if (
            $this->isSystemRole(
                $role
            )
        ) {
            throw ValidationException::withMessages([
                'role' => [
                    'System roles cannot be deleted.',
                ],
            ]);
        }

        if (
            $role->users()->exists()
        ) {
            throw ValidationException::withMessages([
                'role' => [
                    'This role cannot be deleted while users are assigned to it.',
                ],
            ]);
        }

        DB::transaction(
            function () use (
                $role
            ): void {
                $role->permissions()->detach();

                $role->delete();
            }
        );

        return response()->json([
            'message' => 'Role deleted successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function ensureRoleCanBeModified(
        Role $role,
        Request $request
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Super Admin Role Protection
        |--------------------------------------------------------------------------
        |
        | The super_admin role must never be modified through normal CRUD.
        |
        |--------------------------------------------------------------------------
        */

        if (
            $role->slug === 'super_admin'
        ) {
            throw ValidationException::withMessages([
                'role' => [
                    'The super_admin role cannot be modified.',
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent Dangerous System Role Changes
        |--------------------------------------------------------------------------
        |
        | System role slugs are part of the authorization contract and must
        | remain stable.
        |
        |--------------------------------------------------------------------------
        */

        if (
            $this->isSystemRole($role) &&
            $request->has('slug') &&
            $request->input('slug') !== $role->slug
        ) {
            throw ValidationException::withMessages([
                'slug' => [
                    'System role slugs cannot be changed.',
                ],
            ]);
        }
    }

    protected function isSystemRole(
        Role $role
    ): bool {
        return in_array(
            $role->slug,
            [
                'super_admin',
                'admin',
                'accountant',
                'support',
                'customer',
            ],
            true
        );
    }

    protected function transformRole(
        Role $role
    ): array {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'users_count' => $role->users_count ?? 0,

            'permissions' => $role->permissions
                ->map(
                    fn (Permission $permission) => [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'slug' => $permission->slug,
                        'description' => $permission->description,
                    ]
                )
                ->values()
                ->all(),

            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
        ];
    }
}