<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Spatie\Permission\Models\Permission;

/** @mixin Permission */
class PermissionResource extends JsonApiResource
{
    public $id = 'name';

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->resource->name,
        ];
    }
}
