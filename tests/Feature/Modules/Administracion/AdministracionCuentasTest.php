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
 * HU-15, criterios 5 a 8: la pantalla de usuarios crea una cuenta, corrige
 * sus datos, le cambia el rol y le quita el acceso sin borrarla.
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

        Mail::fake();

        $this->administrador = $this->usuarioConRol('Administrador', [
            'usuario' => 'ana.rojas',
            'correo' => 'ocupado@umss.edu.bo',
        ]);

        $this->cuenta = $this->usuarioConRol('Docente', [
            'nombre' => 'Nombre viejo',
            'usuario' => 'nombre.viejo',
            'correo' => 'propio@umss.edu.bo',
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

    public function test_an_account_is_created_with_a_temporary_password_shown_once(): void
    {
        $this->travelTo('2026-10-12 08:21:00');

        $respuesta = $this->actingAs($this->administrador)
            ->postJson('/api/usuarios', [
                'nombre' => '  Blanco Coca Leticia ',
                'usuario' => 'Leticia.Blanco',
                'correo' => 'Leticia.Blanco@umss.edu.bo',
                'rol' => 'Docente',
            ])
            ->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'nombre', 'usuario', 'correo', 'rol', 'estado'],
                'contrasena_temporal',
                'enviada_a',
                'caduca_en',
                'message',
            ])
            ->assertJsonPath('data.nombre', 'Blanco Coca Leticia')
            ->assertJsonPath('data.usuario', 'leticia.blanco')
            ->assertJsonPath('data.correo', 'leticia.blanco@umss.edu.bo')
            ->assertJsonPath('data.rol', 'Docente')
            ->assertJsonPath('data.estado', 'activo')
            ->assertJsonPath('enviada_a', 'leticia.blanco@umss.edu.bo')
            ->assertJsonPath('caduca_en', '2026-10-15T08:21:00-04:00')
            ->assertJsonPath('message', 'Usuario creado.');

        $temporal = $respuesta->json('contrasena_temporal');
        $id = $respuesta->json('data.id');

        self::assertIsString($temporal);
        self::assertIsInt($id);

        $creada = User::findOrFail($id);

        $this->assertTrue(Hash::check($temporal, $creada->password));
        $this->assertNull($creada->getAttribute('password_changed_at'));

        Mail::assertSent(
            CredencialesInicialesMail::class,
            fn (CredencialesInicialesMail $correo): bool => $correo->hasTo('leticia.blanco@umss.edu.bo')
        );

        // La cuenta nueva es la primera del listado.
        $this->actingAs($this->administrador)
            ->getJson('/api/usuarios')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        // Queda asentada con su autor y sin la contrasena.
        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'usuario.registrar',
            'usuario_id' => $this->administrador->getKey(),
            'tabla_afectada' => 'usuarios',
            'registro_id' => $id,
        ]);

        foreach (DB::table('bitacora_operaciones')->pluck('descripcion') as $descripcion) {
            self::assertIsString($descripcion);
            $this->assertStringNotContainsString($temporal, $descripcion);
        }
    }

    public function test_an_account_can_be_created_without_email_and_with_a_created_role(): void
    {
        $this->usuarioConPermisos(['periodo_oferta']);

        $this->actingAs($this->administrador)
            ->postJson('/api/usuarios', [
                'nombre' => 'Vargas Ana',
                'usuario' => 'ana.vargas',
                'correo' => '',
                'rol' => 'Rol de prueba',
            ])
            ->assertCreated()
            ->assertJsonPath('data.correo', null)
            ->assertJsonPath('data.rol', 'Rol de prueba')
            ->assertJsonPath('enviada_a', null);

        Mail::assertNothingSent();
    }

    public function test_the_fields_of_a_new_account_are_marked_with_short_messages(): void
    {
        $this->actingAs($this->administrador)
            ->postJson('/api/usuarios', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.nombre.0', 'Obligatorio')
            ->assertJsonPath('errors.usuario.0', 'Obligatorio')
            ->assertJsonPath('errors.rol.0', 'Obligatorio');

        $this->actingAs($this->administrador)
            ->postJson('/api/usuarios', [
                'nombre' => 'Al',
                'usuario' => 'con espacios',
                'correo' => 'mal',
                'rol' => 'Responsable',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.nombre.0', 'Mínimo 3 caracteres')
            ->assertJsonPath('errors.usuario.0', 'Solo letras, números, puntos y guiones')
            ->assertJsonPath('errors.correo.0', 'Correo no válido')
            ->assertJsonPath('errors.rol.0', 'El rol no existe');

        $this->actingAs($this->administrador)
            ->postJson('/api/usuarios', [
                'nombre' => 'Otra persona',
                'usuario' => 'ANA.ROJAS',
                'correo' => 'Ocupado@umss.edu.bo',
                'rol' => 'Docente',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.usuario.0', 'Ya existe')
            ->assertJsonPath('errors.correo.0', 'Ya existe');

        $this->assertDatabaseCount('usuarios', 2);
        $this->assertDatabaseCount('bitacora_operaciones', 0);
    }

    public function test_creating_an_account_requires_the_session_and_the_permission(): void
    {
        $cuerpo = ['nombre' => 'Vargas Ana', 'usuario' => 'ana.vargas', 'rol' => 'Docente'];

        $this->postJson('/api/usuarios', $cuerpo)->assertUnauthorized();

        $this->actingAs($this->cuenta)
            ->postJson('/api/usuarios', $cuerpo)
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'usuarios_roles');

        $this->assertDatabaseMissing('usuarios', ['usuario' => 'ana.vargas']);
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

        $this->assertDatabaseHas('usuarios', [
            'id' => $this->cuenta->getKey(),
            'nombre' => 'Nombre nuevo',
            'usuario' => 'nombre.nuevo',
            'correo' => 'nombre.nuevo@umss.edu.bo',
        ]);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'usuario.actualizar',
            'usuario_id' => $this->administrador->getKey(),
            'tabla_afectada' => 'usuarios',
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

        $this->assertDatabaseHas('usuarios', [
            'id' => $this->cuenta->getKey(),
            'correo' => null,
            'role_id' => DB::table('roles')->where('name', 'Auxiliar')->value('id'),
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

        $this->assertDatabaseHas('usuarios', [
            'id' => $this->cuenta->getKey(),
            'correo' => 'propio@umss.edu.bo',
        ]);
    }

    public function test_the_fields_are_validated(): void
    {
        $this->actingAs($this->administrador)
            ->putJson($this->ruta(), [
                'nombre' => '',
                'usuario' => 'con espacios',
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

        $this->assertDatabaseHas('usuarios', [
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

        // El acceso es por correo y no dice por que rechaza.
        $this->postJson('/api/auth/login', [
            'email' => 'propio@umss.edu.bo',
            'password' => 'password',
        ])->assertUnauthorized();

        $this->assertGuest();
    }

    public function test_a_blocked_account_can_be_unblocked(): void
    {
        $this->cuenta->forceFill(['is_active' => false])->save();

        $this->actingAs($this->administrador)
            ->putJson($this->ruta(), $this->cuerpo(['activo' => true]))
            ->assertOk()
            ->assertJsonPath('message', 'Cuenta desbloqueada.')
            ->assertJsonPath('data.estado', 'activo');

        $this->assertDatabaseHas('usuarios', [
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
            ->assertExactJson(['message' => 'No puedes bloquear tu propia cuenta.']);

        // Tampoco se guardo el resto de la edicion.
        $this->assertDatabaseHas('usuarios', [
            'id' => $this->administrador->getKey(),
            'nombre' => $this->administrador->nombre,
            'is_active' => true,
        ]);

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

        $this->assertDatabaseHas('usuarios', [
            'id' => $this->cuenta->getKey(),
            'nombre' => 'Nombre viejo',
        ]);
    }

    public function test_a_guest_cannot_touch_the_accounts(): void
    {
        $this->putJson($this->ruta(), $this->cuerpo(['activo' => false]))
            ->assertUnauthorized();

        $this->assertDatabaseHas('usuarios', [
            'id' => $this->cuenta->getKey(),
            'is_active' => true,
        ]);
    }
}
