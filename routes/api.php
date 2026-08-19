<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register/passenger', [AuthController::class, 'registerPassenger']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', fn (Request $request) => response()->json([
        'user' => $request->user(),
        'roles' => $request->user()->getRoleNames(),
    ]));
});
