<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
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
                    $roleQuery->whereIn(
                        'slug',
                        [
                            'admin',
                            'accountant',
                            'support',
                        ]
                    );
                }
            )
            ->with([
                'roles',
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

            $query->where(function ($userQuery) use ($search) {
                $userQuery
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

        /*
        |--------------------------------------------------------------------------
        | Role Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('role')) {
            $role = $request
                ->string('role')
                ->toString();

            if (
                in_array(
                    $role,
                    [
                        'admin',
                        'accountant',
                        'support',
                    ],
                    true
                )
            ) {
                $query->whereHas(
                    'roles',
                    function ($roleQuery) use ($role) {
                        $roleQuery->where(
                            'slug',
                            $role
                        );
                    }
                );
            }
        }

        $users = $query->paginate(20);

        /*
        |--------------------------------------------------------------------------
        | Transform Roles
        |--------------------------------------------------------------------------
        */

        $users->through(
            fn (User $user) => $this->transform(
                $user
            )
        );

        return JsonResource::collection(
            $users
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function show(
        User $user
    ): JsonResource {
        $this->ensureManagedStaff(
            $user
        );

        $user->load([
            'roles',
        ]);

        return JsonResource::make([
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status?->value,
            'roles' => $user->roles
                ->pluck('slug')
                ->values()
                ->all(),
            'email_verified_at' => $user->email_verified_at,
            'phone_verified_at' => $user->phone_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request,
        ActivityLogService $activityLogService
    ): JsonResponse {
        $validated = $request->validate([
            'first_name' => [
                'required',
                'string',
                'max:100',
            ],

            'last_name' => [
                'required',
                'string',
                'max:100',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
                'unique:users,phone',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],

            'status' => [
                'required',
                Rule::enum(
                    UserStatus::class
                ),
            ],

            'role' => [
                'required',
                Rule::in([
                    'admin',
                    'accountant',
                    'support',
                ]),
            ],
        ]);

        $role = Role::query()
            ->where(
                'slug',
                $validated['role']
            )
            ->first();

        if (! $role) {
            throw ValidationException::withMessages([
                'role' => [
                    'The selected role does not exist.',
                ],
            ]);
        }

        $user = DB::transaction(
            function () use (
                $validated,
                $role
            ): User {
                $user = User::create([
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'password' => $validated['password'],
                    'status' => $validated['status'],
                ]);

                $user->roles()->sync([
                    $role->id,
                ]);

                return $user;
            }
        );

        $user->load([
            'roles',
        ]);

        $activityLogService->logCreated(
            model: $user,
            action: 'users.created',
            description: 'Staff user created.',
            properties: [
                'role' => $role->slug,
            ],
            request: $request
        );

        return response()->json([
            'message' => 'Staff user created successfully.',
            'data' => $this->transform(
                $user
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
        User $user,
        ActivityLogService $activityLogService
    ): JsonResource {
        /*
        |--------------------------------------------------------------------------
        | Self Protection
        |--------------------------------------------------------------------------
        */

        if ($request->user()->id === $user->id) {
            if (
                $request->filled('status') &&
                $request->input('status') !== UserStatus::ACTIVE->value
            ) {
                throw ValidationException::withMessages([
                    'status' => [
                        'You cannot deactivate or suspend your own account.',
                    ],
                ]);
            }

            if ($request->filled('role')) {
                throw ValidationException::withMessages([
                    'role' => [
                        'You cannot change your own role.',
                    ],
                ]);
            }
        }

        $this->ensureManagedStaff(
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
                'required',
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

            'password' => [
                'sometimes',
                'required',
                'string',
                'min:8',
                'confirmed',
            ],

            'status' => [
                'sometimes',
                'required',
                Rule::enum(
                    UserStatus::class
                ),
            ],

            'role' => [
                'sometimes',
                'required',
                Rule::in([
                    'admin',
                    'accountant',
                    'support',
                ]),
            ],
        ]);

        $role = null;

        $oldRoles = $user
            ->roles()
            ->pluck('slug')
            ->values()
            ->all();

        if (array_key_exists('role', $validated)) {
            $role = Role::query()
                ->where(
                    'slug',
                    $validated['role']
                )
                ->first();

            if (! $role) {
                throw ValidationException::withMessages([
                    'role' => [
                        'The selected role does not exist.',
                    ],
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Token Revocation on Account Deactivation
        |--------------------------------------------------------------------------
        |
        | Any transition from active to inactive or suspended immediately
        | invalidates all existing Sanctum sessions for the staff user.
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

        DB::transaction(
            function () use (
                $user,
                $validated,
                $role,
                $shouldRevokeTokens
            ): void {
                $userData = collect($validated)
                    ->except([
                        'role',
                    ])
                    ->all();

                $user->update(
                    $userData
                );

                if ($role) {
                    $user->roles()->sync([
                        $role->id,
                    ]);
                }

                if ($shouldRevokeTokens) {
                    $user->tokens()->delete();
                }
            }
        );

        $user->load([
            'roles',
        ]);

        $newRoles = $user->roles
            ->pluck('slug')
            ->values()
            ->all();

        $roleChanged =
            $oldRoles !== $newRoles;

        $activityProperties = [];

        if ($roleChanged) {
            $activityProperties['roles'] = [
                'old' => $oldRoles,
                'new' => $newRoles,
            ];
        }

        if (
            array_key_exists(
                'password',
                $validated
            )
        ) {
            $activityProperties['password_changed'] = true;
        }

        if (
            $shouldRevokeTokens
        ) {
            $activityProperties['tokens_revoked'] = true;
        }

        $activityLogService->logModelChange(
            action: 'users.updated',
            model: $user,
            description: 'Staff user updated.',
            properties: $activityProperties,
            request: $request
        );

        return JsonResource::make([
            'message' => 'Staff user updated successfully.',
            'data' => $this->transform(
                $user->fresh([
                    'roles',
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
        Request $request,
        User $user,
        ActivityLogService $activityLogService
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Self Protection
        |--------------------------------------------------------------------------
        */

        if (
            $request->user()->id === $user->id
        ) {
            throw ValidationException::withMessages([
                'user' => [
                    'You cannot delete your own account.',
                ],
            ]);
        }

        $this->ensureManagedStaff(
            $user
        );

        $roles = $user
            ->roles()
            ->pluck('slug')
            ->values()
            ->all();

        DB::transaction(
            function () use ($user): void {
                $user->tokens()->delete();

                $user->roles()->detach();

                $user->delete();
            }
        );

        $activityLogService->logDeleted(
            model: $user,
            action: 'users.deleted',
            description: 'Staff user deleted.',
            properties: [
                'roles' => $roles,
            ],
            request: $request
        );

        return response()->json([
            'message' => 'Staff user deleted successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function ensureManagedStaff(
        User $user
    ): void {
        abort_unless(
            $user->hasAnyRole([
                'admin',
                'accountant',
                'support',
            ]),
            404
        );
    }

    protected function transform(
        User $user
    ): array {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status?->value,
            'roles' => $user->roles
                ->pluck('slug')
                ->values()
                ->all(),
            'email_verified_at' => $user->email_verified_at,
            'phone_verified_at' => $user->phone_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
