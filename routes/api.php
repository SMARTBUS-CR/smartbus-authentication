<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PermissionsController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\UserPermissionsController;
use App\Http\Controllers\UserRolesController;
use App\Http\Controllers\UsersController;
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
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // Auth Related Routes
    Route::controller(AuthController::class)->group(function () {
        Route::post('token/validate', 'validateToken')->name('token.validate');
        Route::post('logout', 'logout')->name('logout');
        Route::get('user', 'user')->name('user');
    });

    // User Management Routes
    Route::controller(UsersController::class)->group(function () {
        Route::get('users', 'index')->name('users.index');
        Route::post('users', 'store')->name('users.store');
        Route::get('users/{user}', 'show')->name('users.show');
        Route::patch('users/{user}', 'update')->name('users.update');
        Route::delete('users/{user}', 'destroy')->name('users.destroy');
    });

    // Roles and Permissions Routes
    Route::get('roles', [RolesController::class, 'index'])->name('roles.index');
    Route::get('permissions', [PermissionsController::class, 'index'])->name('permissions.index');

    // User Roles Routes
    Route::controller(UserRolesController::class)->group(function () {
        Route::get('users/{user}/roles', 'index')->name('users.roles.index');
        Route::put('users/{user}/roles', 'update')->name('users.roles.update');
        Route::post('users/{user}/roles/{role}', 'store')->name('users.roles.store');
        Route::delete('users/{user}/roles/{role}', 'destroy')->name('users.roles.destroy');
    });

    // User Permissions Routes
    Route::controller(UserPermissionsController::class)->group(function () {
        Route::get('users/{user}/permissions', 'index')->name('users.permissions.index');
        Route::put('users/{user}/permissions', 'update')->name('users.permissions.update');
        Route::post('users/{user}/permissions/{permission}', 'store')->name('users.permissions.store');
        Route::delete('users/{user}/permissions/{permission}', 'destroy')->name('users.permissions.destroy');
    });
});
