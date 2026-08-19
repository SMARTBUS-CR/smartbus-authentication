<?php

namespace App\Http\Controllers;

use App\Enums\UserRoles;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function registerPassenger(RegisterRequest $request)
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
            'user' => $user,
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $user = $request->authenticate();

        $user->tokens()->delete(); // Delete all previous tokens

        $expiresAt = $this->getTokenExpirationForUser($user);
        $deviceName = $request->header('User-Agent', 'auth_token');
        $token = $user->createToken($deviceName, ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'roles' => $user->getRoleNames(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

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
