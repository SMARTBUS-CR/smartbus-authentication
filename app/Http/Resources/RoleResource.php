<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Spatie\Permission\Models\Role;

/** @mixin Role */
class RoleResource extends JsonApiResource
{
    public $id = 'name';

    /**
     * Get the resource's attributes.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $role = UserRole::from($this->resource->name);

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
