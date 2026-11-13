<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

pest()->use(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
});

describe('Passenger Account Deletion', function (): void {
    it('soft deletes the authenticated passenger and revokes all their tokens', function (): void {
        $passenger = User::factory()
            ->withRole(UserRole::Passenger)
            ->create([
                'password' => 'Secret#123',
            ]);

        $otherPassenger = User::factory()
            ->withRole(UserRole::Passenger)
            ->create();

        $currentToken = $passenger->createToken('current-device');
        $passenger->createToken('other-device');

        $otherToken = $otherPassenger->createToken('other-passenger-device');

        $this->withToken($currentToken->plainTextToken)
            ->deleteJson('/api/user', [
                'current_password' => 'Secret#123',
                'confirm_deletion' => true,
            ], [
                'Accept-Language' => 'en',
            ])
            ->assertOk()
            ->assertJsonPath(
                'meta.message',
                'Your account has been deleted successfully.',
            );

        $this->assertSoftDeleted($passenger);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => $passenger->getMorphClass(),
            'tokenable_id' => $passenger->getKey(),
        ]);

        $this->assertModelExists($otherPassenger);
        $this->assertModelExists($otherToken->accessToken);

        expect($otherPassenger->fresh()->trashed())->toBeFalse();
    });

    it('rejects accounts without an exclusive passenger role', function (?UserRole $role): void {
        $user = User::factory()->create([
            'password' => 'Secret#123',
        ]);

        if ($role !== null) {
            $user->assignRole($role->value);
        }

        $token = $user->createToken('current-device');

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/user', [
                'current_password' => 'Secret#123',
                'confirm_deletion' => true,
            ])
            ->assertForbidden();

        expect($user->fresh()->trashed())->toBeFalse();

        $this->assertModelExists($token->accessToken);
    })->with([
        'super administrator' => [UserRole::SuperAdmin],
        'administrator' => [UserRole::Admin],
        'driver' => [UserRole::Driver],
        'no assigned role' => [null],
    ]);

    it('rejects passengers with an additional global role', function (UserRole $role): void {
        $passenger = User::factory()
            ->withRole(UserRole::Passenger)
            ->create([
                'password' => 'Secret#123',
            ]);

        $passenger->assignRole($role->value);

        $token = $passenger->createToken('current-device');

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/user', [
                'current_password' => 'Secret#123',
                'confirm_deletion' => true,
            ])
            ->assertForbidden();

        expect($passenger->fresh()->trashed())->toBeFalse();

        $this->assertModelExists($token->accessToken);
    })->with([
        'super administrator' => [UserRole::SuperAdmin],
        'administrator' => [UserRole::Admin],
        'driver' => [UserRole::Driver],
    ]);

    it('rejects a passenger with an administrator role in another company', function (): void {
        $passenger = User::factory()
            ->withRole(UserRole::Passenger)
            ->create([
                'password' => 'Secret#123',
            ]);

        $previousTeamId = getPermissionsTeamId();
        $companyId = (string) Str::uuid();

        try {
            setPermissionsTeamId($companyId);

            $companyRole = Role::query()->create([
                'name' => UserRole::Admin->value,
                'guard_name' => 'web',
                'company_id' => $companyId,
            ]);

            $passenger->unsetRelation('roles')->unsetRelation('permissions');
            $passenger->assignRole($companyRole);
        } finally {
            setPermissionsTeamId($previousTeamId);
            $passenger->unsetRelation('roles')->unsetRelation('permissions');
        }

        // The company administrator role is hidden in the global team context.
        expect($passenger->hasRole(UserRole::Passenger))->toBeTrue()
            ->and($passenger->hasRole(UserRole::Admin))->toBeFalse();

        $token = $passenger->createToken('current-device');

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/user', [
                'current_password' => 'Secret#123',
                'confirm_deletion' => true,
            ])
            ->assertForbidden();

        expect($passenger->fresh()->trashed())->toBeFalse();

        $this->assertModelExists($token->accessToken);
    });

    it('rejects invalid deletion credentials without changing the account or tokens', function (array $payload, string $field): void {
        $passenger = User::factory()
            ->withRole(UserRole::Passenger)
            ->create([
                'password' => 'Secret#123',
            ]);

        $currentToken = $passenger->createToken('current-device');
        $otherToken = $passenger->createToken('other-device');

        $this->withToken($currentToken->plainTextToken)
            ->deleteJson('/api/user', $payload, [
                'Accept-Language' => 'en',
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.0.source.pointer',
                '/data/attributes/'.$field,
            );

        expect($passenger->fresh()->trashed())->toBeFalse();

        $this->assertModelExists($currentToken->accessToken);
        $this->assertModelExists($otherToken->accessToken);
    })->with([
        'missing current password' => [
            ['confirm_deletion' => true],
            'current_password',
        ],
        'incorrect current password' => [
            [
                'current_password' => 'Wrong#123',
                'confirm_deletion' => true,
            ],
            'current_password',
        ],
        'invalid current password type' => [
            [
                'current_password' => ['Secret#123'],
                'confirm_deletion' => true,
            ],
            'current_password',
        ],
        'missing confirmation' => [
            ['current_password' => 'Secret#123'],
            'confirm_deletion',
        ],
        'rejected confirmation' => [
            [
                'current_password' => 'Secret#123',
                'confirm_deletion' => false,
            ],
            'confirm_deletion',
        ],
        'invalid confirmation' => [
            [
                'current_password' => 'Secret#123',
                'confirm_deletion' => 'yes',
            ],
            'confirm_deletion',
        ],
    ]);

    it('rejects missing or invalid authentication without changing the account', function (?string $bearerToken): void {
        $passenger = User::factory()
            ->withRole(UserRole::Passenger)
            ->create([
                'password' => 'Secret#123',
            ]);

        $existingToken = $passenger->createToken('existing-device');

        $headers = ['Accept-Language' => 'en'];

        if ($bearerToken !== null) {
            $headers['Authorization'] = 'Bearer '.$bearerToken;
        }

        $this->deleteJson('/api/user', [
            'current_password' => 'Secret#123',
            'confirm_deletion' => true,
        ], $headers)
            ->assertUnauthorized()
            ->assertJsonPath('errors.0.status', '401');

        expect($passenger->fresh()->trashed())->toBeFalse();

        $this->assertModelExists($existingToken->accessToken);
    })->with([
        'missing token' => [null],
        'invalid token' => ['invalid-access-token'],
    ]);

    it('rejects an unverified passenger without deleting their account or tokens', function (): void {
        $passenger = User::factory()
            ->unverified()
            ->withRole(UserRole::Passenger)
            ->create([
                'password' => 'Secret#123',
            ]);

        $currentToken = $passenger->createToken('current-device');
        $otherToken = $passenger->createToken('other-device');

        $this->withToken($currentToken->plainTextToken)
            ->deleteJson('/api/user', [
                'current_password' => 'Secret#123',
                'confirm_deletion' => true,
            ])
            ->assertForbidden();

        expect($passenger->fresh()->trashed())->toBeFalse();

        $this->assertModelExists($currentToken->accessToken);
        $this->assertModelExists($otherToken->accessToken);
    });

    it('rejects expired or revoked tokens without deleting the account', function (string $tokenState): void {
        $passenger = User::factory()
            ->withRole(UserRole::Passenger)
            ->create([
                'password' => 'Secret#123',
            ]);

        $token = $passenger->createToken(
            'unusable-device',
            ['*'],
            $tokenState === 'expired' ? now()->subMinute() : null,
        );

        if ($tokenState === 'revoked') {
            $token->accessToken->delete();
        }

        $validToken = $passenger->createToken('valid-device');

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/user', [
                'current_password' => 'Secret#123',
                'confirm_deletion' => true,
            ])
            ->assertUnauthorized();

        expect($passenger->fresh()->trashed())->toBeFalse();

        $this->assertModelExists($validToken->accessToken);
    })->with([
        'expired token' => ['expired'],
        'revoked token' => ['revoked'],
    ]);

    it('ignores a supplied target identifier and protected account fields', function (): void {
        $passenger = User::factory()
            ->withRole(UserRole::Passenger)
            ->create([
                'password' => 'Secret#123',
            ]);

        $otherPassenger = User::factory()
            ->withRole(UserRole::Passenger)
            ->create([
                'name' => 'Other Passenger',
                'email' => 'other@example.com',
            ]);

        $originalAttributes = $passenger->getRawOriginal();

        $currentToken = $passenger->createToken('current-device');
        $otherToken = $otherPassenger->createToken('other-passenger-device');

        $this->withToken($currentToken->plainTextToken)
            ->deleteJson('/api/user', [
                'current_password' => 'Secret#123',
                'confirm_deletion' => true,
                'id' => $otherPassenger->getKey(),
                'user_id' => $otherPassenger->getKey(),
                'name' => 'Injected Name',
                'email' => 'injected@example.com',
                'password' => 'Injected#123',
                'roles' => [UserRole::SuperAdmin->value],
            ])
            ->assertOk();

        $this->assertSoftDeleted($passenger);

        $deletedPassenger = User::withTrashed()
            ->findOrFail($passenger->getKey());

        foreach (['id', 'name', 'email', 'password'] as $attribute) {
            expect($deletedPassenger->getRawOriginal($attribute))
                ->toBe($originalAttributes[$attribute]);
        }

        expect($deletedPassenger->hasRole(UserRole::SuperAdmin))->toBeFalse();

        $this->assertDatabaseHas('users', [
            'id' => $otherPassenger->getKey(),
            'name' => 'Other Passenger',
            'email' => 'other@example.com',
            'deleted_at' => null,
        ]);

        $this->assertModelExists($otherToken->accessToken);
    });

    it('shares the deletion attempt limit across tokens of the same account', function (): void {
        $this->freezeTime();

        $passenger = User::factory()
            ->withRole(UserRole::Passenger)
            ->create([
                'password' => 'Secret#123',
            ]);

        $firstToken = $passenger->createToken('first-device');
        $secondToken = $passenger->createToken('second-device');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            app('auth')->forgetGuards();

            $token = $attempt % 2 === 0 ? $firstToken : $secondToken;

            $this->withToken($token->plainTextToken)
                ->deleteJson('/api/user', [
                    'current_password' => 'Wrong#123',
                    'confirm_deletion' => true,
                ])
                ->assertUnprocessable();
        }

        app('auth')->forgetGuards();

        $this->withToken($secondToken->plainTextToken)
            ->deleteJson('/api/user', [
                'current_password' => 'Secret#123',
                'confirm_deletion' => true,
            ])
            ->assertTooManyRequests()
            ->assertJsonPath('errors.0.status', '429');

        expect($passenger->fresh()->trashed())->toBeFalse();

        $this->assertModelExists($firstToken->accessToken);
        $this->assertModelExists($secondToken->accessToken);
    });

    it('rolls back token revocation when account deletion fails', function (): void {
        $passenger = User::factory()
            ->withRole(UserRole::Passenger)
            ->create([
                'password' => 'Secret#123',
            ]);

        $currentToken = $passenger->createToken('current-device');
        $otherToken = $passenger->createToken('other-device');

        $originalDispatcher = User::getEventDispatcher();

        User::setEventDispatcher(clone $originalDispatcher);

        try {
            User::deleting(function (User $user) use ($passenger): void {
                if ($user->getKey() === $passenger->getKey()) {
                    throw new RuntimeException('Simulated account deletion failure.');
                }
            });

            $this->withToken($currentToken->plainTextToken)
                ->deleteJson('/api/user', [
                    'current_password' => 'Secret#123',
                    'confirm_deletion' => true,
                ], [
                    'Accept-Language' => 'en',
                ])
                ->assertStatus(500)
                ->assertJsonPath(
                    'errors.0.detail',
                    'An unexpected error occurred.',
                );
        } finally {
            User::setEventDispatcher($originalDispatcher);
        }

        expect($passenger->fresh()->trashed())->toBeFalse();

        $this->assertModelExists($currentToken->accessToken);
        $this->assertModelExists($otherToken->accessToken);
    });

    it('does not send password recovery codes to a deleted passenger', function (): void {
        Mail::fake();

        $passenger = User::factory()
            ->withRole(UserRole::Passenger)
            ->create([
                'password' => 'Secret#123',
            ]);

        $token = $passenger->createToken('current-device');

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/user', [
                'current_password' => 'Secret#123',
                'confirm_deletion' => true,
            ])
            ->assertOk();

        app('auth')->forgetGuards();

        $this->withHeader('Authorization', '')
            ->postJson('/api/password/forgot', [
                'email' => $passenger->email,
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.0.source.pointer',
                '/data/attributes/email',
            );

        Mail::assertNothingSent();

        $this->assertSoftDeleted($passenger);

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $passenger->email,
        ]);
    });

    it('rejects a previously issued password reset code after account deletion', function (): void {
        Http::preventStrayRequests();

        Http::fake([
            'https://api.pwnedpasswords.com/range/*' => Http::response('', 200),
        ]);

        $passenger = User::factory()
            ->withRole(UserRole::Passenger)
            ->create([
                'password' => 'Secret#123',
            ]);

        $originalPasswordHash = $passenger->getRawOriginal('password');
        $resetCode = '123456';

        DB::table('password_reset_tokens')->insert([
            'email' => $passenger->email,
            'token' => Hash::make($resetCode),
            'created_at' => now(),
        ]);

        $token = $passenger->createToken('current-device');

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/user', [
                'current_password' => 'Secret#123',
                'confirm_deletion' => true,
            ])
            ->assertOk();

        app('auth')->forgetGuards();

        $this->withHeader('Authorization', '')
            ->postJson('/api/password/reset', [
                'email' => $passenger->email,
                'code' => $resetCode,
                'password' => 'N7v!qL2#rX9@kP4',
                'password_confirmation' => 'N7v!qL2#rX9@kP4',
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.0.source.pointer',
                '/data/attributes/email',
            );

        $this->assertSoftDeleted($passenger);

        $deletedPassenger = User::withTrashed()
            ->findOrFail($passenger->getKey());

        expect($deletedPassenger->getRawOriginal('password'))
            ->toBe($originalPasswordHash);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => $passenger->getMorphClass(),
            'tokenable_id' => $passenger->getKey(),
        ]);
    });

    it('rejects former access tokens after account deletion', function (string $device): void {
        $passenger = User::factory()
            ->withRole(UserRole::Passenger)
            ->create([
                'password' => 'Secret#123',
            ]);

        $currentToken = $passenger->createToken('current-device');
        $otherToken = $passenger->createToken('other-device');

        $this->withToken($currentToken->plainTextToken)
            ->deleteJson('/api/user', [
                'current_password' => 'Secret#123',
                'confirm_deletion' => true,
            ])
            ->assertOk();

        app('auth')->forgetGuards();

        $formerToken = $device === 'current' ? $currentToken : $otherToken;

        $this->withToken($formerToken->plainTextToken)
            ->getJson('/api/user')
            ->assertUnauthorized();

        $this->assertSoftDeleted($passenger);
    })->with([
        'current device' => ['current'],
        'other device' => ['other'],
    ]);

    it('rejects login credentials after account deletion', function (): void {
        $passenger = User::factory()
            ->withRole(UserRole::Passenger)
            ->create([
                'password' => 'Secret#123',
            ]);

        $token = $passenger->createToken('current-device');

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/user', [
                'current_password' => 'Secret#123',
                'confirm_deletion' => true,
            ])
            ->assertOk();

        app('auth')->forgetGuards();

        $this->withHeader('Authorization', '')
            ->postJson('/api/login', [
                'email' => $passenger->email,
                'password' => 'Secret#123',
            ], [
                'Accept-Language' => 'en',
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.0.detail',
                'These credentials do not match our records.',
            )
            ->assertJsonMissingPath('meta.access_token');

        $this->assertSoftDeleted($passenger);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => $passenger->getMorphClass(),
            'tokenable_id' => $passenger->getKey(),
        ]);
    });

    it('returns the deletion confirmation in Spanish', function (): void {
        $passenger = User::factory()
            ->withRole(UserRole::Passenger)
            ->create([
                'password' => 'Secret#123',
            ]);

        $token = $passenger->createToken('current-device');

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/user', [
                'current_password' => 'Secret#123',
                'confirm_deletion' => true,
            ], [
                'Accept-Language' => 'es',
            ])
            ->assertOk()
            ->assertJsonPath(
                'meta.message',
                'Tu cuenta ha sido eliminada correctamente.',
            );

        $this->assertSoftDeleted($passenger);
    });

    it('returns the incorrect password error in Spanish', function (): void {
        $passenger = User::factory()
            ->withRole(UserRole::Passenger)
            ->create([
                'password' => 'Secret#123',
            ]);

        $token = $passenger->createToken('current-device');

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/user', [
                'current_password' => 'Wrong#123',
                'confirm_deletion' => true,
            ], [
                'Accept-Language' => 'es',
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.0.source.pointer',
                '/data/attributes/current_password',
            )
            ->assertJsonPath(
                'errors.0.detail',
                'La contraseña es incorrecta.',
            );

        expect($passenger->fresh()->trashed())->toBeFalse();

        $this->assertModelExists($token->accessToken);
    });

    it('rejects deletion credentials supplied only in the query string', function (): void {
        $passenger = User::factory()
            ->withRole(UserRole::Passenger)
            ->create([
                'password' => 'Secret#123',
            ]);

        $token = $passenger->createToken('current-device');

        $query = http_build_query([
            'current_password' => 'Secret#123',
            'confirm_deletion' => 1,
        ]);

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/user?'.$query, [])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.0.source.pointer',
                '/data/attributes/current_password',
            );

        expect($passenger->fresh()->trashed())->toBeFalse();

        $this->assertModelExists($token->accessToken);
    });
});
