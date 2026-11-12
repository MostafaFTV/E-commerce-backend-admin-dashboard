<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $permissions = $this->roles
            ->flatMap(
                function ($role) {
                    return $role->permissions
                        ->pluck('slug');
                }
            )
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'id' => $this->id,

            'first_name' =>
                $this->first_name,

            'last_name' =>
                $this->last_name,

            'email' =>
                $this->email,

            'phone' =>
                $this->phone,

            'status' =>
                $this->status?->value,

            'roles' =>
                $this->roles->map(
                    function ($role) {
                        return [
                            'name' =>
                                $role->name,

                            'slug' =>
                                $role->slug,
                        ];
                    }
                )->values(),

            'permissions' =>
                $permissions,

            'created_at' =>
                $this->created_at,

            'updated_at' =>
                $this->updated_at,
        ];
    }
}
