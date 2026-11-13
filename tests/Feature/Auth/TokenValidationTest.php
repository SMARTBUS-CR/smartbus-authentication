<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

describe('Token Validation', function () {
    it('validates a token with an expiration date', function (): void {
        $this->freezeTime();

        $user = User::factory()->create();
        $expiresAt = now()->addHour();
        $token = $user->createToken('dashboard', ['*'], $expiresAt);

        $this->postJson(route('token.validate'), [], [
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ])
            ->assertOk()
            ->assertJsonPath('meta.valid', true)
            ->assertJsonPath(
                'meta.expires_at',
                $expiresAt->toIso8601String()
            );
    });

    it('validates a token without an expiration date', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('dashboard');

        $this->postJson(route('token.validate'), [], [
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ])
            ->assertOk()
            ->assertJsonPath('meta.valid', true)
            ->assertJsonPath('meta.expires_at', null);
    });

    it('rejects requests without a token', function (): void {
        $this->postJson(route('token.validate'))
            ->assertUnauthorized();
    });

    it('rejects an unknown token', function (): void {
        $this->postJson(route('token.validate'), [], [
            'Authorization' => 'Bearer invalid-token',
        ])
            ->assertUnauthorized();
    });

    it('rejects an expired token', function (): void {
        $this->freezeTime();

        $user = User::factory()->create();
        $token = $user->createToken(
            'dashboard',
            ['*'],
            now()->subMinute()
        );

        $this->postJson(route('token.validate'), [], [
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ])
            ->assertUnauthorized();
    });

    it('rejects a revoked token', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('dashboard');

        $token->accessToken->delete();

        $this->postJson(route('token.validate'), [], [
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ])
            ->assertUnauthorized();
    });

    it('blocks an unverified email without revoking the token', function (): void {
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('dashboard', ['*'], now()->addHour());

        $this->postJson(route('token.validate'), [], [
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ])
            ->assertForbidden();

        $this->assertModelExists($token->accessToken);
    });

    it('rejects a token belonging to a soft deleted user', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('dashboard', ['*'], now()->addHour());

        $user->delete();

        $this->postJson(route('token.validate'), [], [
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ])
            ->assertUnauthorized();
    });
});
