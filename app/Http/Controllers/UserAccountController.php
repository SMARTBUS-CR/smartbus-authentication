<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePassengerPasswordRequest;
use App\Http\Requests\UpdatePassengerProfileRequest;
use App\Http\Resources\UserResource;
use App\Traits\ApiResponser;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

use function array_key_exists;

#[Group(name: 'User Account', description: 'Endpoints for passengers to manage their own account.')]
class UserAccountController extends Controller
{
    use ApiResponser;

    /**
     * Update Passenger Profile
     *
     * Updates the authenticated passenger's profile information.
     * The user can update their name and email address.
     * If the email is changed, the email verification status will be reset.
     *
     * @throws ValidationException
     */
    #[Response(status: HttpStatus::HTTP_OK, description: 'Passenger profile updated successfully.')]
    public function update(UpdatePassengerProfileRequest $request): UserResource
    {
        $user = $request->user();
        $data = $request->safe()->only(['name', 'email']);

        if (array_key_exists('email', $data) && $data['email'] !== $user->email) {
            $data['email_verified_at'] = null;
        }

        $user->update($data);

        return UserResource::make($user->load('roles'));
    }

    /**
     * Update Passenger Password
     *
     * Updates the authenticated passenger's password.
     * The user must provide their current password to change it.
     * All other active tokens will be revoked upon successful password change.
     *
     * @throws ValidationException
     */
    #[Response(status: HttpStatus::HTTP_OK, description: 'Passenger password updated successfully.', type: 'array{meta: array{message: string}}')]
    public function updatePassword(UpdatePassengerPasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();

        DB::transaction(function () use ($request, $user, $currentToken): void {
            $user->update(['password' => $request->validated('password')]);
            $user->tokens()->whereKeyNot($currentToken->getKey())->delete();
        });

        return $this->successResponse([
            'message' => __('auth.password_updated'),
        ]);
    }
}
