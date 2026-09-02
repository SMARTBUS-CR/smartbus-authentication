<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class UserResource extends JsonApiResource
{
    /**
     * Get the resource's attributes.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->resource->name,
            'email' => $this->resource->email,

            /**
             * User's permissions.
             *
             * @example ["permission-1", "permission-2"]
             *
             * @see User::getAllPermissions()
             */
            'permissions' => $this->resource
                ->getAllPermissions()
                ->pluck('name')
                ->values(),
        ];
    }

    public $relationships = [
        'roles',
    ];

    /**
     * Get the resource's relationships.
     */
    public function toRelationships(Request $request): array
    {
        return [
            'roles' => fn () => RoleResource::collection($this->whenLoaded('roles')),
        ];
    }
}
