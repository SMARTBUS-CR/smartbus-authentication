<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\UserAccountController;
use Illuminate\Support\Facades\Route;

// Auth Routes (Public)
Route::controller(AuthController::class)->group(function () {
    Route::post('register/passenger', 'registerPassenger')->name('register.passenger');
    Route::post('login', 'login')->middleware('throttle:5,1')->name('login');
});

Route::prefix('password')->controller(PasswordResetController::class)->group(function () {
    Route::post('forgot', 'sendResetCode')->middleware('throttle:5,1')->name('password.forgot');
    Route::post('reset', 'resetPassword')->middleware('throttle:5,1')->name('password.reset');
});

Route::prefix('email')->group(function () {
    Route::post('verify', [EmailVerificationController::class, 'verify'])
        ->middleware('throttle:5,1')
        ->name('email.verify');

    Route::post('resend', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:3,1')
        ->name('email.resend');
});

// Protected routes
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // Auth Related Routes
    Route::controller(AuthController::class)->group(function () {
        Route::post('token/validate', 'validateToken')->name('token.validate');
        Route::post('logout', 'logout')->name('logout');
        Route::get('user', 'user')->name('user');
    });

    // User Account Routes
    Route::controller(UserAccountController::class)->group(function () {
        Route::patch('user', 'update')->middleware('throttle:5,1')->name('user.update');
        Route::put('user/password', 'updatePassword')->middleware('throttle:5,1')->name('user.password.update');
        Route::delete('user', 'destroy')->middleware('throttle:5,1')->name('user.destroy');
    });
});
