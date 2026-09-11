<?php

namespace App\Http\Controllers;

use App\Http\Resources\PermissionResource;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Models\Permission;

#[Group(name: 'Permissions', description: 'Administrative permission catalog endpoints.')]
class PermissionsController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware assigned to permission catalog actions.
     */
    public static function middleware(): array
    {
        return [new Middleware('can:permissions.view')];
    }

    /**
     * List
     *
     * List the permissions available to the application.
     *
     * @return AnonymousResourceCollection The list of available permissions.
     */
    #[Response(status: 200, description: 'Available permissions returned successfully.')]
    public function index(): AnonymousResourceCollection
    {
        return PermissionResource::collection(
            Permission::query()->where('guard_name', 'web')->orderBy('name')->get(),
        );
    }
}
