<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\SyncUserRolesRequest;
use App\Http\Resources\RoleResource;
use App\Models\User;
use App\Traits\ApiResponser;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group(name: 'User Roles', description: 'Endpoints for managing roles assigned to users.')]
class UserRolesController extends Controller implements HasMiddleware
{
    use ApiResponser;

    /**
     * Get the middleware assigned to user role actions.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:roles.view', only: ['index']),
            new Middleware('can:users.assign-roles', only: ['update', 'store', 'destroy']),
        ];
    }

    /**
     * List User Roles
     *
     * List the roles assigned to a user.
     *
     * @param  User  $user  The user whose roles are being retrieved.
     * @return AnonymousResourceCollection A collection of the user's roles.
     */
    #[Response(status: 200, description: 'User roles returned successfully.')]
    public function index(User $user): AnonymousResourceCollection
    {
        return RoleResource::collection($user->roles);
    }

    /**
     * Update User Roles
     *
     * Replace all roles assigned to a user.
     *
     * @param  SyncUserRolesRequest  $request  The request containing the new set of roles.
     * @param  User  $user  The user whose roles are being updated.
     * @return AnonymousResourceCollection A collection of the user's updated roles.
     */
    #[Response(status: 200, description: 'User roles synchronized successfully.')]
    #[Response(status: 422, description: 'The role list is invalid.')]
    public function update(SyncUserRolesRequest $request, User $user): AnonymousResourceCollection
    {
        $roles = $request->validated('roles');
        $this->ensureNotRemovingLastAdmin($request->user(), $user, $roles);
        $user->syncRoles($roles);

        return RoleResource::collection($user->load('roles')->roles);
    }

    /**
     * Assign Role to User
     *
     * Assign a supported role to a user.
     *
     * @param  User  $user  The user to whom the role is being assigned.
     * @param  string  $role  The name of the role to assign.
     */
    #[Response(status: 200, description: 'Role assigned successfully.')]
    #[Response(status: 422, description: 'The requested role is invalid.')]
    public function store(User $user, string $role): AnonymousResourceCollection
    {
        $roleValue = $this->validatedRole($role);
        $this->ensureNotEscalating(request()->user(), [$roleValue]);
        $user->assignRole($roleValue);

        return RoleResource::collection($user->load('roles')->roles);
    }

    /**
     * Revoke Role from User
     *
     * Revoke a role from a user.
     *
     * @param  User  $user  The user from whom the role is being revoked.
     * @param  string  $role  The name of the role to revoke.
     * @return JsonResponse A JSON response indicating success or failure.
     */
    #[Response(status: 200, description: 'Role revoked successfully.')]
    #[Response(status: 409, description: 'The revocation violates an administrator safety rule.')]
    public function destroy(User $user, string $role): JsonResponse
    {
        $roleValue = $this->validatedRole($role);
        if (request()->user()->is($user) && in_array($roleValue, [UserRole::SuperAdmin->value, UserRole::CompanyAdmin->value], true)) {
            return $this->errorResponse('You cannot revoke your own administrator role.', 'Conflict', HttpStatus::HTTP_CONFLICT);
        }

        $roles = $user->getRoleNames()->reject(fn (string $name): bool => $name === $roleValue)->values()->all();
        $this->ensureNotRemovingLastAdmin(request()->user(), $user, $roles);
        $user->removeRole($roleValue);

        return $this->successResponse(['message' => 'Role revoked.']);
    }

    /**
     * Validate a route role against the supported role enum.
     *
     * @param  string  $role  The role to validate.
     * @return string The validated role value.
     *
     * @throws ValidationException If the role is not supported.
     * @throws ModelNotFoundException If the role is not found.
     */
    private function validatedRole(string $role): string
    {
        $value = UserRole::tryFrom($role)?->value;
        if ($value === null) {
            throw ValidationException::withMessages(['role' => ['The selected role is invalid.']]);
        }

        return $value;
    }

    /**
     * Prevent unauthorized assignment of the super-admin role.
     *
     * @param  User  $actor  The user attempting to assign roles.
     * @param  array  $roles  The roles being assigned.
     *
     * @throws ValidationException If the actor is not authorized to assign the super-admin role.
     */
    private function ensureNotEscalating(User $actor, array $roles): void
    {
        if ($actor->can('roles.manage')) {
            return;
        }

        if (in_array(UserRole::SuperAdmin->value, $roles, true)) {
            throw ValidationException::withMessages(['role' => ['You are not authorized to assign the super-admin role.']]);
        }
    }

    /**
     * Prevent removal of the final administrator role.
     *
     * @param  User  $actor  The user attempting to modify roles.
     * @param  User  $user  The user whose roles are being modified.
     * @param  array  $roles  The roles being assigned to the user.
     *
     * @throws ValidationException If the action would remove the last administrator role.
     */
    private function ensureNotRemovingLastAdmin(User $actor, User $user, array $roles): void
    {
        $this->ensureNotEscalating($actor, $roles);
        $isAdmin = array_intersect($roles, [UserRole::SuperAdmin->value, UserRole::CompanyAdmin->value]) !== [];
        if (! $isAdmin && $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::CompanyAdmin->value]) && User::role([UserRole::SuperAdmin->value, UserRole::CompanyAdmin->value])->count() <= 1) {
            throw ValidationException::withMessages(['roles' => ['The last administrator role cannot be revoked.']]);
        }
    }
}
