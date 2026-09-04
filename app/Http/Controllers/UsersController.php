<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\AdminUserIndexRequest;
use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\UpdateAdminUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponser;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group(name: 'Users', description: 'Administrative user management endpoints.')]
class UsersController extends Controller implements HasMiddleware
{
    use ApiResponser;

    /**
     * Get the middleware assigned to user management actions.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:users.view', only: ['index', 'show']),
            new Middleware('can:users.create', only: ['store']),
            new Middleware('can:users.update', only: ['update']),
            new Middleware('can:users.delete', only: ['destroy']),
        ];
    }

    /**
     * List
     *
     * Get a paginated list of users with optional filtering, sorting, and inclusion of related roles.
     *
     * @param  AdminUserIndexRequest  $request  The request containing filtering, sorting, and pagination parameters.
     * @return AnonymousResourceCollection A paginated collection of UserResource instances.
     */
    #[Response(status: HttpStatus::HTTP_OK, description: 'Paginated users returned successfully.')]
    public function index(AdminUserIndexRequest $request): AnonymousResourceCollection
    {
        $users = QueryBuilder::for(User::class)
            ->with('roles')
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::partial('email'),
                AllowedFilter::callback('role', function (Builder $query, mixed $value): void {
                    $query->role((string) $value);
                }),
            )
            ->allowedSorts('name', 'email', 'created_at')
            ->allowedIncludes('roles')
            ->defaultSort('name')
            ->paginate((int) $request->input('page.size', 25))
            ->appends($request->query());

        return UserResource::collection($users);
    }

    /**
     * Create
     *
     * Create a new user and assign the requested roles. The super-admin role can only
     * be assigned by users with the 'roles.manage' permission.
     *
     * @param  StoreAdminUserRequest  $request  The request containing user data and roles.
     * @return UserResource The created user resource.
     */
    #[Response(status: HttpStatus::HTTP_OK, description: 'User created successfully.')]
    #[Response(status: HttpStatus::HTTP_UNPROCESSABLE_ENTITY, description: 'The user data is invalid.')]
    public function store(StoreAdminUserRequest $request): UserResource
    {
        $data = $request->validated();
        $roles = $data['roles'] ?? [UserRole::Passenger->value];
        $this->ensureAssignableRoles($request->user(), $roles);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $user->syncRoles($roles);

        return UserResource::make($user->load('roles'));
    }

    /**
     * Show
     *
     * Get a user with its assigned roles and effective permissions.
     *
     * @param  User  $user  The user to retrieve.
     * @return UserResource The user resource with roles and permissions.
     */
    #[Response(status: HttpStatus::HTTP_OK, description: 'User returned successfully.')]
    #[Response(status: HttpStatus::HTTP_NOT_FOUND, description: 'The requested user was not found.')]
    public function show(User $user): UserResource
    {
        return UserResource::make($user->load('roles'));
    }

    /**
     * Update
     *
     * Update a user's profile and optionally change its password. If the password is changed,
     * all existing tokens for the user will be revoked.
     *
     * @param  UpdateAdminUserRequest  $request  The request containing updated user data.
     * @param  User  $user  The user to update.
     * @return UserResource The updated user resource.
     */
    #[Response(status: HttpStatus::HTTP_OK, description: 'User updated successfully.')]
    #[Response(status: HttpStatus::HTTP_UNPROCESSABLE_ENTITY, description: 'The user data is invalid.')]
    public function update(UpdateAdminUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();
        if (array_key_exists('password', $data)) {
            $user->tokens()->delete();
        }

        $user->update($data);

        return UserResource::make($user->load('roles'));
    }

    /**
     * Delete
     *
     * Delete a user after applying administrator safety rules. A user cannot delete themselves,
     * and the last administrator cannot be deleted.
     *
     * @param  User  $user  The user to delete.
     * @return JsonResponse A JSON response indicating success or failure.
     */
    #[Response(status: HttpStatus::HTTP_OK, description: 'User deleted successfully.')]
    #[Response(status: HttpStatus::HTTP_CONFLICT, description: 'The deletion violates a user safety rule.')]
    public function destroy(User $user): JsonResponse
    {
        $actor = request()->user();
        if ($actor->is($user)) {
            return $this->errorResponse('You cannot delete your own user.', 'Conflict', HttpStatus::HTTP_CONFLICT);
        }

        if ($this->isAdministrator($user) && User::role([UserRole::SuperAdmin->value, UserRole::CompanyAdmin->value])->count() <= 1) {
            return $this->errorResponse('The last administrator cannot be deleted.', 'Conflict', HttpStatus::HTTP_CONFLICT);
        }

        $user->tokens()->delete();
        $user->delete();

        return $this->successResponse(['message' => 'User deleted.']);
    }

    /**
     * Ensure that the super-admin role is not being assigned by an unauthorized user.
     *
     * @param  User  $actor  The user attempting to assign roles.
     * @param  array  $roles  The roles being assigned.
     *
     * @throws ValidationException If the actor is not authorized to assign the super-admin role.
     */
    private function ensureAssignableRoles(User $actor, array $roles): void
    {
        if ($actor->can('roles.manage')) {
            return;
        }

        if (in_array(UserRole::SuperAdmin->value, $roles, true)) {
            throw ValidationException::withMessages([
                'roles' => ['You are not authorized to assign the super-admin role.'],
            ]);
        }
    }

    /**
     * Determine whether a user has an administrator role.
     *
     * @param  User  $user  The user to check.
     * @return bool True if the user has an administrator role, false otherwise.
     */
    private function isAdministrator(User $user): bool
    {
        return $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::CompanyAdmin->value]);
    }
}
