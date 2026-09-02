<?php

use App\Enums\UserRoles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\freezeTime;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

// Ensures that the database is refreshed for each test
pest()->use(RefreshDatabase::class);

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

        $response = postJson(route('register.passenger'), $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'id',
                    'attributes' => ['name', 'email', 'permissions'],
                ],
                'meta' => ['access_token', 'token_type', 'expires_at'],
            ])
            ->assertJsonPath('data.type', 'users')
            ->assertJsonPath('data.attributes.name', 'Pasajero de Prueba')
            ->assertJsonPath('data.attributes.email', 'pasajero@smartbus.com');

        // Check that the user was saved in the database
        assertDatabaseHas('users', [
            'email' => 'pasajero@smartbus.com',
        ]);

        // Check that the user has the 'passenger' role
        $user = User::where('email', 'pasajero@smartbus.com')->first();
        expect($user->hasRole(UserRoles::PASSENGER))->toBeTrue();
    });

    it('rejects registration when required data is missing', function () {
        $response = postJson(route('register.passenger'), []);

        $response->assertUnprocessable()
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

        $response = postJson(route('login', 'include=roles'), [
            'email' => 'login@smartbus.com',
            'password' => 'password123',
        ]);

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'id',
                    'attributes' => ['name', 'email', 'permissions'],
                    'relationships' => ['roles'],
                ],
                'meta' => ['access_token', 'token_type', 'expires_at'],
            ])
            ->assertJsonPath('data.type', 'users')
            ->assertJsonPath('meta.token_type', 'Bearer');
    });

    it('rejects login with invalid credentials', function () {
        User::factory()->create([
            'email' => 'login@smartbus.com',
            'password' => bcrypt('password123'),
        ]);

        $response = postJson(route('login'), [
            'email' => 'login@smartbus.com',
            'password' => 'contraseña-incorrecta',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('blocks login attempts after 5 failed tries (Rate Limiting)', function () {
        $email = 'hacker-'.fake()->unique()->safeEmail();

        User::factory()->create([
            'email' => $email,
            'password' => bcrypt('password123'),
        ]);

        // The first 5 failed attempts should be rejected normally.
        for ($i = 0; $i < 5; $i++) {
            postJson(route('login'), [
                'email' => $email,
                'password' => 'wrong-password',
            ]);
        }

        // The 6th attempt should be blocked by the route-level rate limiter.
        $response = postJson(route('login'), [
            'email' => $email,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
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
        $response = postJson(route('login'), [
            'email' => 'multidevice@smartbus.com',
            'password' => 'password123',
        ]);

        $response->assertSuccessful();

        // The user should still have only 1 active token (the new one),
        // because the 'old_device' token was deleted.
        expect($user->fresh()->tokens()->count())->toBe(1);
    });

    it('allows the user to log out successfully (protected route)', function () {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['*']);

        $response = postJson(route('logout'));

        $response->assertSuccessful()
            ->assertJsonPath('meta.message', __('auth.logged_out'));
    });
});

describe('Protected Routes (Sanctum)', function () {

    it('returns the authenticated user information', function () {
        $user = User::factory()->create();
        $user->assignRole(UserRoles::PASSENGER);

        // Simulate authentication with Sanctum
        Sanctum::actingAs($user, ['*']);

        $response = getJson(route('user', 'include=roles'));

        $response->assertSuccessful()
            ->assertJsonPath('data.type', 'users')
            ->assertJsonPath('data.attributes.email', $user->email)
            ->assertJsonPath('included.0.type', 'roles')
            ->assertJsonPath('included.0.attributes.value', UserRoles::PASSENGER->value)
            ->assertJsonPath('included.0.attributes.label', UserRoles::PASSENGER->label());
    });

    it('blocks access to user route when no token is provided', function () {
        $response = getJson(route('user'));

        $response->assertUnauthorized();
    });
});

describe('Internationalization (Locale)', function () {

    it('returns validation errors in Spanish when Accept-Language header is sent', function () {
        $response = postJson(route('login'), [], [
            'Accept-Language' => 'es',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'El campo correo electrónico es obligatorio.');
    });

    it('returns validation errors in English when no Accept-Language header is sent', function () {
        $response = postJson(route('login'));

        $response->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'The email field is required.');
    });
});

describe('Tokens Expiration by Role', function () {

    beforeEach(fn () => freezeTime());

    it('assigns a 30-day expiration to passengers', function () {
        $user = User::factory()->create([
            'email' => 'passenger_exp@smartbus.com',
            'password' => bcrypt('password123'),
        ]);
        $user->assignRole(UserRoles::PASSENGER);

        postJson(route('login'), [
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

        postJson(route('login'), [
            'email' => 'driver_exp@smartbus.com',
            'password' => 'password123',
        ]);

        $token = $user->tokens()->first();

        expect($token->expires_at)->not->toBeNull()
            ->and($token->expires_at->toDateTimeString())->toBe(now()->addHours(14)->toDateTimeString());
    });

    it('assigns an 8-hour expiration to company admins', function () {
        $user = User::factory()->create([
            'email' => 'admin_exp@smartbus.com',
            'password' => bcrypt('password123'),
        ]);
        $user->assignRole(UserRoles::COMPANY_ADMIN);

        postJson(route('login'), [
            'email' => 'admin_exp@smartbus.com',
            'password' => 'password123',
        ]);

        $token = $user->tokens()->first();

        expect($token->expires_at)->not->toBeNull()
            ->and($token->expires_at->toDateTimeString())->toBe(now()->addHours(8)->toDateTimeString());
    });

    it('assigns an 2-hour expiration to super admins', function () {
        $user = User::factory()->create([
            'email' => 'super_admin_exp@smartbus.com',
            'password' => bcrypt('password123'),
        ]);
        $user->assignRole(UserRoles::SUPER_ADMIN);

        postJson(route('login'), [
            'email' => 'super_admin_exp@smartbus.com',
            'password' => 'password123',
        ]);

        $token = $user->tokens()->first();

        expect($token->expires_at)->not->toBeNull()
            ->and($token->expires_at->toDateTimeString())->toBe(now()->addHours(2)->toDateTimeString());
    });
});
