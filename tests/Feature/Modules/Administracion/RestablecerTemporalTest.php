<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Administracion\Infrastructure\Mail\CredencialesInicialesMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * HU-15, criterio 9: la administracion emite una contrasena temporal nueva para una
 * cuenta que ya existe.
 */
final class RestablecerTemporalTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->administrador = $this->usuarioConRol('Administrador');
    }

    private function ruta(User $cuenta): string
    {
        $id = $cuenta->getKey();

        self::assertIsInt($id);

        return '/api/usuarios/'.$id.'/contrasena-temporal';
    }

    public function test_a_new_temporary_password_is_issued_and_returned_once(): void
    {
        $this->travelTo('2026-10-12 08:21:00');

        $cuenta = $this->usuarioConRol('Docente', ['correo' => null]);

        $respuesta = $this->actingAs($this->administrador)
            ->postJson($this->ruta($cuenta))
            ->assertOk()
            ->assertJsonStructure(['contrasena_temporal', 'enviada_a', 'caduca_en'])
            ->assertJsonPath('enviada_a', null)
            ->assertJsonPath('caduca_en', '2026-10-15T08:21:00-04:00');

        $temporal = $respuesta->json('contrasena_temporal');

        self::assertIsString($temporal);

        $cuenta->refresh();

        $this->assertTrue(Hash::check($temporal, $cuenta->password));
        // Queda otra vez como temporal.
        $this->assertNull($cuenta->getAttribute('password_changed_at'));

        Mail::assertNothingSent();
    }

    public function test_the_temporary_password_is_also_emailed_when_there_is_an_email(): void
    {
        $cuenta = $this->usuarioConRol('Docente', ['correo' => 'x@umss.edu.bo']);

        $this->actingAs($this->administrador)
            ->postJson($this->ruta($cuenta))
            ->assertOk()
            ->assertJsonPath('enviada_a', 'x@umss.edu.bo');

        Mail::assertSent(
            CredencialesInicialesMail::class,
            fn (CredencialesInicialesMail $correo): bool => $correo->hasTo('x@umss.edu.bo')
        );
    }

    public function test_it_lifts_the_lock_for_failed_attempts(): void
    {
        $cuenta = $this->usuarioConRol('Docente');

        $cuenta->forceFill([
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(15),
        ])->save();

        $this->actingAs($this->administrador)
            ->postJson($this->ruta($cuenta))
            ->assertOk();

        $this->assertDatabaseHas('usuarios', [
            'id' => $cuenta->getKey(),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    public function test_it_is_recorded_in_the_audit_log_without_the_password(): void
    {
        $cuenta = $this->usuarioConRol('Docente');

        $temporal = $this->actingAs($this->administrador)
            ->postJson($this->ruta($cuenta))
            ->assertOk()
            ->json('contrasena_temporal');

        self::assertIsString($temporal);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'usuario.restablecer_temporal',
            'usuario_id' => $this->administrador->getKey(),
            'tabla_afectada' => 'usuarios',
            'registro_id' => $cuenta->getKey(),
        ]);

        foreach (DB::table('bitacora_operaciones')->pluck('descripcion') as $descripcion) {
            self::assertIsString($descripcion);
            $this->assertStringNotContainsString($temporal, $descripcion);
        }
    }

    public function test_an_account_that_does_not_exist_is_reported(): void
    {
        $this->actingAs($this->administrador)
            ->postJson('/api/usuarios/999999/contrasena-temporal')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Usuario no encontrado.']);

        $this->assertDatabaseCount('bitacora_operaciones', 0);
    }

    public function test_a_guest_cannot_reset_a_password(): void
    {
        $cuenta = $this->usuarioConRol('Docente');
        $anterior = $cuenta->password;

        $this->postJson($this->ruta($cuenta))->assertUnauthorized();

        $this->assertSame($anterior, $cuenta->refresh()->password);
    }

    public function test_the_permission_is_required(): void
    {
        $cuenta = $this->usuarioConRol('Docente');
        $anterior = $cuenta->password;

        $this->actingAs($this->usuarioConRol('Auxiliar'))
            ->postJson($this->ruta($cuenta))
            ->assertForbidden();

        $this->assertSame($anterior, $cuenta->refresh()->password);
    }
}
