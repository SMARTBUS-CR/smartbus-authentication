<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'roles' => RoleResource::collection($this->resource->roles),

            /**
             * User's permissions.
             *
             * @example ["permission-1", "permission-2"]
             *
             * @see User::getAllPermissions()
             */
            'permissions' => $this->resource->getAllPermissions()->pluck('name')->values(),
        ];
    }
}
