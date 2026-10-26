<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Administracion\Infrastructure\Mail\EnlaceRestablecimientoMail;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class AccesoConUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_logs_in_with_username_and_email_is_not_an_identifier(): void
    {
        $user = UserFactory::new()->createOne([
            'usuario' => 'ana.rojas',
            'correo' => 'ana.rojas@example.invalid',
            'password' => 'CorrectPassword123!',
        ]);

        $this->postJson('/api/auth/login', [
            'usuario' => ' ANA.ROJAS ',
            'password' => 'CorrectPassword123!',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->getKey())
            ->assertJsonPath('user.usuario', 'ana.rojas')
            ->assertJsonPath('user.correo', 'ana.rojas@example.invalid')
            ->assertJsonPath('user.debe_cambiar_contrasena', false);

        $this->postJson('/api/auth/logout')->assertNoContent();

        $this->postJson('/api/auth/login', [
            'usuario' => 'ana.rojas@example.invalid',
            'password' => 'CorrectPassword123!',
        ])->assertUnauthorized();
    }

    public function test_login_requires_username_and_password(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['usuario', 'password']);
    }

    public function test_failed_login_reports_remaining_attempts(): void
    {
        config()->set('auth_security.max_failed_attempts', 5);
        $user = UserFactory::new()->createOne([
            'usuario' => 'intentos',
            'password' => 'CorrectPassword123!',
        ]);

        $this->postJson('/api/auth/login', [
            'usuario' => $user->usuario,
            'password' => 'IncorrectPassword123!',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('intentos_restantes', 4);
    }

    public function test_temporary_password_forces_change_before_protected_features(): void
    {
        $user = UserFactory::new()->createOne([
            'usuario' => 'temporal',
            'password' => 'Temporary123!',
            'password_changed_at' => null,
            'password_temporal_expira_en' => now()->addHours(72),
        ]);

        $this->postJson('/api/auth/login', [
            'usuario' => $user->usuario,
            'password' => 'Temporary123!',
        ])
            ->assertOk()
            ->assertJsonPath('user.debe_cambiar_contrasena', true);

        $this->getJson('/api/auth/sesion')
            ->assertOk()
            ->assertJsonPath('user.debe_cambiar_contrasena', true);

        $this->getJson('/api/usuarios')
            ->assertStatus(423)
            ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');

        foreach ([
            '/api/aulas',
            '/api/edificios',
            '/api/facultades',
            '/api/periodos/vigentes',
        ] as $rutaCatalogo) {
            $this->getJson($rutaCatalogo)
                ->assertStatus(423)
                ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
        }

        $this->postJson('/api/auth/cambiar-contrasena', [
            'contrasena_actual' => 'Temporary123!',
            'password' => 'NewStrongPassword123!',
            'password_confirmation' => 'NewStrongPassword123!',
        ])
            ->assertOk()
            ->assertJsonPath('user.debe_cambiar_contrasena', false);

        $actualizado = User::findOrFail($user->getKey());
        self::assertInstanceOf(User::class, $actualizado);
        self::assertTrue(Hash::check(
            'NewStrongPassword123!',
            $actualizado->getAuthPassword(),
        ));

        Auth::forgetGuards();
        $this->getJson('/api/aulas')->assertOk();
    }

    public function test_expired_temporary_password_is_rejected_with_recovery_guidance(): void
    {
        $user = UserFactory::new()->createOne([
            'usuario' => 'vencida',
            'password' => 'Temporary123!',
            'password_changed_at' => null,
            'password_temporal_expira_en' => now()->subSecond(),
        ]);

        $this->postJson('/api/auth/login', [
            'usuario' => $user->usuario,
            'password' => 'Temporary123!',
        ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'TEMPORARY_PASSWORD_EXPIRED');

        $this->assertGuest();
    }

    public function test_temporary_password_user_can_log_out_without_changing_the_password(): void
    {
        $user = UserFactory::new()->createOne([
            'password_changed_at' => null,
            'password_temporal_expira_en' => now()->addHours(72),
        ]);

        $this->actingAs($user)
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        $this->assertGuest();
    }

    public function test_recovery_email_can_reset_password_with_a_single_use_token(): void
    {
        Mail::fake();

        $user = UserFactory::new()->createOne([
            'usuario' => 'recuperar',
            'correo' => 'recuperar@example.invalid',
        ]);

        $this->postJson('/api/auth/recuperar-contrasena', [
            'usuario' => 'recuperar',
        ])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Si la cuenta tiene ese correo de contacto, enviaremos instrucciones para recuperar el acceso.',
            );

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $user->getKey(),
            'operacion' => 'contrasena.solicitar_restablecimiento',
            'tabla_afectada' => 'usuarios',
            'registro_id' => $user->getKey(),
            'descripcion' => 'Se solicitó un enlace de recuperación de contraseña.',
        ]);

        Mail::assertSent(EnlaceRestablecimientoMail::class);

        $token = DB::table('password_reset_tokens')
            ->where('email', $user->correo)
            ->value('token');

        self::assertIsString($token);

        Mail::assertSent(
            EnlaceRestablecimientoMail::class,
            function (EnlaceRestablecimientoMail $mail) use ($user, $token): bool {
                parse_str((string) parse_url($mail->enlace, PHP_URL_QUERY), $query);

                self::assertSame($user->correo, $query['correo'] ?? null);
                self::assertIsString($query['token'] ?? null);
                self::assertTrue(Hash::check($query['token'], $token));

                return true;
            },
        );

        $rawToken = null;
        Mail::assertSent(
            EnlaceRestablecimientoMail::class,
            function (EnlaceRestablecimientoMail $mail) use (&$rawToken): bool {
                parse_str((string) parse_url($mail->enlace, PHP_URL_QUERY), $query);
                $rawToken = $query['token'] ?? null;

                return true;
            },
        );

        self::assertIsString($rawToken);

        $this->postJson('/api/auth/restablecer-contrasena', [
            'correo' => $user->correo,
            'token' => $rawToken,
            'password' => 'RecoveredPassword123!',
            'password_confirmation' => 'RecoveredPassword123!',
        ])->assertOk();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $user->getKey(),
            'operacion' => 'contrasena.restablecer_por_enlace',
            'tabla_afectada' => 'usuarios',
            'registro_id' => $user->getKey(),
            'descripcion' => 'Contraseña restablecida mediante enlace de recuperación.',
        ]);

        $recuperado = User::findOrFail($user->getKey());
        self::assertInstanceOf(User::class, $recuperado);
        self::assertTrue(Hash::check(
            'RecoveredPassword123!',
            $recuperado->getAuthPassword(),
        ));

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->correo,
        ]);

        $this->postJson('/api/auth/restablecer-contrasena', [
            'correo' => $user->correo,
            'token' => $rawToken,
            'password' => 'AnotherRecoveredPassword123!',
            'password_confirmation' => 'AnotherRecoveredPassword123!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    public function test_expired_recovery_token_cannot_reset_password(): void
    {
        $user = UserFactory::new()->createOne([
            'usuario' => 'recuperacion-vencida',
            'correo' => 'recuperacion-vencida@example.invalid',
            'password' => 'OriginalPassword123!',
        ]);
        $token = 'recovery-token-expired';

        DB::table('password_reset_tokens')->insert([
            'email' => $user->correo,
            'token' => Hash::make($token),
            'created_at' => now()->subMinutes(60),
        ]);

        $this->postJson('/api/auth/restablecer-contrasena', [
            'correo' => $user->correo,
            'token' => $token,
            'password' => 'RecoveredPassword123!',
            'password_confirmation' => 'RecoveredPassword123!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);

        $usuarioSinCambios = User::findOrFail($user->getKey());
        self::assertInstanceOf(User::class, $usuarioSinCambios);
        self::assertTrue(Hash::check(
            'OriginalPassword123!',
            $usuarioSinCambios->getAuthPassword(),
        ));
    }

    public function test_recovery_response_does_not_reveal_unknown_emails(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/recuperar-contrasena', [
            'usuario' => 'usuario-inexistente',
        ]);

        $response
            ->assertOk()
            ->assertExactJson([
                'message' => 'Si la cuenta tiene ese correo de contacto, enviaremos instrucciones para recuperar el acceso.',
            ]);

        Mail::assertNothingSent();
    }

    public function test_recovery_request_after_throttle_sends_a_new_link_and_invalidates_the_old_one(): void
    {
        Mail::fake();

        $user = UserFactory::new()->createOne([
            'usuario' => 'reenvio',
            'correo' => 'reenvio@example.invalid',
        ]);

        $this->postJson('/api/auth/recuperar-contrasena', [
            'usuario' => $user->usuario,
        ])->assertOk();

        $correos = Mail::sent(EnlaceRestablecimientoMail::class);
        self::assertCount(1, $correos);
        $primerCorreo = $correos->first();
        self::assertInstanceOf(EnlaceRestablecimientoMail::class, $primerCorreo);
        parse_str((string) parse_url($primerCorreo->enlace, PHP_URL_QUERY), $primerQuery);
        $tokenAnterior = $primerQuery['token'] ?? null;
        self::assertIsString($tokenAnterior);

        $throttle = config('auth.passwords.users.throttle');
        self::assertIsInt($throttle);

        DB::table('password_reset_tokens')
            ->where('email', $user->correo)
            ->update([
                'created_at' => now()->subMinutes($throttle + 1),
            ]);

        $this->postJson('/api/auth/recuperar-contrasena', [
            'usuario' => $user->usuario,
        ])->assertOk();

        $correos = Mail::sent(EnlaceRestablecimientoMail::class);
        self::assertCount(2, $correos);
        $ultimoCorreo = $correos->last();
        self::assertInstanceOf(EnlaceRestablecimientoMail::class, $ultimoCorreo);
        parse_str((string) parse_url($ultimoCorreo->enlace, PHP_URL_QUERY), $segundoQuery);
        $tokenNuevo = $segundoQuery['token'] ?? null;
        self::assertIsString($tokenNuevo);
        self::assertNotSame($tokenAnterior, $tokenNuevo);

        $this->postJson('/api/auth/restablecer-contrasena', [
            'correo' => $user->correo,
            'token' => $tokenAnterior,
            'password' => 'RecoveredPassword123!',
            'password_confirmation' => 'RecoveredPassword123!',
        ])->assertUnprocessable()->assertJsonValidationErrors(['token']);

        $this->postJson('/api/auth/restablecer-contrasena', [
            'correo' => $user->correo,
            'token' => $tokenNuevo,
            'password' => 'RecoveredPassword123!',
            'password_confirmation' => 'RecoveredPassword123!',
        ])->assertOk();
    }
}
