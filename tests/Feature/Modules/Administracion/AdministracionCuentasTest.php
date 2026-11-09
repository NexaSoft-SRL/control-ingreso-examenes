<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * Ruta 10: la pantalla de usuarios corrige los datos de una cuenta, le
 * cambia el rol y le quita el acceso sin borrarla.
 */
final class AdministracionCuentasTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    private User $administrador;

    private User $cuenta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrador = $this->usuarioConRol('Administrador', [
            'usuario' => 'ana.rojas',
            'email' => 'ocupado@umss.edu.bo',
        ]);

        $this->cuenta = $this->usuarioConRol('Docente', [
            'name' => 'Nombre viejo',
            'usuario' => 'nombre.viejo',
            'email' => 'propio@umss.edu.bo',
        ]);
    }

    private function ruta(?User $cuenta = null): string
    {
        $id = ($cuenta ?? $this->cuenta)->getKey();

        self::assertIsInt($id);

        return '/api/usuarios/'.$id;
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function cuerpo(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Nombre viejo',
            'usuario' => 'nombre.viejo',
            'correo' => 'propio@umss.edu.bo',
            'rol' => 'Docente',
            'activo' => true,
        ], $cambios);
    }

    private function asientos(string $operacion): int
    {
        return DB::table('bitacora_operaciones')
            ->where('operacion', $operacion)
            ->where('registro_id', $this->cuenta->getKey())
            ->count();
    }

    public function test_an_account_can_be_edited(): void
    {
        $this->actingAs($this->administrador)
            ->putJson($this->ruta(), $this->cuerpo([
                'nombre' => 'Nombre nuevo',
                'usuario' => 'Nombre.Nuevo',
                'correo' => 'nombre.nuevo@umss.edu.bo',
            ]))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $this->cuenta->getKey(),
                    'nombre' => 'Nombre nuevo',
                    'usuario' => 'nombre.nuevo',
                    'correo' => 'nombre.nuevo@umss.edu.bo',
                    'rol' => 'Docente',
                    'estado' => 'activo',
                ],
                'message' => 'Cambios guardados.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->cuenta->getKey(),
            'name' => 'Nombre nuevo',
            'usuario' => 'nombre.nuevo',
            'email' => 'nombre.nuevo@umss.edu.bo',
        ]);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'usuario.actualizar',
            'usuario_id' => $this->administrador->getKey(),
            'tabla_afectada' => 'users',
            'registro_id' => $this->cuenta->getKey(),
        ]);
    }

    public function test_the_role_and_the_email_can_be_changed(): void
    {
        $this->actingAs($this->administrador)
            ->putJson($this->ruta(), $this->cuerpo(['rol' => 'Auxiliar', 'correo' => null]))
            ->assertOk()
            ->assertJsonPath('data.rol', 'Auxiliar')
            ->assertJsonPath('data.correo', null);

        $this->assertDatabaseHas('users', [
            'id' => $this->cuenta->getKey(),
            'email' => null,
            'role_id' => DB::table('roles')->where('name', 'Auxiliar')->value('id'),
        ]);
    }

    public function test_an_account_can_be_moved_to_a_created_role(): void
    {
        $this->usuarioConPermisos(['periodo_oferta']);

        $this->actingAs($this->administrador)
            ->putJson($this->ruta(), $this->cuerpo(['rol' => 'Rol de prueba']))
            ->assertOk()
            ->assertJsonPath('data.rol', 'Rol de prueba');

        $this->assertDatabaseHas('users', [
            'id' => $this->cuenta->getKey(),
            'role_id' => DB::table('roles')->where('name', 'Rol de prueba')->value('id'),
        ]);
    }

    public function test_editing_an_account_keeps_its_own_username_and_email(): void
    {
        $this->actingAs($this->administrador)
            ->putJson($this->ruta(), $this->cuerpo(['nombre' => 'Nombre corregido']))
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Nombre corregido');
    }

    public function test_saving_without_changes_writes_nothing_to_the_audit_log(): void
    {
        $this->actingAs($this->administrador)
            ->putJson($this->ruta(), $this->cuerpo())
            ->assertOk()
            ->assertJsonPath('message', 'Cambios guardados.');

        $this->assertSame(0, $this->asientos('usuario.actualizar'));
        $this->assertSame(0, $this->asientos('usuario.bloquear'));
    }

    public function test_the_username_of_another_account_cannot_be_reused(): void
    {
        $this->actingAs($this->administrador)
            ->putJson($this->ruta(), $this->cuerpo(['usuario' => 'ana.rojas']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.usuario.0', 'Ya existe');
    }

    public function test_the_email_of_another_account_cannot_be_reused(): void
    {
        $this->actingAs($this->administrador)
            ->putJson($this->ruta(), $this->cuerpo(['correo' => 'ocupado@umss.edu.bo']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.correo.0', 'Ya existe');

        $this->assertDatabaseHas('users', [
            'id' => $this->cuenta->getKey(),
            'email' => 'propio@umss.edu.bo',
        ]);
    }

    public function test_the_fields_are_validated(): void
    {
        $this->actingAs($this->administrador)
            ->putJson($this->ruta(), [
                'nombre' => '',
                'usuario' => 'con/barra',
                'correo' => 'mal',
                'rol' => 'Otro',
                'activo' => 'quizas',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre', 'usuario', 'correo', 'rol', 'activo']);
    }

    public function test_an_account_is_blocked_instead_of_deleted(): void
    {
        $this->actingAs($this->administrador)
            ->putJson($this->ruta(), $this->cuerpo(['activo' => false]))
            ->assertOk()
            ->assertJsonPath('message', 'Cuenta bloqueada.')
            ->assertJsonPath('data.estado', 'bloqueado');

        $this->assertDatabaseHas('users', [
            'id' => $this->cuenta->getKey(),
            'is_active' => false,
        ]);

        $this->assertSame(1, $this->asientos('usuario.bloquear'));
        // Solo cambio el estado: no hay asiento de edicion.
        $this->assertSame(0, $this->asientos('usuario.actualizar'));
    }

    public function test_a_blocked_account_cannot_sign_in(): void
    {
        $this->actingAs($this->administrador)
            ->putJson($this->ruta(), $this->cuerpo(['activo' => false]))
            ->assertOk();

        $this->app->make('auth')->forgetGuards();

        $this->postJson('/api/auth/login', [
            'usuario' => 'nombre.viejo',
            'password' => 'password',
        ])->assertStatus(423);
    }

    public function test_a_blocked_account_can_be_unblocked(): void
    {
        $this->cuenta->forceFill(['is_active' => false])->save();

        $this->actingAs($this->administrador)
            ->putJson($this->ruta(), $this->cuerpo(['activo' => true]))
            ->assertOk()
            ->assertJsonPath('message', 'Cuenta desbloqueada.')
            ->assertJsonPath('data.estado', 'activo');

        $this->assertDatabaseHas('users', [
            'id' => $this->cuenta->getKey(),
            'is_active' => true,
        ]);

        $this->assertSame(1, $this->asientos('usuario.desbloquear'));
    }

    public function test_the_state_is_left_alone_when_it_does_not_travel(): void
    {
        $this->cuenta->forceFill(['is_active' => false])->save();

        $cuerpo = $this->cuerpo(['nombre' => 'Nombre nuevo']);
        unset($cuerpo['activo']);

        $this->actingAs($this->administrador)
            ->putJson($this->ruta(), $cuerpo)
            ->assertOk()
            ->assertJsonPath('message', 'Cambios guardados.')
            ->assertJsonPath('data.estado', 'bloqueado');
    }

    public function test_the_administrator_cannot_lock_itself_out(): void
    {
        $this->actingAs($this->administrador)
            ->putJson($this->ruta($this->administrador), [
                'nombre' => 'Otro nombre',
                'usuario' => 'ana.rojas',
                'correo' => 'ocupado@umss.edu.bo',
                'rol' => 'Administrador',
                'activo' => false,
            ])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'No se puede bloquear la propia cuenta.']);

        // Tampoco se guardo el resto de la edicion.
        $this->assertDatabaseHas('users', [
            'id' => $this->administrador->getKey(),
            'name' => $this->administrador->name,
            'is_active' => true,
        ]);

        $this->assertDatabaseCount('bitacora_operaciones', 0);
    }

    public function test_the_administrator_cannot_change_its_own_role(): void
    {
        $this->actingAs($this->administrador)
            ->putJson($this->ruta($this->administrador), [
                'nombre' => $this->administrador->name,
                'usuario' => $this->administrador->usuario,
                'rol' => 'Docente',
            ])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'No se puede cambiar el rol de la propia cuenta.']);

        $this->assertDatabaseCount('bitacora_operaciones', 0);
    }

    public function test_an_account_that_does_not_exist_is_reported(): void
    {
        $this->actingAs($this->administrador)
            ->putJson('/api/usuarios/999999', $this->cuerpo([
                'usuario' => 'fantasma',
                'correo' => 'fantasma@umss.edu.bo',
            ]))
            ->assertNotFound()
            ->assertExactJson(['message' => 'Usuario no encontrado.']);
    }

    public function test_the_permission_is_required(): void
    {
        $this->actingAs($this->usuarioConPermisos(['bitacora']))
            ->putJson($this->ruta(), $this->cuerpo(['nombre' => 'Nombre nuevo']))
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'usuarios_roles');

        $this->assertDatabaseHas('users', [
            'id' => $this->cuenta->getKey(),
            'name' => 'Nombre viejo',
        ]);
    }

    public function test_a_guest_cannot_touch_the_accounts(): void
    {
        $this->putJson($this->ruta(), $this->cuerpo(['activo' => false]))
            ->assertUnauthorized();

        $this->assertDatabaseHas('users', [
            'id' => $this->cuenta->getKey(),
            'is_active' => true,
        ]);
    }
}
