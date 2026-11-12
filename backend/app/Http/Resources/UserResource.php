<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'first_name' => $this->first_name,

            'last_name' => $this->last_name,

            'email' => $this->email,

            'phone' => $this->phone,

            'status' => $this->status?->value,


            'roles' => $this->whenLoaded(
                'roles',
                function () {
                    return $this->roles->map(function ($role) {
                        return [
                            'name' => $role->name,
                            'slug' => $role->slug,
                        ];
                    });
                }
            ),


            'created_at' =>
                $this->created_at,

            'updated_at' =>
                $this->updated_at,

        ];
    }
}