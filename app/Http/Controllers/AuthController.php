<?php

namespace App\Http\Controllers;

use App\Enums\UserRoles;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponser;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group(name: 'Authentication', description: 'Endpoints for user authentication, and registration.')]
class AuthController extends Controller
{
    use ApiResponser;

    /**
     * Register Passenger
     *
     * Registers a new passenger user in the system.
     * The user will be assigned the 'passenger' role and an access token will be generated for them.
     *
     * @throws ValidationException
     */
    #[Response(status: HttpStatus::HTTP_CREATED, description: 'User registered successfully.')]
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

        return UserResource::make($user)
            ->additional([
                'meta' => [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'expires_at' => $expiresAt->toIso8601String(),
                ],
            ])
            ->response()
            ->setStatusCode(HttpStatus::HTTP_CREATED);
    }

    /**
     * Login
     *
     * Authenticates a user and generates a new access token.
     * All previous tokens for the user will be revoked.
     *
     * @throws ValidationException
     */
    #[Response(status: HttpStatus::HTTP_OK, description: 'User authenticated successfully.')]
    public function login(LoginRequest $request): UserResource
    {
        $user = $request->authenticate();

        $user->tokens()->delete(); // Delete all previous tokens
        $user->load('roles'); // Load roles for the authenticated user

        $expiresAt = $this->getTokenExpirationForUser($user);
        $deviceName = $request->header('User-Agent', 'auth_token');
        $token = $user->createToken($deviceName, ['*'], $expiresAt)->plainTextToken;

        return UserResource::make($user)->additional([
            'meta' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toIso8601String(),
            ],
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
    #[Response(status: HttpStatus::HTTP_OK, description: 'Successfully logged out.', type: 'array{meta: array{message: string}}')]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        // Logout Response following the [JSON:API](https://jsonapi.org/) specification.
        return $this->successResponse([
            'message' => __('auth.logged_out'),
        ], HttpStatus::HTTP_OK);
    }

    /**
     * Get Authenticated User
     *
     * Returns the authenticated user's information along with their roles.
     *
     * @throws UnauthorizedException
     */
    #[Response(status: HttpStatus::HTTP_OK, description: 'Authenticated user retrieved successfully.')]
    public function user(Request $request): UserResource
    {
        $user = $request->user()->load('roles');

        return UserResource::make($user);
    }

    /**
     * Validate Token
     *
     * Validates the current access token for the authenticated user.
     * Returns a JSON response indicating whether the token is valid or not.
     *
     * @throws UnauthorizedException
     */
    #[Response(status: HttpStatus::HTTP_OK, description: 'Token is valid.', type: 'array{meta: array{valid: bool, expires_at: string}}')]
    #[Response(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Token is invalid or expired.', type: 'array{errors: array{status: string, title: string, detail: string}}')]
    public function validateToken(Request $request): JsonResponse
    {
        if (! $request->user()) {
            return $this->errorResponse(
                __('This action is unauthorized.'),
                __('Unauthorized'),
                HttpStatus::HTTP_UNAUTHORIZED
            );
        }

        return $this->successResponse([
            'valid' => true,
            'expires_at' => $request->user()->currentAccessToken()->expires_at->toIso8601String(),
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
