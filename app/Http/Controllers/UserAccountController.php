<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeletePassengerAccountRequest;
use App\Http\Requests\UpdatePassengerPasswordRequest;
use App\Http\Requests\UpdatePassengerProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\PassengerAccountDeletionService;
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

    /**
     * Delete Passenger Account
     *
     * Soft-deletes the authenticated, email-verified passenger account.
     * The account must have the passenger role and no additional roles,
     * including roles assigned in another company.
     *
     * Requires the current password and explicit deletion confirmation.
     * Revokes all account access tokens, including the current token.
     *
     * Personal data and historical references are retained.
     * This operation does not permanently erase or anonymize the account.
     *
     * Limited to five attempts per minute per authenticated account.
     * Response messages support English and Spanish through Accept-Language.
     *
     * @throws ValidationException
     */
    #[Response(status: HttpStatus::HTTP_OK, description: 'Account soft-deleted and all access tokens revoked.', type: 'array{meta: array{message: string}}')]
    #[Response(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Missing, invalid, expired, or revoked access token.', type: 'array{errors: list<array{status: string, title: string, detail: string}>}')]
    #[Response(status: HttpStatus::HTTP_FORBIDDEN, description: 'Email verification required or the account is not exclusively a passenger.', type: 'array{errors: list<array{status: string, title: string, detail: string}>}')]
    #[Response(status: HttpStatus::HTTP_UNPROCESSABLE_ENTITY, description: 'Invalid current password or deletion confirmation.', type: 'array{errors: list<array{status: int, title: string, detail: string, source: array{pointer: string}}>}')]
    #[Response(status: HttpStatus::HTTP_TOO_MANY_REQUESTS, description: 'More than five attempts within one minute.', type: 'array{errors: list<array{status: string, title: string, detail: string}>}')]
    #[Response(status: HttpStatus::HTTP_INTERNAL_SERVER_ERROR, description: 'Unexpected failure; account deletion and token revocation are rolled back.', type: 'array{errors: list<array{status: string, title: string, detail: string}>}')]
    public function destroy(DeletePassengerAccountRequest $request, PassengerAccountDeletionService $service): JsonResponse
    {
        $service->delete(
            $request->user(),
            $request->validated('current_password'),
        );

        return $this->successResponse([
            'message' => __('auth.account_deleted'),
        ]);
    }
}
