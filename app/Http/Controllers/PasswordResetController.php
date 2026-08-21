<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SendResetCodeRequest;
use App\Mail\ResetPasswordCode;
use App\Models\User;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group(name: 'Password Reset', description: 'Endpoints for password reset functionality.')]
class PasswordResetController extends Controller
{
    /**
     * Send Reset Code
     *
     * Sends a 6-digit password reset code to the user's email.
     * The code is valid for 15 minutes.
     *
     * @throws ValidationException
     */
    public function sendResetCode(SendResetCodeRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $code = (string) random_int(100000, 999999); // Secure 6-digit code

        // Save the hashed code in the database
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($code),
                'created_at' => now(),
            ]
        );

        $user = User::where('email', $email)->first();

        // Send the code via email
        Mail::to($email)->send(new ResetPasswordCode($code, $user?->name));

        return response()->json([
            'message' => __('passwords.code_sent'),
        ]);
    }

    /**
     * Reset Password
     *
     * Resets the user's password using the provided reset code.
     * The code must match the one sent to the user's email and must not be expired.
     *
     * @throws ValidationException
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $resetRequest = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        // Check if there is a pending reset request for the given email
        if (! $resetRequest) {
            return response()->json(
                ['message' => __('passwords.invalid_code')],
                HttpStatus::HTTP_BAD_REQUEST
            );
        }

        // Check if the provided code matches the hashed token in the database
        if (! Hash::check($data['code'], $resetRequest->token)) {
            return response()->json(
                ['message' => __('passwords.incorrect_code')],
                HttpStatus::HTTP_BAD_REQUEST
            );
        }

        // Check if the code has expired (15 minutes)
        if (Carbon::parse($resetRequest->created_at)->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

            return response()->json([
                'message' => __('passwords.code_expired'),
            ], HttpStatus::HTTP_BAD_REQUEST);
        }

        // Update the password and delete active access tokens to force a new login
        $user = User::where('email', $data['email'])->first();
        $user->update(['password' => Hash::make($data['password'])]);
        $user->tokens()->delete();

        // Clear the reset token
        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        return response()->json([
            'message' => __('passwords.reset'),
        ]);
    }
}
