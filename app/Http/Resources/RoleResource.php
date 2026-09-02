<?php

namespace App\Http\Resources;

use App\Enums\UserRoles;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class RoleResource extends JsonApiResource
{
    /**
     * Get the resource's attributes.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $role = UserRoles::from($this->resource->name);

        return [
            /**
             * Role's DB value.
             *
             * @example "role-name"
             *
             * @see UserRoles::value
             */
            'value' => $role->value,

            /**
             * Role's display label.
             *
             * @example "Role Name"
             *
             * @see UserRoles::label()
             */
            'label' => $role->label(),
        ];
    }
}
