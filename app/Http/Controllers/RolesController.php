<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Resources\RoleResource;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Models\Role;

#[Group(name: 'Roles', description: 'Administrative role catalog endpoints.')]
class RolesController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware assigned to role catalog actions.
     */
    public static function middleware(): array
    {
        return [new Middleware('can:roles.view')];
    }

    /**
     * List
     *
     * List the roles supported by the application.
     *
     * @return AnonymousResourceCollection The list of supported roles.
     */
    #[Response(status: 200, description: 'Available roles returned successfully.')]
    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection(
            Role::query()
                ->whereIn('name', array_map(fn (UserRole $role): string => $role->value, UserRole::cases()))
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->get(),
        );
    }
}
