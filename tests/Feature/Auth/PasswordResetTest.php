<?php

use App\Mail\ResetPasswordCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\postJson;

pest()->use(RefreshDatabase::class);

describe('Password Reset (OTP)', function () {

    it('sends an email with a 6-digit code to an existing user in default English', function () {
        Mail::fake();
        $user = User::factory()->create(['email' => 'test@smartbus.com']);

        $response = postJson(route('password.forgot'), ['email' => 'test@smartbus.com']);

        $response->assertSuccessful()
            ->assertJsonPath('message', 'A password recovery code has been sent to your email.');

        Mail::assertSent(
            ResetPasswordCode::class,
            fn ($mail) => $mail->hasTo($user->email)
                && preg_match('/^\d{6}$/', $mail->code) === 1
        );

        expect(DB::table('password_reset_tokens')->where('email', 'test@smartbus.com')->exists())->toBeTrue();
    });

    it('sends an email with a 6-digit code in Spanish when Accept-Language header is set', function () {
        Mail::fake();
        $user = User::factory()->create(['email' => 'test_es@smartbus.com']);

        $response = postJson(route('password.forgot'), ['email' => 'test_es@smartbus.com'], [
            'Accept-Language' => 'es',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('message', 'Se ha enviado un código de recuperación a tu correo.');

        Mail::assertSent(
            ResetPasswordCode::class,
            fn ($mail) => $mail->hasTo($user->email)
                && preg_match('/^\d{6}$/', $mail->code) === 1
        );
    });

    it('allows resetting the password with a valid code', function () {
        Mail::fake();
        $oldPassword = 'N7v!qL2#rX9@kP4';
        $newPassword = 'P9@kX4!vN7#qL2r';
        $user = User::factory()->create([
            'email' => 'test@smartbus.com',
            'password' => $oldPassword,
        ]);

        postJson(route('password.forgot'), ['email' => $user->email])
            ->assertSuccessful();

        $code = null;
        Mail::assertSent(ResetPasswordCode::class, function ($mail) use ($user, &$code) {
            $code = $mail->code;

            return $mail->hasTo($user->email);
        });

        $response = postJson(route('password.reset'), [
            'email' => $user->email,
            'code' => $code,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('message', 'Password updated successfully.');

        expect(DB::table('password_reset_tokens')->count())->toBe(0)
            ->and(Hash::check($newPassword, $user->fresh()->password))->toBeTrue();

        postJson(route('login'), [
            'email' => $user->email,
            'password' => $oldPassword,
        ])->assertUnprocessable();

        postJson(route('login'), [
            'email' => $user->email,
            'password' => $newPassword,
        ])->assertSuccessful();
    });

    it('allows resetting the password in Spanish when Accept-Language header is set', function () {
        $user = User::factory()->create([
            'email' => 'test_spanish@smartbus.com',
        ]);

        $code = '654321';
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => bcrypt($code),
            'created_at' => now(),
        ]);

        $newPassword = 'P9@kX4!vN7#qL2r';
        $response = postJson(route('password.reset'), [
            'email' => $user->email,
            'code' => $code,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ], [
            'Accept-Language' => 'es',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('message', 'Contraseña actualizada correctamente.');
    });

    it('rejects incorrect codes in both locales', function () {
        $user = User::factory()->create(['email' => 'test@smartbus.com']);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => bcrypt('111111'),
            'created_at' => now(),
        ]);

        $validPassword = 'P9@kX4!vN7#qL2r';

        $responseEn = postJson(route('password.reset'), [
            'email' => $user->email,
            'code' => '999999',
            'password' => $validPassword,
            'password_confirmation' => $validPassword,
        ]);

        $responseEn->assertBadRequest()
            ->assertJsonPath('message', 'The entered code is incorrect.');

        $responseEs = postJson(route('password.reset'), [
            'email' => $user->email,
            'code' => '999999',
            'password' => $validPassword,
            'password_confirmation' => $validPassword,
        ], [
            'Accept-Language' => 'es',
        ]);

        $responseEs->assertBadRequest()
            ->assertJsonPath('message', 'El código ingresado es incorrecto.');
    });

    it('rejects expired codes in both locales', function () {
        $userEn = User::factory()->create(['email' => 'test_en@smartbus.com']);
        $userEs = User::factory()->create(['email' => 'test_es@smartbus.com']);
        $code = '123456';

        // Simulate tokens from 20 minutes ago
        DB::table('password_reset_tokens')->insert([
            ['email' => $userEn->email, 'token' => bcrypt($code), 'created_at' => now()->subMinutes(20)],
            ['email' => $userEs->email, 'token' => bcrypt($code), 'created_at' => now()->subMinutes(20)],
        ]);

        $validPassword = 'P9@kX4!vN7#qL2r';

        $responseEn = postJson(route('password.reset'), [
            'email' => $userEn->email,
            'code' => $code,
            'password' => $validPassword,
            'password_confirmation' => $validPassword,
        ]);

        $responseEn->assertBadRequest()
            ->assertJsonPath('message', 'The code has expired. Please request a new one.');

        $responseEs = postJson(route('password.reset'), [
            'email' => $userEs->email,
            'code' => $code,
            'password' => $validPassword,
            'password_confirmation' => $validPassword,
        ], [
            'Accept-Language' => 'es',
        ]);

        $responseEs->assertBadRequest()
            ->assertJsonPath('message', 'El código ha expirado. Solicita uno nuevo.');
    });

    it('renders the reset password email in Spanish', function () {
        $mailable = new ResetPasswordCode('482910', 'Carlos', 'es');

        $mailable->assertHasSubject('Código de recuperación de contraseña');
        $mailable->assertSeeInHtml('482910');
        $mailable->assertSeeInHtml('Hola, Carlos:');
        $mailable->assertSeeInHtml('Seguridad de la Cuenta');
        $mailable->assertSeeInHtml('15 minutos');
        $mailable->assertSeeInHtml('¿No solicitaste este cambio?');
    });

    it('renders the reset password email in English', function () {
        $mailable = new ResetPasswordCode('482910', 'John', 'en');

        $mailable->assertHasSubject('Password Reset Code');
        $mailable->assertSeeInHtml('482910');
        $mailable->assertSeeInHtml('Hello, John:');
        $mailable->assertSeeInHtml('Account Security');
        $mailable->assertSeeInHtml('15 minutes');
        $mailable->assertSeeInHtml("Didn't request this change?");
    });
});
