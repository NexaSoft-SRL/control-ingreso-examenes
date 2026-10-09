<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * HU-15, criterios 10 a 15: la matriz de permisos, los roles que crea la
 * administracion, el cambio de sus permisos y su baja.
 */
final class RolesTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrador = $this->usuarioConRol('Administrador');
    }

    private function rolId(string $nombre): int
    {
        $id = Role::where('name', $nombre)->value('id');

        self::assertIsInt($id);

        return $id;
    }

    /**
     * El modelo en memoria conserva los permisos que leyo antes; en un
     * servidor cada peticion lo lee de nuevo.
     */
    private function recargada(User $cuenta): User
    {
        return User::query()->whereKey($cuenta->getKey())->firstOrFail();
    }

    /**
     * @param  list<string>  $permisos
     */
    private function crear(string $nombre, array $permisos): int
    {
        $id = $this->actingAs($this->administrador)
            ->postJson('/api/roles', ['nombre' => $nombre, 'permisos' => $permisos])
            ->assertCreated()
            ->json('data.id');

        self::assertIsInt($id);

        return $id;
    }

    public function test_the_matrix_lists_the_roles_with_their_accounts_and_the_catalogue(): void
    {
        $this->usuarioConRol('Docente');
        $this->usuarioConRol('Docente');
        $coordinador = $this->crear('Coordinador', ['aulas_docentes', 'periodo_oferta']);

        $respuesta = $this->actingAs($this->administrador)
            ->getJson('/api/roles')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonCount(14, 'meta.permisos')
            ->assertJsonPath('meta.permisos.0', [
                'clave' => 'periodo_oferta',
                'pantalla' => 'Período y oferta académica',
            ])
            ->assertJsonPath('meta.permisos.11', [
                'clave' => 'usuarios_roles',
                'pantalla' => 'Usuarios y roles',
            ])
            ->assertJsonPath('data.1', [
                'id' => $this->rolId('Docente'),
                'nombre' => 'Docente',
                'es_sistema' => true,
                'cuentas' => 2,
                'permisos' => RolePermissionSeeder::POR_ROL['Docente'],
            ])
            // Los creados van despues de los tres de inicio; sus permisos,
            // en el orden del catalogo.
            ->assertJsonPath('data.3', [
                'id' => $coordinador,
                'nombre' => 'Coordinador',
                'es_sistema' => false,
                'cuentas' => 0,
                'permisos' => ['periodo_oferta', 'aulas_docentes'],
            ]);

        $this->assertSame(
            ['Administrador', 'Docente', 'Auxiliar', 'Coordinador'],
            $respuesta->json('data.*.nombre'),
        );
        $this->assertSame(1, $respuesta->json('data.0.cuentas'));
        $this->assertSame(
            array_keys(RolePermissionSeeder::PERMISOS),
            $respuesta->json('meta.permisos.*.clave'),
        );
    }

    public function test_a_role_is_created_with_its_permissions(): void
    {
        $respuesta = $this->actingAs($this->administrador)
            ->postJson('/api/roles', [
                'nombre' => '  Coordinador   de carrera ',
                'permisos' => ['bitacora', 'periodo_oferta'],
            ])
            ->assertCreated();

        $id = $respuesta->json('data.id');

        self::assertIsInt($id);

        $respuesta->assertExactJson([
            'data' => [
                'id' => $id,
                'nombre' => 'Coordinador de carrera',
                'es_sistema' => false,
                'cuentas' => 0,
                'permisos' => ['periodo_oferta', 'bitacora'],
            ],
            'message' => 'Rol creado.',
        ]);

        $this->assertDatabaseHas('roles', [
            'id' => $id,
            'name' => 'Coordinador de carrera',
            'es_sistema' => false,
        ]);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'rol.crear',
            'usuario_id' => $this->administrador->getKey(),
            'tabla_afectada' => 'roles',
            'registro_id' => $id,
            'descripcion' => 'Rol Coordinador de carrera creado con 2 permisos.',
        ]);

        // El rol nuevo ya sirve para dar de alta una cuenta y para filtrar.
        $this->actingAs($this->administrador)
            ->postJson('/api/usuarios', [
                'nombre' => 'Vargas Ana',
                'usuario' => 'ana.vargas',
                'rol' => 'Coordinador de carrera',
            ])
            ->assertCreated();

        $this->actingAs($this->administrador)
            ->getJson('/api/usuarios?rol='.urlencode('Coordinador de carrera'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.conteos.Coordinador de carrera', 1);
    }

    public function test_a_role_needs_a_name(): void
    {
        $this->actingAs($this->administrador)
            ->postJson('/api/roles', ['permisos' => ['bitacora']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.nombre.0', 'Obligatorio');

        $this->actingAs($this->administrador)
            ->postJson('/api/roles', ['nombre' => 'Ab', 'permisos' => ['bitacora']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.nombre.0', 'Mínimo 3 caracteres');

        $this->actingAs($this->administrador)
            ->postJson('/api/roles', ['nombre' => str_repeat('a', 41), 'permisos' => ['bitacora']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.nombre.0', 'Máximo 40 caracteres');

        $this->assertDatabaseCount('roles', 3);
    }

    public function test_a_repeated_name_is_rejected_whatever_its_case(): void
    {
        $this->crear('Coordinador', ['bitacora']);

        foreach (['coordinador', 'COORDINADOR', 'docente', 'Bloqueadas'] as $nombre) {
            $this->actingAs($this->administrador)
                ->postJson('/api/roles', ['nombre' => $nombre, 'permisos' => ['bitacora']])
                ->assertUnprocessable()
                ->assertJsonPath('errors.nombre.0', 'Ya existe');
        }

        $this->assertDatabaseCount('roles', 4);
    }

    public function test_a_role_without_permissions_is_not_created(): void
    {
        foreach ([['nombre' => 'Vacio'], ['nombre' => 'Vacio', 'permisos' => []]] as $cuerpo) {
            $this->actingAs($this->administrador)
                ->postJson('/api/roles', $cuerpo)
                ->assertUnprocessable()
                ->assertJsonPath('errors.permisos.0', 'Al menos un permiso');
        }

        $this->actingAs($this->administrador)
            ->postJson('/api/roles', ['nombre' => 'Vacio', 'permisos' => ['no_existe']])
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['permisos.0' => ['El permiso no existe']]);

        $this->assertDatabaseMissing('roles', ['name' => 'Vacio']);
        $this->assertDatabaseCount('bitacora_operaciones', 0);
    }

    public function test_changing_the_permissions_takes_effect_on_the_next_request(): void
    {
        $id = $this->crear('Coordinador', ['periodo_oferta']);

        $cuenta = $this->usuarioConRol('Docente');
        $cuenta->forceFill(['role_id' => $id])->save();

        $this->actingAs($cuenta->refresh())->getJson('/api/bitacora')->assertForbidden();

        $this->actingAs($this->administrador)
            ->putJson("/api/roles/{$id}", ['permisos' => ['bitacora', 'periodo_oferta']])
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $id,
                    'nombre' => 'Coordinador',
                    'es_sistema' => false,
                    'cuentas' => 1,
                    'permisos' => ['periodo_oferta', 'bitacora'],
                ],
                'message' => 'Cambios guardados.',
            ]);

        $this->actingAs($this->recargada($cuenta))->getJson('/api/bitacora')->assertOk();

        $this->actingAs($this->administrador)
            ->putJson("/api/roles/{$id}", ['permisos' => ['periodo_oferta']])
            ->assertOk();

        $this->actingAs($this->recargada($cuenta))
            ->getJson('/api/bitacora')
            ->assertForbidden();

        $this->assertSame(2, DB::table('bitacora_operaciones')
            ->where('operacion', 'rol.modificar')
            ->where('tabla_afectada', 'roles')
            ->where('registro_id', $id)
            ->where('usuario_id', $this->administrador->getKey())
            ->count());

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'rol.modificar',
            'descripcion' => 'El rol Coordinador quedó con 2 permisos.',
        ]);
    }

    public function test_a_created_role_can_be_renamed(): void
    {
        $id = $this->crear('Coordinador', ['periodo_oferta']);
        $this->crear('Jefatura', ['bitacora']);

        $this->actingAs($this->administrador)
            ->putJson("/api/roles/{$id}", ['nombre' => 'jefatura', 'permisos' => ['periodo_oferta']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.nombre.0', 'Ya existe');

        // Su propio nombre no cuenta como repetido.
        $this->actingAs($this->administrador)
            ->putJson("/api/roles/{$id}", ['nombre' => 'COORDINADOR', 'permisos' => ['periodo_oferta']])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'COORDINADOR');

        $this->actingAs($this->administrador)
            ->putJson("/api/roles/{$id}", ['nombre' => 'Coordinación', 'permisos' => ['periodo_oferta']])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Coordinación');

        $this->assertDatabaseHas('roles', ['id' => $id, 'name' => 'Coordinación']);
    }

    public function test_saving_a_role_without_changes_writes_nothing_to_the_audit_log(): void
    {
        $id = $this->crear('Coordinador', ['periodo_oferta']);

        $this->actingAs($this->administrador)
            ->putJson("/api/roles/{$id}", ['nombre' => 'Coordinador', 'permisos' => ['periodo_oferta']])
            ->assertOk();

        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'rol.modificar']);
    }

    public function test_the_permissions_of_a_starting_role_can_be_changed_but_not_its_name(): void
    {
        $auxiliar = $this->rolId('Auxiliar');

        $this->actingAs($this->administrador)
            ->putJson("/api/roles/{$auxiliar}", ['nombre' => 'Auxiliar', 'permisos' => ['punto_control']])
            ->assertOk()
            ->assertJsonPath('data.es_sistema', true)
            ->assertJsonPath('data.permisos', ['punto_control']);

        $this->actingAs($this->administrador)
            ->putJson("/api/roles/{$auxiliar}", ['nombre' => 'Portería', 'permisos' => ['bitacora']])
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'El nombre de un rol de inicio no cambia.',
                'errors' => ['nombre' => ['El nombre de un rol de inicio no cambia.']],
            ]);

        // Tampoco se guardaron los permisos de esa peticion.
        $this->actingAs($this->administrador)
            ->getJson('/api/roles')
            ->assertJsonPath('data.2.nombre', 'Auxiliar')
            ->assertJsonPath('data.2.permisos', ['punto_control']);
    }

    public function test_a_role_cannot_be_left_without_permissions(): void
    {
        $id = $this->crear('Coordinador', ['periodo_oferta']);

        $this->actingAs($this->administrador)
            ->putJson("/api/roles/{$id}", ['permisos' => []])
            ->assertUnprocessable()
            ->assertJsonPath('errors.permisos.0', 'Al menos un permiso');
    }

    public function test_nobody_removes_the_users_and_roles_permission_from_its_own_role(): void
    {
        $administrador = $this->rolId('Administrador');

        $this->actingAs($this->administrador)
            ->putJson("/api/roles/{$administrador}", ['permisos' => ['bitacora', 'periodo_oferta']])
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'No puedes quitar «Usuarios y roles» a tu propio rol.',
                'errors' => ['permisos' => ['No puedes quitar «Usuarios y roles» a tu propio rol.']],
            ]);

        $this->assertSame(14, DB::table('permission_role')->where('role_id', $administrador)->count());
        $this->assertDatabaseCount('bitacora_operaciones', 0);

        // Conservandolo, si puede recortar su propio rol.
        $this->actingAs($this->administrador)
            ->putJson("/api/roles/{$administrador}", ['permisos' => ['usuarios_roles', 'bitacora']])
            ->assertOk()
            ->assertJsonPath('data.permisos', ['usuarios_roles', 'bitacora']);

        // Y quitarselo a un rol ajeno.
        $otro = $this->crear('Segundo', ['usuarios_roles']);

        $this->actingAs($this->recargada($this->administrador))
            ->putJson("/api/roles/{$otro}", ['permisos' => ['bitacora']])
            ->assertOk();
    }

    public function test_a_starting_role_is_not_deleted(): void
    {
        foreach (['Administrador', 'Docente', 'Auxiliar'] as $nombre) {
            $this->actingAs($this->administrador)
                ->deleteJson('/api/roles/'.$this->rolId($nombre))
                ->assertConflict()
                ->assertExactJson([
                    'message' => 'Los roles de inicio no se eliminan.',
                    'codigo' => 'ROL_DE_INICIO',
                ]);
        }

        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('bitacora_operaciones', 0);
    }

    public function test_a_role_with_accounts_is_not_deleted(): void
    {
        $id = $this->crear('Coordinador', ['periodo_oferta']);

        $primera = $this->usuarioConRol('Docente');
        $primera->forceFill(['role_id' => $id])->save();

        $this->actingAs($this->administrador)
            ->deleteJson("/api/roles/{$id}")
            ->assertConflict()
            ->assertExactJson([
                'message' => 'El rol tiene 1 cuenta asignada.',
                'codigo' => 'ROL_CON_CUENTAS',
            ]);

        // Una cuenta bloqueada tambien lo usa.
        $segunda = $this->usuarioConRol('Docente', ['is_active' => false]);
        $segunda->forceFill(['role_id' => $id])->save();

        $this->actingAs($this->administrador)
            ->deleteJson("/api/roles/{$id}")
            ->assertConflict()
            ->assertJsonPath('message', 'El rol tiene 2 cuentas asignadas.');

        $this->assertDatabaseHas('roles', ['id' => $id]);
        $this->assertDatabaseHas('usuarios', ['id' => $primera->getKey(), 'role_id' => $id]);
        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'rol.eliminar']);
    }

    public function test_a_created_role_without_accounts_is_deleted(): void
    {
        $id = $this->crear('Coordinador', ['periodo_oferta', 'bitacora']);

        $this->actingAs($this->administrador)
            ->deleteJson("/api/roles/{$id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('roles', ['id' => $id]);
        $this->assertDatabaseMissing('permission_role', ['role_id' => $id]);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'rol.eliminar',
            'usuario_id' => $this->administrador->getKey(),
            'tabla_afectada' => 'roles',
            'registro_id' => $id,
            'descripcion' => 'Rol Coordinador eliminado.',
        ]);

        $this->actingAs($this->administrador)
            ->getJson('/api/roles')
            ->assertJsonCount(3, 'data');
    }

    public function test_a_role_that_does_not_exist_is_reported(): void
    {
        $this->actingAs($this->administrador)
            ->putJson('/api/roles/999999', ['permisos' => ['bitacora']])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Rol no encontrado.']);

        $this->actingAs($this->administrador)
            ->deleteJson('/api/roles/999999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Rol no encontrado.']);
    }

    public function test_the_audit_log_of_accounts_and_roles_never_holds_a_password(): void
    {
        $respuesta = $this->actingAs($this->administrador)
            ->postJson('/api/usuarios', [
                'nombre' => 'Vargas Ana',
                'usuario' => 'ana.vargas',
                'rol' => 'Docente',
            ])
            ->assertCreated();

        $temporal = $respuesta->json('contrasena_temporal');
        $cuenta = $respuesta->json('data.id');

        self::assertIsString($temporal);
        self::assertIsInt($cuenta);

        $id = $this->crear('Coordinador', ['periodo_oferta']);

        $this->actingAs($this->administrador)
            ->putJson("/api/usuarios/{$cuenta}", [
                'nombre' => 'Vargas Ana',
                'usuario' => 'ana.vargas',
                'rol' => 'Coordinador',
                'activo' => false,
            ])
            ->assertOk();

        $this->actingAs($this->administrador)
            ->putJson("/api/roles/{$id}", ['permisos' => ['bitacora']])
            ->assertOk();

        $asientos = DB::table('bitacora_operaciones')->orderBy('id')->get();

        $this->assertSame(
            ['usuario.registrar', 'rol.crear', 'usuario.actualizar', 'usuario.bloquear', 'rol.modificar'],
            $asientos->pluck('operacion')->all(),
        );

        foreach ($asientos as $asiento) {
            $this->assertSame($this->administrador->getKey(), $asiento->usuario_id);
            self::assertIsString($asiento->descripcion);
            $this->assertStringNotContainsString($temporal, $asiento->descripcion);
            $this->assertStringNotContainsStringIgnoringCase('contrase', $asiento->descripcion);
        }
    }

    public function test_the_roles_require_the_session(): void
    {
        $this->getJson('/api/roles')->assertUnauthorized();
        $this->postJson('/api/roles', ['nombre' => 'Coordinador', 'permisos' => ['bitacora']])
            ->assertUnauthorized();
        $this->putJson('/api/roles/'.$this->rolId('Auxiliar'), ['permisos' => ['bitacora']])
            ->assertUnauthorized();
        $this->deleteJson('/api/roles/'.$this->rolId('Auxiliar'))->assertUnauthorized();

        $this->assertDatabaseCount('roles', 3);
    }

    public function test_the_roles_require_the_permission(): void
    {
        $docente = $this->usuarioConRol('Docente');
        $auxiliar = $this->rolId('Auxiliar');

        $this->actingAs($docente)
            ->getJson('/api/roles')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'usuarios_roles')
            ->assertJsonPath('rol', 'Docente');

        $this->actingAs($docente)
            ->postJson('/api/roles', ['nombre' => 'Coordinador', 'permisos' => ['bitacora']])
            ->assertForbidden();

        $this->actingAs($docente)
            ->putJson("/api/roles/{$auxiliar}", ['permisos' => ['usuarios_roles']])
            ->assertForbidden();

        $this->actingAs($docente)
            ->deleteJson("/api/roles/{$auxiliar}")
            ->assertForbidden();

        $this->assertDatabaseCount('roles', 3);
        $this->assertSame(2, DB::table('permission_role')->where('role_id', $auxiliar)->count());
    }
}
