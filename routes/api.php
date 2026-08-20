<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth Routes
Route::group(['prefix' => 'auth'], function () {
    Route::post('/register/passenger', [AuthController::class, 'registerPassenger'])
        ->name('register.passenger');
    Route::post('/login', [AuthController::class, 'login'])
        ->name('login');
    Route::post('/password/forgot', [PasswordResetController::class, 'sendResetCode'])
        ->middleware('throttle:3,1')
        ->name('password.forgot');
    Route::post('/password/reset', [PasswordResetController::class, 'resetPassword'])
        ->middleware('throttle:5,1')
        ->name('password.reset');
});

// Protected routes
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('logout');
    Route::get('/user', fn (Request $request) => response()->json([
        'user' => $request->user(),
        'roles' => $request->user()->getRoleNames(),
    ]))->name('user');
});
