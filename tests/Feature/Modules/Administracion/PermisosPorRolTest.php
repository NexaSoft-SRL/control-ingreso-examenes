<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\Permission;
use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\User;
use Database\Factories\UserFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tercer criterio de HU-02: "un usuario sin permiso recibe una negativa
 * explicita, no una pantalla en blanco". Hasta ahora cualquier sesion valida
 * entraba a cualquier ruta.
 */
final class PermisosPorRolTest extends TestCase
{
    use RefreshDatabase;

    private function identificador(Model $modelo): int
    {
        $id = $modelo->getKey();

        self::assertIsInt($id);

        return $id;
    }

    private function cuentaCon(string $rol): User
    {
        $this->seed(RolePermissionSeeder::class);

        $encontrado = Role::where('name', $rol)->firstOrFail();

        return UserFactory::new()->createOne([
            'role_id' => $this->identificador($encontrado),
        ]);
    }

    public function test_the_administrator_reaches_every_screen_of_the_sprint(): void
    {
        $administrador = $this->cuentaCon('Administrador');

        $this->actingAs($administrador)->getJson('/api/students')->assertOk();
        $this->actingAs($administrador)->getJson('/api/admin/ambientes')->assertOk();
        $this->actingAs($administrador)->getJson('/api/asignaturas')->assertOk();
        $this->actingAs($administrador)->getJson('/api/bitacora')->assertOk();
        $this->actingAs($administrador)->getJson('/api/auth/admin/users')->assertOk();
    }

    public function test_a_teacher_is_refused_the_student_roll_with_a_reason(): void
    {
        $docente = $this->cuentaCon('Docente');

        $this->actingAs($docente)
            ->getJson('/api/students')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'padron_estudiantes')
            ->assertJsonPath('rol', 'Docente')
            ->assertJsonFragment([
                'message' => 'Tu rol (Docente) no tiene acceso a esta sección. Pide al administrador que le habilite el permiso.',
            ]);
    }

    public function test_a_teacher_is_refused_the_log_and_the_accounts(): void
    {
        $docente = $this->cuentaCon('Docente');

        $this->actingAs($docente)->getJson('/api/bitacora')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'bitacora');

        $this->actingAs($docente)->getJson('/api/auth/admin/users')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'usuarios_roles');
    }

    public function test_the_control_staff_is_refused_the_rooms(): void
    {
        $personal = $this->cuentaCon('Personal');

        $this->actingAs($personal)->postJson('/api/admin/ambientes', [
            'nombre' => 'Aula sin permiso',
            'capacidad' => 30,
        ])->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'asignaturas_ambientes');

        $this->assertDatabaseMissing('ambientes', ['nombre' => 'Aula sin permiso']);
    }

    public function test_an_account_without_a_role_is_told_so(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $huerfano = UserFactory::new()->createOne(['role_id' => null]);

        $this->actingAs($huerfano)
            ->getJson('/api/students')
            ->assertForbidden()
            ->assertJsonPath('rol', null)
            ->assertJsonFragment([
                'message' => 'Tu rol (sin rol asignado) no tiene acceso a esta sección. Pide al administrador que le habilite el permiso.',
            ]);
    }

    public function test_the_permission_granted_from_the_roles_screen_opens_the_door(): void
    {
        $administrador = $this->cuentaCon('Administrador');
        $rolDocente = Role::where('name', 'Docente')->firstOrFail();
        $docente = UserFactory::new()->createOne([
            'role_id' => $this->identificador($rolDocente),
        ]);

        $this->actingAs($docente)->getJson('/api/bitacora')->assertForbidden();

        /** @var list<int> $permisos */
        $permisos = Permission::whereIn(
            'name',
            ['examenes_normas', 'habilitacion', 'reportes_asignatura', 'bitacora'],
        )->pluck('id')->all();

        $this->actingAs($administrador)
            ->putJson("/api/auth/admin/roles/{$this->identificador($rolDocente)}/permisos", [
                'permisos' => $permisos,
            ])
            ->assertOk();

        // El modelo en memoria conserva los permisos que leyo antes; en un
        // servidor cada peticion lo lee de nuevo.
        $recargado = $docente->fresh();

        self::assertNotNull($recargado);

        $this->actingAs($recargado)->getJson('/api/bitacora')->assertOk();
    }

    public function test_without_a_session_the_answer_is_still_unauthorized(): void
    {
        $this->getJson('/api/students')->assertUnauthorized();
        $this->getJson('/api/bitacora')->assertUnauthorized();
    }

    public function test_the_teacher_registration_only_needs_the_accounts_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $rol = Role::where('name', 'Personal')->firstOrFail();

        /** @var list<int> $permisos */
        $permisos = Permission::where(
            'name',
            'usuarios_roles',
        )->pluck('id')->all();

        $rol->permissions()->sync($permisos);

        $soloCuentas = UserFactory::new()->createOne([
            'role_id' => $this->identificador($rol),
        ]);

        // Sin el permiso de asignaturas, el alta del docente igual pasa.
        $this->actingAs($soloCuentas)
            ->postJson('/api/docentes', [
                'codigo_docente' => 'DOC-950',
                'nombres' => 'Marcela',
                'apellidos' => 'Quiroga',
            ])
            ->assertCreated();

        $this->actingAs($soloCuentas)
            ->getJson('/api/docentes')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'asignaturas_ambientes');
    }
}
