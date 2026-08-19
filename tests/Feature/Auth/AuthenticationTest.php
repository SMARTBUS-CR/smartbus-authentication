<?php

use App\Enums\UserRoles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

// Ensures that the database is refreshed for each test
uses(RefreshDatabase::class);

beforeEach(function () {
    // Initial setup: Create the necessary role before each test
    Role::firstOrCreate(['name' => UserRoles::PASSENGER, 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => UserRoles::SUPER_ADMIN, 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => UserRoles::COMPANY_ADMIN, 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => UserRoles::DRIVER, 'guard_name' => 'web']);
});

describe('Passenger Registration', function () {

    it('allows a new passenger to register with valid data', function () {
        $data = [
            'name' => 'Pasajero de Prueba',
            'email' => 'pasajero@smartbus.com',
            'password' => 'N7v!qL2#rX9@kP4',
            'password_confirmation' => 'N7v!qL2#rX9@kP4',
        ];

        $response = postJson('/api/register/passenger', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'user' => ['id', 'name', 'email'],
            ]);

        // Check that the user was saved in the database
        assertDatabaseHas('users', [
            'email' => 'pasajero@smartbus.com',
        ]);

        // Check that the user has the 'passenger' role
        $user = User::where('email', 'pasajero@smartbus.com')->first();
        expect($user->hasRole(UserRoles::PASSENGER))->toBeTrue();
    });

    it('rejects registration when required data is missing', function () {
        $response = postJson('/api/register/passenger', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    });
});

describe('Login and Logout', function () {

    it('allows login with valid credentials', function () {
        $user = User::factory()->create([
            'email' => 'login@smartbus.com',
            'password' => bcrypt('password123'),
        ]);
        $user->assignRole(UserRoles::PASSENGER);

        $response = postJson('/api/login', [
            'email' => 'login@smartbus.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'roles',
            ])
            ->assertJsonPath('roles.0', UserRoles::PASSENGER);
    });

    it('rejects login with invalid credentials', function () {
        User::factory()->create([
            'email' => 'login@smartbus.com',
            'password' => bcrypt('password123'),
        ]);

        $response = postJson('/api/login', [
            'email' => 'login@smartbus.com',
            'password' => 'contraseña-incorrecta',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('blocks login attempts after 5 failed tries (Rate Limiting)', function () {
        $user = User::factory()->create([
            'email' => 'hacker@smartbus.com',
            'password' => bcrypt('password123'),
        ]);

        // The first 5 attempts should fail but not trigger the rate limiter
        for ($i = 0; $i < 5; $i++) {
            postJson('/api/login', [
                'email' => 'hacker@smartbus.com',
                'password' => 'wrong-password',
            ]);
        }

        // The 6th attempt should be blocked by the Rate Limiter
        $response = postJson('/api/login', [
            'email' => 'hacker@smartbus.com',
            'password' => 'wrong-password',
        ]);

        // The response should return a 422 with the throttle error on the email field
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('deletes previous tokens upon successful login (Single Active Session)', function () {
        $user = User::factory()->create([
            'email' => 'multidevice@smartbus.com',
            'password' => bcrypt('password123'),
        ]);
        $user->assignRole(UserRoles::PASSENGER);

        // Simulates that the user already had an open session on another device
        $user->createToken('old_device');

        // Verifies that the user has 1 active token
        expect($user->tokens()->count())->toBe(1);

        // The new login should delete the previous token and create a new one
        $response = postJson('/api/login', [
            'email' => 'multidevice@smartbus.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        // The user should still have only 1 active token (the new one),
        // because the 'old_device' token was deleted.
        expect($user->fresh()->tokens()->count())->toBe(1);
    });

    // --------------------
});

describe('Protected Routes (Sanctum)', function () {

    it('returns the authenticated user information', function () {
        $user = User::factory()->create();
        $user->assignRole(UserRoles::PASSENGER);

        // Simulate authentication with Sanctum
        Sanctum::actingAs($user, ['*']);

        $response = getJson('/api/user');

        $response->assertStatus(200)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('roles.0', UserRoles::PASSENGER);
    });

    it('blocks access to /api/user when no token is provided', function () {
        $response = getJson('/api/user');

        $response->assertStatus(401);
    });

    it('allows the user to log out successfully', function () {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['*']);

        $response = postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Sesión cerrada correctamente']);
    });
});

describe('Internationalization (Locale)', function () {

    it('returns validation errors in Spanish when Accept-Language header is sent', function () {
        $response = postJson('/api/login', [], [
            'Accept-Language' => 'es',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'El campo correo electrónico es obligatorio.');
    });

    it('returns validation errors in English when no Accept-Language header is sent', function () {
        $response = postJson('/api/login');

        $response->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'The email field is required.');
    });
});

describe('Tokens Expiration by Role', function () {

    beforeEach(function () {
        // Freeze time to ensure consistent token expiration checks
        $this->freezeTime();
    });

    it('assigns a 30-day expiration to passengers', function () {
        $user = User::factory()->create([
            'email' => 'passenger_exp@smartbus.com',
            'password' => bcrypt('password123'),
        ]);
        $user->assignRole(UserRoles::PASSENGER);

        postJson('/api/login', [
            'email' => 'passenger_exp@smartbus.com',
            'password' => 'password123',
        ]);

        $token = $user->tokens()->first();

        // We check that the token's expires_at is not null and that the difference in days is 30
        expect($token->expires_at)->not->toBeNull();
        expect($token->expires_at->toDateTimeString())
            ->toBe(now()->addDays(30)->toDateTimeString());
    });

    it('assigns a 14-hour expiration to drivers', function () {
        $user = User::factory()->create([
            'email' => 'driver_exp@smartbus.com',
            'password' => bcrypt('password123'),
        ]);
        $user->assignRole(UserRoles::DRIVER);

        postJson('/api/login', [
            'email' => 'driver_exp@smartbus.com',
            'password' => 'password123',
        ]);

        $token = $user->tokens()->first();

        expect($token->expires_at)->not->toBeNull();
        expect($token->expires_at->toDateTimeString())
            ->toBe(now()->addHours(14)->toDateTimeString());
    });

    it('assigns an 8-hour expiration to company admins', function () {
        $user = User::factory()->create([
            'email' => 'admin_exp@smartbus.com',
            'password' => bcrypt('password123'),
        ]);
        $user->assignRole(UserRoles::COMPANY_ADMIN);

        postJson('/api/login', [
            'email' => 'admin_exp@smartbus.com',
            'password' => 'password123',
        ]);

        $token = $user->tokens()->first();

        expect($token->expires_at)->not->toBeNull();
        expect($token->expires_at->toDateTimeString())
            ->toBe(now()->addHours(8)->toDateTimeString());
    });

    it('assigns an 2-hour expiration to super admins', function () {
        $user = User::factory()->create([
            'email' => 'super_admin_exp@smartbus.com',
            'password' => bcrypt('password123'),
        ]);
        $user->assignRole(UserRoles::SUPER_ADMIN);

        postJson('/api/login', [
            'email' => 'super_admin_exp@smartbus.com',
            'password' => 'password123',
        ]);

        $token = $user->tokens()->first();

        expect($token->expires_at)->not->toBeNull();
        expect($token->expires_at->toDateTimeString())
            ->toBe(now()->addHours(2)->toDateTimeString());
    });
});
