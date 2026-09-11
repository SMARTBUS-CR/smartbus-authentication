<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncUserPermissionsRequest;
use App\Models\User;
use App\Traits\ApiResponser;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Models\Permission;

#[Group(name: 'User Permissions', description: 'Endpoints for managing direct and effective user permissions.')]
class UserPermissionsController extends Controller implements HasMiddleware
{
    use ApiResponser;

    /**
     * Get the middleware assigned to user permission actions.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:permissions.view', only: ['index']),
            new Middleware('can:users.assign-permissions', only: ['update', 'store', 'destroy']),
        ];
    }

    /**
     * List User Permissions
     *
     * Get a user's direct and effective permissions.
     *
     * @param  User  $user  The user whose permissions are being retrieved.
     * @return JsonResponse A JSON response containing the user's direct and effective permissions.
     */
    #[Response(status: 200, description: 'User permissions returned successfully.')]
    public function index(User $user): JsonResponse
    {
        return response()->json([
            'data' => [
                'type' => 'user-permissions',
                'id' => (string) $user->getKey(),
                'attributes' => [
                    'direct' => $user->getDirectPermissions()->pluck('name')->values(),
                    'effective' => $user->getAllPermissions()->pluck('name')->values(),
                ],
            ],
        ]);
    }

    /**
     * Update User Permissions
     *
     * Replace all direct permissions assigned to a user.
     *
     * @param  SyncUserPermissionsRequest  $request  The request containing the new set of permissions.
     * @param  User  $user  The user whose permissions are being updated.
     * @return JsonResponse A JSON response indicating success or failure.
     */
    #[Response(status: 200, description: 'User permissions synchronized successfully.')]
    #[Response(status: 422, description: 'The permission list is invalid.')]
    public function update(SyncUserPermissionsRequest $request, User $user): JsonResponse
    {
        $user->syncPermissions($request->validated('permissions'));

        return $this->successResponse(['message' => 'Permissions synchronized.']);
    }

    /**
     * Assign Permission to User
     *
     * Assign a direct permission to a user.
     *
     * @param  User  $user  The user to whom the permission is being assigned.
     * @param  string  $permission  The name of the permission to assign.
     * @return JsonResponse A JSON response indicating success or failure.
     */
    #[Response(status: 200, description: 'Permission assigned successfully.')]
    #[Response(status: 404, description: 'The requested permission was not found.')]
    public function store(User $user, string $permission): JsonResponse
    {
        $user->givePermissionTo($this->findPermission($permission));

        return $this->successResponse(['message' => 'Permission assigned.']);
    }

    /**
     * Revoke Permission from User
     *
     * Revoke a direct permission from a user.
     *
     * @param  User  $user  The user from whom the permission is being revoked.
     * @param  string  $permission  The name of the permission to revoke.
     * @return JsonResponse A JSON response indicating success or failure.
     */
    #[Response(status: 200, description: 'Permission revoked successfully.')]
    #[Response(status: 404, description: 'The requested permission was not found.')]
    public function destroy(User $user, string $permission): JsonResponse
    {
        $user->revokePermissionTo($this->findPermission($permission));

        return $this->successResponse(['message' => 'Permission revoked.']);
    }

    /**
     * Find a permission by name.
     *
     * @param  string  $permission  The name of the permission to find.
     * @return Permission The found permission.
     *
     * @throws ModelNotFoundException If the permission is not found.
     */
    private function findPermission(string $permission): Permission
    {
        return Permission::query()->where('name', $permission)->where('guard_name', 'web')->firstOrFail();
    }
}
