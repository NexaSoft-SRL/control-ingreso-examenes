<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Administracion\Infrastructure\Mail\EnlaceRestablecimientoMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RestablecimientoContrasenaTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_email_with_a_link_is_sent_to_a_registered_account(): void
    {
        Mail::fake();
        $this->usuario();

        $this->postJson('/api/auth/contrasena/olvide', [
            'usuario' => 'docente.prueba',
        ])->assertOk();

        Mail::assertSent(
            EnlaceRestablecimientoMail::class,
            static fn (EnlaceRestablecimientoMail $correo): bool => $correo->hasTo('docente@umss.edu.bo')
                && str_contains($correo->enlace, '/restablecer-contrasena?token=')
                && str_ends_with($correo->enlace, '&usuario=docente.prueba')
                && $correo->minutosVigencia === 60,
        );

        $this->assertTrue(
            DB::table('password_reset_tokens')
                ->where('email', 'docente@umss.edu.bo')
                ->exists()
        );
    }

    public function test_an_unknown_account_gets_the_same_answer_and_no_email(): void
    {
        Mail::fake();

        $this->usuario();

        $conocida = $this->postJson('/api/auth/contrasena/olvide', [
            'usuario' => 'docente.prueba',
        ])->assertOk();

        $desconocida = $this->postJson('/api/auth/contrasena/olvide', [
            'usuario' => 'nadie',
        ])->assertOk();

        $conocida->assertExactJson([
            'message' => 'Si la cuenta tiene un correo registrado, le enviamos un enlace.',
        ]);

        $this->assertSame(
            $conocida->getContent(),
            $desconocida->getContent()
        );

        Mail::assertSentCount(1);
    }

    public function test_an_account_without_contact_email_gets_the_same_answer_and_no_email(): void
    {
        Mail::fake();

        User::create([
            'name' => 'Auxiliar sin correo',
            'usuario' => 'sin.correo',
            'password' => 'Clave-Inicial-1',
        ]);

        $this->postJson('/api/auth/contrasena/olvide', [
            'usuario' => 'sin.correo',
        ])
            ->assertOk()
            ->assertExactJson([
                'message' => 'Si la cuenta tiene un correo registrado, le enviamos un enlace.',
            ]);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertDatabaseMissing('bitacora_operaciones', [
            'operacion' => 'usuario.solicitar_restablecimiento',
        ]);
    }

    public function test_a_blocked_account_does_not_get_a_link(): void
    {
        Mail::fake();

        $this->usuario()->forceFill(['is_active' => false])->save();

        $this->postJson('/api/auth/contrasena/olvide', [
            'usuario' => 'docente.prueba',
        ])->assertOk();

        Mail::assertNothingSent();
    }

    public function test_the_request_asks_for_a_user_name(): void
    {
        $this->postJson('/api/auth/contrasena/olvide', [
            'correo' => 'docente@umss.edu.bo',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('usuario');
    }

    public function test_the_password_is_reset_with_a_valid_link(): void
    {
        $user = $this->usuario();
        $token = $this->tokenPara('docente@umss.edu.bo');

        $this->postJson('/api/auth/contrasena/restablecer', [
            'usuario' => 'docente.prueba',
            'token' => $token,
            'contrasena_nueva' => 'Clave-Nueva-2',
            'contrasena_nueva_confirmation' => 'Clave-Nueva-2',
        ])->assertOk();

        $user->refresh();

        $this->assertTrue(Hash::check('Clave-Nueva-2', $user->getAuthPassword()));
        $this->assertNotNull($user->password_changed_at);

        // Ya con contrasena propia, entra sin pasar por el primer ingreso.
        $this->postJson('/api/auth/login', [
            'usuario' => 'docente.prueba',
            'password' => 'Clave-Nueva-2',
        ])
            ->assertOk()
            ->assertJsonPath('user.debe_cambiar_contrasena', false);
    }

    public function test_the_new_password_follows_the_same_rule_as_the_change(): void
    {
        $user = $this->usuario();
        $token = $this->tokenPara('docente@umss.edu.bo');

        $this->postJson('/api/auth/contrasena/restablecer', [
            'usuario' => 'docente.prueba',
            'token' => $token,
            'contrasena_nueva' => 'clave-debil',
            'contrasena_nueva_confirmation' => 'clave-debil',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contrasena_nueva');

        $this->assertTrue(
            Hash::check('Clave-inicial-1', $user->refresh()->getAuthPassword())
        );
    }

    public function test_the_link_serves_only_once(): void
    {
        $this->usuario();
        $token = $this->tokenPara('docente@umss.edu.bo');

        $datos = [
            'usuario' => 'docente.prueba',
            'token' => $token,
            'contrasena_nueva' => 'Clave-Nueva-2',
            'contrasena_nueva_confirmation' => 'Clave-Nueva-2',
        ];

        $this->postJson('/api/auth/contrasena/restablecer', $datos)->assertOk();
        $this->postJson('/api/auth/contrasena/restablecer', $datos)
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'El enlace no es válido o ya venció. Solicite uno nuevo.',
            ]);
    }

    public function test_an_expired_link_is_rejected(): void
    {
        $user = $this->usuario();
        $token = $this->tokenPara('docente@umss.edu.bo', 120);

        $this->postJson('/api/auth/contrasena/restablecer', [
            'usuario' => 'docente.prueba',
            'token' => $token,
            'contrasena_nueva' => 'Clave-Nueva-2',
            'contrasena_nueva_confirmation' => 'Clave-Nueva-2',
        ])->assertUnprocessable();

        $user->refresh();

        $this->assertTrue(Hash::check('Clave-inicial-1', $user->getAuthPassword()));
    }

    public function test_a_forged_token_is_rejected(): void
    {
        $this->usuario();
        $this->tokenPara('docente@umss.edu.bo');

        $this->postJson('/api/auth/contrasena/restablecer', [
            'usuario' => 'docente.prueba',
            'token' => 'token-inventado',
            'contrasena_nueva' => 'Clave-Nueva-2',
            'contrasena_nueva_confirmation' => 'Clave-Nueva-2',
        ])->assertUnprocessable();
    }

    public function test_the_reset_unlocks_an_account_blocked_by_failed_attempts(): void
    {
        $user = $this->usuario();
        $user->forceFill([
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(10),
        ])->save();

        $token = $this->tokenPara('docente@umss.edu.bo');

        $this->postJson('/api/auth/contrasena/restablecer', [
            'usuario' => 'docente.prueba',
            'token' => $token,
            'contrasena_nueva' => 'Clave-Nueva-2',
            'contrasena_nueva_confirmation' => 'Clave-Nueva-2',
        ])->assertOk();

        $user->refresh();

        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertNull($user->locked_until);
    }

    public function test_both_steps_are_recorded_in_the_audit_log(): void
    {
        Mail::fake();
        $this->usuario();

        $this->postJson('/api/auth/contrasena/olvide', [
            'usuario' => 'docente.prueba',
        ])->assertOk();

        $token = $this->tokenPara('docente@umss.edu.bo');

        $this->postJson('/api/auth/contrasena/restablecer', [
            'usuario' => 'docente.prueba',
            'token' => $token,
            'contrasena_nueva' => 'Clave-Nueva-2',
            'contrasena_nueva_confirmation' => 'Clave-Nueva-2',
        ])->assertOk();

        foreach ([
            'usuario.solicitar_restablecimiento',
            'usuario.restablecer_contrasena',
        ] as $operacion) {
            $this->assertTrue(
                DB::table('bitacora_operaciones')
                    ->where('operacion', $operacion)
                    ->exists(),
                "Falta la operacion {$operacion} en la bitacora."
            );
        }
    }

    private function usuario(): User
    {
        return User::create([
            'name' => 'Docente de prueba',
            'usuario' => 'docente.prueba',
            'email' => 'docente@umss.edu.bo',
            'password' => 'Clave-inicial-1',
        ]);
    }

    /**
     * Crea un enlace valido y devuelve el token en claro; la tabla solo
     * guarda su version cifrada.
     */
    private function tokenPara(string $correo, int $minutosAtras = 0): string
    {
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $correo],
            [
                'token' => Hash::make($token),
                'created_at' => now()->subMinutes($minutosAtras),
            ],
        );

        return $token;
    }
}
