<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
});

describe('Administrative Endpoint Retirement', function (): void {
    it('does not expose the retired endpoint even to a super administrator', function (string $method, string $path): void {
        $administrator = User::factory()
            ->withRole(UserRole::SuperAdmin)
            ->create();

        $target = User::factory()
            ->withRole(UserRole::Passenger)
            ->create([
                'name' => 'Unchanged Passenger',
            ]);

        $token = $administrator->createToken('administrator-device');

        $path = str_replace('{user}', $target->getKey(), $path);

        $this->withToken($token->plainTextToken)
            ->json($method, $path, [])
            ->assertNotFound();

        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'name' => 'Unchanged Passenger',
            'deleted_at' => null,
        ]);

        $this->assertModelExists($token->accessToken);
    })->with([
        'list users' => ['GET', '/api/users'],
        'create user' => ['POST', '/api/users'],
        'show user' => ['GET', '/api/users/{user}'],
        'update user' => ['PATCH', '/api/users/{user}'],
        'delete user' => ['DELETE', '/api/users/{user}'],

        'list roles' => ['GET', '/api/roles'],
        'list permissions' => ['GET', '/api/permissions'],

        'list user roles' => ['GET', '/api/users/{user}/roles'],
        'replace user roles' => ['PUT', '/api/users/{user}/roles'],
        'assign user role' => ['POST', '/api/users/{user}/roles/driver'],
        'revoke user role' => ['DELETE', '/api/users/{user}/roles/driver'],

        'list user permissions' => ['GET', '/api/users/{user}/permissions'],
        'replace user permissions' => ['PUT', '/api/users/{user}/permissions'],
        'assign user permission' => ['POST', '/api/users/{user}/permissions/Update:User'],
        'revoke user permission' => ['DELETE', '/api/users/{user}/permissions/Update:User'],
    ]);
});
