<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
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

// Protected routes
Route::middleware(['auth:sanctum', 'verified'])->controller(AuthController::class)->group(function () {
    Route::post('token/validate', 'validateToken')->name('token.validate');
    Route::post('logout', 'logout')->name('logout');
    Route::get('user', 'user')->name('user');
});