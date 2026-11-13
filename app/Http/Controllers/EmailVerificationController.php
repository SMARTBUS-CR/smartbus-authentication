<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Resources\UserResource;
use App\Mail\VerifyEmailCode;
use App\Models\User;
use App\Traits\ApiResponser;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group(name: 'Email Verification', description: 'Endpoints for email verification via OTP code.')]
class EmailVerificationController extends Controller
{
    use ApiResponser;

    /**
     * Verify Email
     *
     * Verifies the user's email address using the 6-digit OTP code sent to their email.
     * On success, the user's email is marked as verified and an access token is returned.
     *
     * @throws ValidationException
     */
    #[Response(status: HttpStatus::HTTP_OK, description: 'Email verified successfully.')]
    #[Response(status: HttpStatus::HTTP_BAD_REQUEST, description: 'Invalid, expired, or too many attempts.', type: 'array{errors: array{status: string, title: string, detail: string}}')]
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $record = DB::table('email_verification_codes')
            ->where('email', $data['email'])
            ->first();

        if (! $record) {
            return $this->errorResponse(
                __('verification.incorrect_code'),
                __('http-statuses.400'),
                HttpStatus::HTTP_BAD_REQUEST
            );
        }

        if ($record->attempts >= 5) {
            DB::table('email_verification_codes')->where('email', $data['email'])->delete();

            return $this->errorResponse(
                __('verification.too_many_attempts'),
                __('http-statuses.400'),
                HttpStatus::HTTP_BAD_REQUEST
            );
        }

        if (Carbon::parse($record->created_at)->addMinutes(15)->isPast()) {
            DB::table('email_verification_codes')
                ->where('email', $data['email'])
                ->delete();

            return $this->errorResponse(
                __('verification.code_expired'),
                __('http-statuses.400'),
                HttpStatus::HTTP_BAD_REQUEST
            );
        }

        if (! Hash::check($data['code'], $record->code)) {
            DB::table('email_verification_codes')
                ->where('email', $data['email'])
                ->increment('attempts');

            return $this->errorResponse(
                __('verification.incorrect_code'),
                __('http-statuses.400'),
                HttpStatus::HTTP_BAD_REQUEST
            );
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            DB::table('email_verification_codes')
                ->where('email', $data['email'])
                ->delete();

            return $this->errorResponse(
                __('verification.incorrect_code'),
                __('http-statuses.400'),
                HttpStatus::HTTP_BAD_REQUEST
            );
        }

        $user->markEmailAsVerified();

        DB::table('email_verification_codes')->where('email', $data['email'])->delete();

        $user->load('roles');
        $user->tokens()->delete(); // Delete all previous tokens

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
            ->setStatusCode(HttpStatus::HTTP_OK);
    }

    /**
     * Resend Verification Code
     *
     * Resends the 6-digit email verification code to the given email address.
     * For security, always returns 200 even if the email is not found or already verified.
     *
     * @throws ValidationException
     */
    #[Response(status: HttpStatus::HTTP_OK, description: 'Verification code sent (or silently skipped).', type: 'array{meta: array{message: string}}')]
    public function resend(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = $request->input('email');

        $user = User::where('email', $email)->first();

        if (! $user || $user->hasVerifiedEmail()) {
            return $this->successResponse([
                'message' => __('verification.code_sent'),
            ]);
        }

        $code = (string) random_int(100000, 999999);

        DB::table('email_verification_codes')->updateOrInsert(
            ['email' => $email],
            [
                'code' => Hash::make($code),
                'attempts' => 0,
                'created_at' => now(),
            ]
        );

        Mail::to($email)->send(new VerifyEmailCode($code, $user->name));

        return $this->successResponse([
            'message' => __('verification.code_sent'),
        ]);
    }

    /**
     * Get Token Expiration Time for User.
     *
     * @param  User  $user  User instance for which to determine token expiration.
     * @return Carbon Expiration time for the user's access token.
     */
    private function getTokenExpirationForUser(User $user): Carbon
    {
        return match (true) {
            $user->hasRole(UserRole::SUPER_ADMIN) => now()->addHours(2),
            $user->hasRole(UserRole::ADMIN) => now()->addHours(8),
            $user->hasRole(UserRole::DRIVER) => now()->addHours(14),
            default => now()->addDays(30),
        };
    }
}
