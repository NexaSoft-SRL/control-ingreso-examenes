<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * Tercer criterio de HU-02: "un usuario sin permiso recibe una negativa
 * explicita, no una pantalla en blanco". Cada ruta declara el permiso de su
 * pantalla con el middleware `permiso`.
 */
final class PermisosPorRolTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    private function identificador(Model $modelo): int
    {
        $id = $modelo->getKey();

        self::assertIsInt($id);

        return $id;
    }

    public function test_the_administrator_reaches_the_log_and_the_accounts(): void
    {
        $administrador = $this->usuarioConRol('Administrador');

        $this->actingAs($administrador)->getJson('/api/bitacora')->assertOk();
        $this->actingAs($administrador)->getJson('/api/usuarios')->assertOk();
    }

    public function test_a_teacher_is_refused_the_log_with_a_reason(): void
    {
        $docente = $this->usuarioConRol('Docente');

        $this->actingAs($docente)
            ->getJson('/api/bitacora')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'bitacora')
            ->assertJsonPath('rol', 'Docente')
            ->assertJsonFragment([
                'message' => 'Tu rol (Docente) no tiene acceso a esta sección. Pide al administrador que le habilite el permiso.',
            ]);
    }

    public function test_a_teacher_and_an_assistant_are_refused_the_accounts(): void
    {
        foreach (['Docente', 'Auxiliar'] as $rol) {
            $this->actingAs($this->usuarioConRol($rol))
                ->getJson('/api/usuarios')
                ->assertForbidden()
                ->assertJsonPath('permiso_requerido', 'usuarios_roles')
                ->assertJsonPath('rol', $rol);
        }
    }

    public function test_an_account_without_a_role_is_told_so(): void
    {
        $huerfano = $this->usuarioConRol('Docente');
        $huerfano->forceFill(['role_id' => null])->save();

        $this->actingAs($huerfano->refresh())
            ->getJson('/api/bitacora')
            ->assertForbidden()
            ->assertJsonPath('rol', null)
            ->assertJsonFragment([
                'message' => 'Tu rol (sin rol asignado) no tiene acceso a esta sección. Pide al administrador que le habilite el permiso.',
            ]);
    }

    public function test_the_permission_granted_from_the_roles_screen_opens_the_door(): void
    {
        $administrador = $this->usuarioConRol('Administrador');
        $docente = $this->usuarioConRol('Docente');
        $rolDocente = Role::where('name', 'Docente')->firstOrFail();

        $this->actingAs($docente)->getJson('/api/bitacora')->assertForbidden();

        $this->actingAs($administrador)
            ->putJson("/api/roles/{$this->identificador($rolDocente)}", [
                'permisos' => ['examenes', 'habilitacion', 'reportes_examenes', 'bitacora'],
            ])
            ->assertOk();

        // El modelo en memoria conserva los permisos que leyo antes; en un
        // servidor cada peticion lo lee de nuevo.
        $recargado = $docente->fresh();

        self::assertNotNull($recargado);

        $this->actingAs($recargado)->getJson('/api/bitacora')->assertOk();
    }

    public function test_a_created_role_only_reaches_the_screens_it_was_given(): void
    {
        $coordinador = $this->usuarioConPermisos(['bitacora']);

        $this->actingAs($coordinador)->getJson('/api/bitacora')->assertOk();

        $this->actingAs($coordinador)
            ->getJson('/api/usuarios')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'usuarios_roles')
            ->assertJsonPath('rol', 'Rol de prueba');
    }

    public function test_one_of_several_permissions_is_enough_when_the_route_lists_them(): void
    {
        Route::middleware(['web', 'auth', 'permiso:periodo_oferta|padron_estudiantes'])
            ->get('/api/_prueba/alternativas', static fn (): array => ['ok' => true]);

        $this->actingAs($this->usuarioConPermisos(['padron_estudiantes']))
            ->getJson('/api/_prueba/alternativas')
            ->assertOk();

        $sinNinguno = UserFactory::new()->createOne([
            'role_id' => $this->identificador(Role::create(['name' => 'Sin pantallas'])),
        ]);

        $this->actingAs($sinNinguno)
            ->getJson('/api/_prueba/alternativas')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'periodo_oferta|padron_estudiantes');
    }

    public function test_without_a_session_the_answer_is_unauthorized_and_in_spanish(): void
    {
        $this->getJson('/api/bitacora')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'No hay una sesión activa.']);

        $this->getJson('/api/usuarios')->assertUnauthorized();
    }

    public function test_an_unknown_api_route_answers_json_in_spanish(): void
    {
        $this->actingAs(UserFactory::new()->createOne())
            ->get('/api/no-existe')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Recurso no encontrado.']);
    }
}
