<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
});

describe('API Error Response', function () {
    it('formats not found API errors as JSON:API errors', function () {
        $admin = User::factory()->withRole(UserRole::SuperAdmin)->create();
        Sanctum::actingAs($admin);

        $this->getJson(route('users.show', ['user' => 999999]))
            ->assertNotFound()
            ->assertJsonStructure(['errors' => [['status', 'title', 'detail']]])
            ->assertJsonPath('errors.0.status', '404')
            ->assertJsonPath('errors.0.title', __('Not Found'));
    });

    it('formats method not allowed API errors as JSON:API errors', function () {
        $this->postJson(route('user'))
            ->assertMethodNotAllowed()
            ->assertJsonPath('errors.0.status', '405')
            ->assertJsonPath('errors.0.title', __('Method Not Allowed'));
    });

    it('formats unauthorized API errors as JSON:API errors', function () {
        $this->getJson(route('user'))
            ->assertUnauthorized()
            ->assertJsonPath('errors.0.status', '401')
            ->assertJsonPath('errors.0.title', __('Unauthorized'));
    });
});
