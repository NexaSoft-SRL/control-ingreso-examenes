<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class CambioContrasenaTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_the_user_changes_their_own_password(): void
    {
        $user = $this->usuarioConContrasenaPropia();

        $this->actingAs($user)
            ->postJson('/api/auth/contrasena', [
                'contrasena_actual' => 'Clave-Actual-1',
                'contrasena_nueva' => 'Clave-Propia-2',
                'contrasena_nueva_confirmation' => 'Clave-Propia-2',
            ])
            ->assertOk()
            ->assertExactJson(['message' => 'Contraseña actualizada.']);

        $user->refresh();

        $this->assertTrue(Hash::check('Clave-Propia-2', $user->getAuthPassword()));
        $this->assertNotNull($user->password_changed_at);
    }

    public function test_the_change_is_rejected_when_the_current_password_is_wrong(): void
    {
        $user = $this->usuarioConContrasenaPropia();

        $this->actingAs($user)
            ->postJson('/api/auth/contrasena', [
                'contrasena_actual' => 'no-es-la-actual',
                'contrasena_nueva' => 'Clave-Propia-2',
                'contrasena_nueva_confirmation' => 'Clave-Propia-2',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La contraseña actual no es correcta.')
            ->assertJsonValidationErrors('contrasena_actual');

        $this->assertTrue(
            Hash::check('Clave-Actual-1', $user->refresh()->getAuthPassword())
        );
    }

    public function test_the_current_password_is_required_once_the_user_has_their_own(): void
    {
        $user = $this->usuarioConContrasenaPropia();

        $this->actingAs($user)
            ->postJson('/api/auth/contrasena', [
                'contrasena_nueva' => 'Clave-Propia-2',
                'contrasena_nueva_confirmation' => 'Clave-Propia-2',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contrasena_actual');

        $this->assertTrue(
            Hash::check('Clave-Actual-1', $user->refresh()->getAuthPassword())
        );
    }

    public function test_the_new_password_must_be_confirmed_and_different(): void
    {
        $user = $this->usuarioConContrasenaPropia();

        $this->actingAs($user)
            ->postJson('/api/auth/contrasena', [
                'contrasena_actual' => 'Clave-Actual-1',
                'contrasena_nueva' => 'Clave-Actual-1',
                'contrasena_nueva_confirmation' => 'otra-distinta',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contrasena_nueva');
    }

    #[DataProvider('contrasenasDebiles')]
    public function test_the_new_password_needs_ten_characters_mixed_case_and_a_number(
        string $contrasena,
        string $mensaje,
    ): void {
        $user = $this->usuarioConContrasenaPropia();

        $respuesta = $this->actingAs($user)
            ->postJson('/api/auth/contrasena', [
                'contrasena_actual' => 'Clave-Actual-1',
                'contrasena_nueva' => $contrasena,
                'contrasena_nueva_confirmation' => $contrasena,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contrasena_nueva');

        $this->assertContains($mensaje, (array) $respuesta->json('errors.contrasena_nueva'));

        $this->assertTrue(
            Hash::check('Clave-Actual-1', $user->refresh()->getAuthPassword())
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function contrasenasDebiles(): array
    {
        return [
            'nueve caracteres' => [
                'Clave-12a',
                'La contraseña nueva debe tener al menos 10 caracteres.',
            ],
            'sin mayuscula' => [
                'clave-propia-2',
                'La contraseña nueva debe tener una mayúscula y una minúscula.',
            ],
            'sin minuscula' => [
                'CLAVE-PROPIA-2',
                'La contraseña nueva debe tener una mayúscula y una minúscula.',
            ],
            'sin numero' => [
                'Clave-Propia-x',
                'La contraseña nueva debe tener un número.',
            ],
        ];
    }

    public function test_a_guest_cannot_change_a_password(): void
    {
        $this->postJson('/api/auth/contrasena', [
            'contrasena_actual' => 'Clave-Actual-1',
            'contrasena_nueva' => 'Clave-Propia-2',
            'contrasena_nueva_confirmation' => 'Clave-Propia-2',
        ])->assertUnauthorized();
    }

    public function test_the_login_response_reports_the_pending_change(): void
    {
        $this->usuarioConContrasenaTemporal();

        $this->postJson('/api/auth/login', [
            'usuario' => 'docente.prueba',
            'password' => 'Fa7-Kmq4-Ru9',
        ])
            ->assertOk()
            ->assertJsonPath('user.debe_cambiar_contrasena', true);
    }

    public function test_the_rest_of_the_api_is_blocked_until_the_password_is_changed(): void
    {
        $user = $this->usuarioConContrasenaTemporal();

        // Cualquier ruta distinta de las de sesion y contrasena queda
        // bloqueada.
        $this->actingAs($user)
            ->getJson('/api/bitacora')
            ->assertStatus(409)
            ->assertExactJson([
                'message' => 'Defina su propia contraseña antes de continuar.',
                'debe_cambiar_contrasena' => true,
            ]);

        $this->actingAs($user)
            ->getJson('/api/auth/sesion')
            ->assertOk()
            ->assertJsonPath('user.debe_cambiar_contrasena', true);

        $this->actingAs($user)
            ->postJson('/api/auth/contrasena', [
                'contrasena_nueva' => 'Clave-Propia-2',
                'contrasena_nueva_confirmation' => 'Clave-Propia-2',
            ])
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/bitacora')
            ->assertOk();
    }

    public function test_the_change_is_recorded_in_the_audit_log(): void
    {
        $user = $this->usuarioConContrasenaPropia();

        $this->actingAs($user)
            ->postJson('/api/auth/contrasena', [
                'contrasena_actual' => 'Clave-Actual-1',
                'contrasena_nueva' => 'Clave-Propia-2',
                'contrasena_nueva_confirmation' => 'Clave-Propia-2',
            ])
            ->assertOk();

        $this->assertTrue(
            DB::table('bitacora_operaciones')
                ->where('operacion', 'usuario.cambiar_contrasena')
                ->where('usuario_id', $user->getKey())
                ->exists()
        );

        $this->assertStringNotContainsString(
            'Clave-Propia-2',
            DB::table('bitacora_operaciones')->pluck('descripcion')->implode(' ')
        );
    }

    /**
     * Cuenta que ya definio su contrasena: para cambiarla se le pide la
     * actual.
     */
    private function usuarioConContrasenaPropia(): User
    {
        $usuario = $this->usuarioConPermisos(['bitacora']);

        $usuario->forceFill([
            'name' => 'Docente de prueba',
            'usuario' => 'docente.prueba',
            'password' => 'Clave-Actual-1',
            'password_changed_at' => now()->subDay(),
        ])->save();

        return $usuario;
    }

    /**
     * Cuenta recien creada: sigue con la contrasena temporal que se le
     * entrego, de ahi que password_changed_at sea nulo.
     */
    private function usuarioConContrasenaTemporal(): User
    {
        $usuario = $this->usuarioConPermisos(['bitacora']);

        $usuario->forceFill([
            'name' => 'Docente de prueba',
            'usuario' => 'docente.prueba',
            'password' => 'Fa7-Kmq4-Ru9',
            'password_changed_at' => null,
            'password_temporal_expira_en' => now()->addHours(72),
        ])->save();

        return $usuario;
    }
}
