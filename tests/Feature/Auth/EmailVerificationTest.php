<?php

use App\Enums\UserRole;
use App\Mail\VerifyEmailCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\postJson;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => UserRole::PASSENGER, 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => UserRole::SUPER_ADMIN, 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => UserRole::ADMIN, 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => UserRole::DRIVER, 'guard_name' => 'web']);
});

describe('Email Verification (OTP)', function () {

    describe('Registration', function () {

        it('sends a verification code on registration and returns no token', function () {
            Mail::fake();

            $response = postJson(route('register.passenger'), [
                'name' => 'Test Passenger',
                'email' => 'passenger@smartbus.com',
                'password' => 'N7v!qL2#rX9@kP4',
                'password_confirmation' => 'N7v!qL2#rX9@kP4',
            ]);

            $response->assertCreated()
                ->assertJsonPath('meta.verification_required', true)
                ->assertJsonPath('data.attributes.email_verified', false);

            $response->assertJsonMissingPath('meta.access_token');

            $user = User::where('email', 'passenger@smartbus.com')->first();

            Mail::assertSent(VerifyEmailCode::class, function ($mail) use ($user) {
                return $mail->hasTo($user->email)
                    && preg_match('/^\d{6}$/', $mail->code) === 1;
            });

            assertDatabaseHas('email_verification_codes', [
                'email' => 'passenger@smartbus.com',
            ]);
        });

        it('sends a verification code on registration in Spanish', function () {
            Mail::fake();

            $response = postJson(route('register.passenger'), [
                'name' => 'Pasajero de Prueba',
                'email' => 'pasajero@smartbus.com',
                'password' => 'N7v!qL2#rX9@kP4',
                'password_confirmation' => 'N7v!qL2#rX9@kP4',
            ], ['Accept-Language' => 'es']);

            $response->assertCreated()
                ->assertJsonPath('meta.message', 'Te enviamos un código de verificación a tu correo.');
        });
    });

    describe('Verify', function () {

        it('verifies email with a valid code and returns an access token', function () {
            Mail::fake();

            postJson(route('register.passenger'), [
                'name' => 'Test Passenger',
                'email' => 'passenger@smartbus.com',
                'password' => 'N7v!qL2#rX9@kP4',
                'password_confirmation' => 'N7v!qL2#rX9@kP4',
            ])->assertCreated();

            $user = User::where('email', 'passenger@smartbus.com')->first();

            $code = null;
            Mail::assertSent(VerifyEmailCode::class, function ($mail) use ($user, &$code) {
                $code = $mail->code;

                return $mail->hasTo($user->email);
            });

            $response = postJson(route('email.verify'), [
                'email' => 'passenger@smartbus.com',
                'code' => $code,
            ]);

            $response->assertSuccessful()
                ->assertJsonStructure([
                    'data' => [
                        'type',
                        'id',
                        'attributes' => ['name', 'email', 'email_verified'],
                    ],
                    'meta' => ['access_token', 'token_type', 'expires_at'],
                ])
                ->assertJsonPath('data.attributes.email_verified', true);

            assertDatabaseMissing('email_verification_codes', [
                'email' => 'passenger@smartbus.com',
            ]);

            // Verify token works on a protected route
            $token = $response->json('meta.access_token');
            $this->getJson(route('user'), ['Authorization' => "Bearer {$token}"])
                ->assertSuccessful();
        });

        it('rejects reuse of a code after verify', function () {
            Mail::fake();
            postJson(route('register.passenger'), ['name' => 'T', 'email' => 're2@smartbus.com', 'password' => 'N7v!qL2#rX9@kP4', 'password_confirmation' => 'N7v!qL2#rX9@kP4'])
                ->assertCreated();

            $code = null;
            Mail::assertSent(VerifyEmailCode::class, function ($m) use (&$code) {
                $code = $m->code;

                return true;
            });

            postJson(route('email.verify'), ['email' => 're2@smartbus.com', 'code' => $code])->assertSuccessful();
            postJson(route('email.verify'), ['email' => 're2@smartbus.com', 'code' => $code])->assertBadRequest();
        });

        it('rejects verification with an incorrect code', function () {
            $user = User::factory()->create(['email' => 'test@smartbus.com']);
            DB::table('email_verification_codes')->insert([
                'email' => $user->email,
                'code' => Hash::make('111111'),
                'attempts' => 0,
                'created_at' => now(),
            ]);

            $responseEn = postJson(route('email.verify'), [
                'email' => $user->email,
                'code' => '999999',
            ]);

            $responseEn->assertBadRequest()
                ->assertJsonPath('errors.0.detail', 'The entered code is incorrect.');

            $responseEs = postJson(route('email.verify'), [
                'email' => $user->email,
                'code' => '999999',
            ], ['Accept-Language' => 'es']);

            $responseEs->assertBadRequest()
                ->assertJsonPath('errors.0.detail', 'El código ingresado es incorrecto.');
        });

        it('increments attempts on each wrong code', function () {
            $user = User::factory()->create(['email' => 'test@smartbus.com']);
            DB::table('email_verification_codes')->insert([
                'email' => $user->email,
                'code' => Hash::make('111111'),
                'attempts' => 0,
                'created_at' => now(),
            ]);

            postJson(route('email.verify'), ['email' => $user->email, 'code' => '999999'])
                ->assertBadRequest();

            expect(DB::table('email_verification_codes')->where('email', $user->email)->value('attempts'))
                ->toBe(1);

            postJson(route('email.verify'), ['email' => $user->email, 'code' => '888888'])
                ->assertBadRequest();

            expect(DB::table('email_verification_codes')->where('email', $user->email)->value('attempts'))
                ->toBe(2);
        });

        it('rejects and deletes the code after 5 wrong attempts', function () {
            $user = User::factory()->create(['email' => 'test@smartbus.com']);
            DB::table('email_verification_codes')->insert([
                'email' => $user->email,
                'code' => Hash::make('111111'),
                'attempts' => 5,
                'created_at' => now(),
            ]);

            $responseEn = postJson(route('email.verify'), [
                'email' => $user->email,
                'code' => '999999',
            ]);

            $responseEn->assertBadRequest()
                ->assertJsonPath('errors.0.detail', 'Too many incorrect attempts. Please request a new code.');

            assertDatabaseMissing('email_verification_codes', ['email' => $user->email]);

            // Spanish
            $user2 = User::factory()->create(['email' => 'test2@smartbus.com']);
            DB::table('email_verification_codes')->insert([
                'email' => $user2->email,
                'code' => Hash::make('111111'),
                'attempts' => 5,
                'created_at' => now(),
            ]);

            postJson(route('email.verify'), ['email' => $user2->email, 'code' => '999999'], ['Accept-Language' => 'es'])
                ->assertBadRequest()
                ->assertJsonPath('errors.0.detail', 'Demasiados intentos incorrectos. Por favor solicita un nuevo código.');
        });

        it('rejects expired codes and deletes the record', function () {
            $userEn = User::factory()->create(['email' => 'test_en@smartbus.com']);
            $userEs = User::factory()->create(['email' => 'test_es@smartbus.com']);
            $code = '123456';

            DB::table('email_verification_codes')->insert([
                ['email' => $userEn->email, 'code' => Hash::make($code), 'attempts' => 0, 'created_at' => now()->subMinutes(20)],
                ['email' => $userEs->email, 'code' => Hash::make($code), 'attempts' => 0, 'created_at' => now()->subMinutes(20)],
            ]);

            postJson(route('email.verify'), ['email' => $userEn->email, 'code' => $code])
                ->assertBadRequest()
                ->assertJsonPath('errors.0.detail', 'The verification code has expired. Please request a new one.');

            assertDatabaseMissing('email_verification_codes', ['email' => $userEn->email]);

            postJson(route('email.verify'), ['email' => $userEs->email, 'code' => $code], ['Accept-Language' => 'es'])
                ->assertBadRequest()
                ->assertJsonPath('errors.0.detail', 'El código de verificación ha expirado. Solicita uno nuevo.');

            assertDatabaseMissing('email_verification_codes', ['email' => $userEs->email]);
        });

        it('rejects if no code exists for the email', function () {
            $user = User::factory()->create(['email' => 'test@smartbus.com']);

            postJson(route('email.verify'), ['email' => $user->email, 'code' => '123456'])
                ->assertBadRequest()
                ->assertJsonPath('errors.0.detail', 'The entered code is incorrect.');
        });

        it('validates verify payload with 422', function () {
            postJson(route('email.verify'), ['email' => 'not-an-email', 'code' => '123'])
                ->assertUnprocessable();
        });
    });

    describe('Resend', function () {

        it('validates resend payload with 422', function () {
            postJson(route('email.resend'), ['email' => 'not-an-email'])->assertUnprocessable();
        });

        it('resends a new verification code and resets attempts', function () {
            Mail::fake();

            $user = User::factory()->unverified()->create(['email' => 'test@smartbus.com']);
            DB::table('email_verification_codes')->insert([
                'email' => $user->email,
                'code' => Hash::make('111111'),
                'attempts' => 3,
                'created_at' => now()->subMinutes(5),
            ]);

            $responseEn = postJson(route('email.resend'), ['email' => $user->email]);

            $responseEn->assertSuccessful()
                ->assertJsonPath('meta.message', 'A verification code has been sent to your email.');

            Mail::assertSent(VerifyEmailCode::class, fn ($mail) => $mail->hasTo($user->email));

            expect(DB::table('email_verification_codes')->where('email', $user->email)->value('attempts'))
                ->toBe(0);
        });

        it('invalidates the previous code after resend', function () {
            Mail::fake();
            postJson(route('register.passenger'), ['name' => 'T', 'email' => 're@smartbus.com', 'password' => 'N7v!qL2#rX9@kP4', 'password_confirmation' => 'N7v!qL2#rX9@kP4'])
                ->assertCreated();

            $old = null;
            Mail::assertSent(VerifyEmailCode::class, function ($m) use (&$old) {
                $old = $m->code;

                return true;
            });
            postJson(route('email.resend'), ['email' => 're@smartbus.com'])->assertSuccessful();
            postJson(route('email.verify'), ['email' => 're@smartbus.com', 'code' => $old])->assertBadRequest();
        });

        it('resends in Spanish when Accept-Language header is set', function () {
            Mail::fake();

            $user = User::factory()->unverified()->create(['email' => 'test@smartbus.com']);

            postJson(route('email.resend'), ['email' => $user->email], ['Accept-Language' => 'es'])
                ->assertSuccessful()
                ->assertJsonPath('meta.message', 'Te enviamos un código de verificación a tu correo.');
        });

        it('returns 200 and does not send email for already verified users', function () {
            Mail::fake();

            $user = User::factory()->create([
                'email' => 'verified@smartbus.com',
                'email_verified_at' => now(),
            ]);

            postJson(route('email.resend'), ['email' => $user->email])
                ->assertSuccessful()
                ->assertJsonPath('meta.message', 'A verification code has been sent to your email.');

            Mail::assertNotSent(VerifyEmailCode::class);
        });

        it('returns 200 and does not send email for non-existent emails', function () {
            Mail::fake();

            postJson(route('email.resend'), ['email' => 'nonexistent@smartbus.com'])
                ->assertSuccessful()
                ->assertJsonPath('meta.message', 'A verification code has been sent to your email.');

            Mail::assertNotSent(VerifyEmailCode::class);
        });
    });

    describe('Login with unverified email', function () {

        it('returns 403 when attempting to login with an unverified email', function () {
            $user = User::factory()->create([
                'email' => 'unverified@smartbus.com',
                'password' => bcrypt('password123'),
                'email_verified_at' => null,
            ]);
            $user->assignRole(UserRole::PASSENGER);

            postJson(route('login'), [
                'email' => 'unverified@smartbus.com',
                'password' => 'password123',
            ])->assertForbidden()
                ->assertJsonPath('errors.0.status', '403')
                ->assertJsonPath('errors.0.code', 'email_not_verified')
                ->assertJsonPath('errors.0.detail', 'You must verify your email before logging in.');
        });

        it('returns 403 in Spanish for unverified login', function () {
            $user = User::factory()->create([
                'email' => 'unverified@smartbus.com',
                'password' => bcrypt('password123'),
                'email_verified_at' => null,
            ]);
            $user->assignRole(UserRole::PASSENGER);

            postJson(route('login'), [
                'email' => 'unverified@smartbus.com',
                'password' => 'password123',
            ], ['Accept-Language' => 'es'])
                ->assertForbidden()
                ->assertJsonPath('errors.0.detail', 'Debes verificar tu correo antes de iniciar sesión.');
        });

        it('returns 422 for wrong password (not 403), even for unverified users', function () {
            User::factory()->create([
                'email' => 'unverified@smartbus.com',
                'password' => bcrypt('correct-password'),
                'email_verified_at' => null,
            ]);

            postJson(route('login'), [
                'email' => 'unverified@smartbus.com',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        });
    });

    describe('Email Rendering', function () {

        it('renders the verification email in English', function () {
            $mailable = new VerifyEmailCode('482910', 'John', 'en');

            $mailable->assertHasSubject('Email Verification Code');
            $mailable->assertSeeInHtml('482910');
            $mailable->assertSeeInHtml('Hello, John:');
            $mailable->assertSeeInHtml('Email Verification');
            $mailable->assertSeeInHtml('15 minutes');
            $mailable->assertSeeInHtml("Didn't create an account?");
        });

        it('renders the verification email in Spanish', function () {
            $mailable = new VerifyEmailCode('482910', 'Carlos', 'es');

            $mailable->assertHasSubject('Código de verificación de correo');
            $mailable->assertSeeInHtml('482910');
            $mailable->assertSeeInHtml('Hola, Carlos:');
            $mailable->assertSeeInHtml('Verificación de correo');
            $mailable->assertSeeInHtml('15 minutos');
            $mailable->assertSeeInHtml('¿No creaste una cuenta?');
        });
    });
});
