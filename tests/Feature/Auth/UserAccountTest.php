<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
});

describe('Passenger Profile', function () {
    it('updates only the authenticated passenger profile', function () {
        $passenger = User::factory()->withRole(UserRole::Passenger)->create([
            'name' => 'Ana Perez',
            'email' => 'ana@example.com',
            'password' => 'Secret#123',
        ]);
        $otherUser = User::factory()->withRole(UserRole::Passenger)->create([
            'name' => 'Other User',
            'email' => 'other@example.com',
        ]);
        Sanctum::actingAs($passenger);

        $this->patchJson(route('user.update', ['include' => 'roles']), [
            'name' => 'Ana Maria Perez',
            'email' => 'ana.new@example.com',
            'current_password' => 'Secret#123',
            'roles' => [UserRole::Admin->value],
            'permissions' => ['Update:User'],
            'password' => 'Ignored#123',
            'id' => $otherUser->getKey(),
        ])->assertSuccessful()
            ->assertJsonPath('data.attributes.name', 'Ana Maria Perez')
            ->assertJsonPath('data.attributes.email', 'ana.new@example.com')
            ->assertJsonStructure(['data' => ['relationships' => ['roles']]]);

        expect($passenger->fresh()->hasRole(UserRole::Passenger))->toBeTrue()
            ->and($passenger->fresh()->hasRole(UserRole::Admin))->toBeFalse()
            ->and($otherUser->fresh()->name)->toBe('Other User');
    });

    it('returns 422 when email changes without the current password', function () {
        $passenger = User::factory()->withRole(UserRole::Passenger)->create([
            'email' => 'ana@example.com',
            'password' => 'Secret#123',
        ]);
        Sanctum::actingAs($passenger);

        $this->patchJson(route('user.update'), ['email' => 'ana.new@example.com'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/current_password');
    });

    it('returns 422 for a duplicate email or an incorrect current password', function () {
        $passenger = User::factory()->withRole(UserRole::Passenger)->create([
            'email' => 'ana@example.com',
            'password' => 'Secret#123',
        ]);
        User::factory()->create(['email' => 'taken@example.com']);
        Sanctum::actingAs($passenger);

        $this->patchJson(route('user.update'), [
            'email' => 'taken@example.com',
            'current_password' => 'Secret#123',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/email');

        $this->patchJson(route('user.update'), [
            'email' => 'ana.new@example.com',
            'current_password' => 'Wrong#123',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/current_password');
    });
});

describe('Passenger Password', function () {
    it('changes the password and revokes every other token', function () {
        $passenger = User::factory()->withRole(UserRole::Passenger)->create([
            'email' => 'ana@example.com',
            'password' => 'Secret#123',
        ]);
        $currentToken = $passenger->createToken('current-device');
        $otherToken = $passenger->createToken('other-device');

        $response = $this->withToken($currentToken->plainTextToken)
            ->putJson(route('user.password.update'), [
                'current_password' => 'Secret#123',
                'password' => 'N3w#Secret',
                'password_confirmation' => 'N3w#Secret',
            ]);

        $response->assertSuccessful()
            ->assertJsonPath('meta.message', __('auth.password_updated'));

        expect($passenger->fresh()->tokens()->pluck('id')->all())->toBe([$currentToken->accessToken->id])
            ->and(Hash::check('N3w#Secret', $passenger->fresh()->password))->toBeTrue()
            ->and($otherToken->accessToken->fresh())->toBeNull();
    });

    it('returns 422 when the new password is invalid', function (array $payload, string $pointer) {
        $passenger = User::factory()->withRole(UserRole::Passenger)->create([
            'password' => 'Secret#123',
        ]);
        Sanctum::actingAs($passenger);

        $this->putJson(route('user.password.update'), $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', $pointer);
    })->with([
        'wrong current password' => [
            ['current_password' => 'Wrong#123', 'password' => 'N3w#Secret', 'password_confirmation' => 'N3w#Secret'],
            '/data/attributes/current_password',
        ],
        'mismatched confirmation' => [
            ['current_password' => 'Secret#123', 'password' => 'N3w#Secret', 'password_confirmation' => 'Different#123'],
            '/data/attributes/password',
        ],
        'same password' => [
            ['current_password' => 'Secret#123', 'password' => 'Secret#123', 'password_confirmation' => 'Secret#123'],
            '/data/attributes/password',
        ],
        'weak password' => [
            ['current_password' => 'Secret#123', 'password' => 'weak', 'password_confirmation' => 'weak'],
            '/data/attributes/password',
        ],
    ]);

    it('returns 403 to a driver', function () {
        $driver = User::factory()->withRole(UserRole::Driver)->create();
        Sanctum::actingAs($driver);

        $this->patchJson(route('user.update'), ['name' => 'Not Allowed'])->assertForbidden();
        $this->putJson(route('user.password.update'), [])->assertForbidden();
    });

    it('translates validation messages to Spanish', function () {
        $passenger = User::factory()->withRole(UserRole::Passenger)->create();
        Sanctum::actingAs($passenger);

        $this->putJson(route('user.password.update'), [], ['Accept-Language' => 'es'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.detail', 'El campo contraseña actual es obligatorio.');
    });
});
