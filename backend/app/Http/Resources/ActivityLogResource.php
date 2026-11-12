<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'user' => $this->when(
                $this->relationLoaded('user'),
                function () {
                    return $this->user
                        ? [
                            'id' => $this->user->id,
                            'first_name' => $this->user->first_name,
                            'last_name' => $this->user->last_name,
                            'email' => $this->user->email,
                            'roles' => $this->user->roles
                                ->pluck('slug')
                                ->values()
                                ->all(),
                        ]
                        : null;
                }
            ),

            'action' =>
                $this->action,

            'description' =>
                $this->description,

            'properties' =>
                $this->properties,

            'ip_address' =>
                $this->ip_address,

            'user_agent' =>
                $this->user_agent,

            'created_at' =>
                $this->created_at,
        ];
    }
}
