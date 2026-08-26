<?php

namespace App\Http\Controllers;

use App\Enums\UserRoles;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group(name: 'Authentication', description: 'Endpoints for user authentication, and registration.')]
class AuthController extends Controller
{
    /**
     * Register Passenger
     *
     * Registers a new passenger user in the system.
     * The user will be assigned the 'passenger' role and an access token will be generated for them.
     *
     * @throws ValidationException
     */
    public function registerPassenger(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole(UserRoles::PASSENGER);

        $expiresAt = $this->getTokenExpirationForUser($user);
        $deviceName = $request->header('User-Agent', 'auth_token');
        $token = $user->createToken($deviceName, ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => UserResource::make($user),
        ], HttpStatus::HTTP_CREATED);
    }

    /**
     * Login
     *
     * Authenticates a user and generates a new access token.
     * All previous tokens for the user will be revoked.
     *
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $request->authenticate();

        $user->tokens()->delete(); // Delete all previous tokens

        $expiresAt = $this->getTokenExpirationForUser($user);
        $deviceName = $request->header('User-Agent', 'auth_token');
        $token = $user->createToken($deviceName, ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'roles' => RoleResource::collection($user->roles),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ]);
    }

    /**
     * Logout
     *
     * Revokes the current access token for the authenticated user,
     * effectively logging them out.
     *
     * @throws UnauthorizedException
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('auth.logged_out')]);
    }

    /**
     * Get Authenticated User
     *
     * Returns the authenticated user's information along with their roles.
     *
     * @throws UnauthorizedException
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => UserResource::make($user),
        ]);
    }

    /**
     * Validate Token
     *
     * Validates the current access token for the authenticated user.
     * Returns a JSON response indicating whether the token is valid or not.
     *
     * @throws UnauthorizedException
     */
    public function validateToken(Request $request): JsonResponse
    {
        if (! $request->user()) {
            return response()->json([
                'message' => __('This action is unauthorized.'),
            ], HttpStatus::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'valid' => true,
            'expires_at' => $request->user()->currentAccessToken()->expires_at,
        ], HttpStatus::HTTP_OK);
    }

    /**
     * Get Token Expiration Time for User
     * Returns the expiration time for the user's access token based on their role.
     *
     * @param  User  $user  User instance for which to determine token expiration.
     * @return Carbon Expiration time for the user's access token.
     */
    private function getTokenExpirationForUser(User $user): Carbon
    {
        return match (true) {
            $user->hasRole(UserRoles::SUPER_ADMIN) => now()->addHours(2),
            $user->hasRole(UserRoles::COMPANY_ADMIN) => now()->addHours(8),
            $user->hasRole(UserRoles::DRIVER) => now()->addHours(14),
            default => now()->addDays(30),
        };
    }
}
