<?php

namespace App\Http\Controllers;

use App\Mail\ResetPasswordCode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

class PasswordResetController extends Controller
{
    /**
     * Step 1: Generate the code and send it via email.
     */
    public function sendResetCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $email = $request->email;
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
     * Step 2: Validate the code and change the password.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string|size:6',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)  // Minimum length of 8 characters
                    ->letters()         // Require at least one letter
                    ->mixedCase()       // Require at least one uppercase and one lowercase letter
                    ->numbers()         // Require at least one number
                    ->symbols()         // Require at least one symbol
                    ->uncompromised(),  // Ensure the password is not compromised
            ],
        ]);

        $resetRequest = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        // 1. Check if there is a pending reset request for the given email
        if (! $resetRequest) {
            return response()->json(['message' => __('passwords.invalid_code')], 400);
        }

        // 2. Check if the provided code matches the hashed token in the database
        if (! Hash::check($request->code, $resetRequest->token)) {
            return response()->json(['message' => __('passwords.incorrect_code')], 400);
        }

        // 3. Check if the code has expired (15 minutes)
        if (Carbon::parse($resetRequest->created_at)->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return response()->json(['message' => __('passwords.code_expired')], 400);
        }

        // 4. Update the password and delete active access tokens to force a new login
        $user = User::where('email', $request->email)->first();
        $user->update(['password' => Hash::make($request->password)]);
        $user->tokens()->delete();

        // 5. Clear the reset token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'message' => __('passwords.reset'),
        ]);
    }
}
