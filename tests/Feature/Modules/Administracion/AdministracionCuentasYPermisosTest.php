<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\Permission;
use App\Modules\Administracion\Domain\Models\Role;
use Database\Factories\UserFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HU-02: la pantalla solo podia listar y crear. Aqui quedan fijadas la
 * edicion de la cuenta, su activacion y los permisos modificables de cada
 * rol, que son los criterios del backlog.
 */
final class AdministracionCuentasYPermisosTest extends TestCase
{
    use RefreshDatabase;

    private function identificador(Model $modelo): int
    {
        $id = $modelo->getKey();

        self::assertIsInt($id);

        return $id;
    }

    public function test_the_user_list_carries_the_name_of_the_role(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $rol = Role::where('name', 'Docente')->firstOrFail();

        UserFactory::new()->createOne([
            'nombre' => 'Aaa Docente',
            'role_id' => $this->identificador($rol),
        ]);

        $administrador = UserFactory::new()->createOne(['nombre' => 'Zzz Administrador']);

        $this->actingAs($administrador)
            ->getJson('/api/auth/admin/users')
            ->assertOk()
            ->assertJsonPath('0.rol', 'Docente')
            ->assertJsonPath('1.rol', 'Administrador');
    }

    public function test_an_account_can_be_edited(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $administrador = UserFactory::new()->createOne();
        $cuenta = UserFactory::new()->createOne(['nombre' => 'Nombre viejo']);

        $this->actingAs($administrador)
            ->putJson("/api/auth/admin/users/{$this->identificador($cuenta)}", [
                'nombre' => 'Nombre nuevo',
                'correo' => 'nombre.nuevo@umss.edu.bo',
                'rol' => 'Responsable',
            ])
            ->assertOk();

        $this->assertDatabaseHas('usuarios', [
            'id' => $cuenta->getKey(),
            'nombre' => 'Nombre nuevo',
            'correo' => 'nombre.nuevo@umss.edu.bo',
        ]);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'usuario.actualizar',
            'registro_id' => $cuenta->getKey(),
        ]);
    }

    public function test_the_email_of_another_account_cannot_be_reused(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $administrador = UserFactory::new()->createOne();
        $ocupado = UserFactory::new()->createOne(['correo' => 'ocupado@umss.edu.bo']);
        $cuenta = UserFactory::new()->createOne();

        $this->actingAs($administrador)
            ->putJson("/api/auth/admin/users/{$this->identificador($cuenta)}", [
                'nombre' => 'Quien sea',
                'correo' => $ocupado->correo,
                'rol' => 'Docente',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('correo');
    }

    public function test_an_account_is_deactivated_instead_of_deleted(): void
    {
        $administrador = UserFactory::new()->createOne();
        $cuenta = UserFactory::new()->createOne(['is_active' => true]);

        $this->actingAs($administrador)
            ->patchJson("/api/auth/admin/users/{$this->identificador($cuenta)}/estado", [
                'is_active' => false,
            ])
            ->assertOk();

        $this->assertDatabaseHas('usuarios', [
            'id' => $cuenta->getKey(),
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'usuario.desactivar',
            'registro_id' => $cuenta->getKey(),
        ]);
    }

    public function test_the_administrator_cannot_lock_itself_out(): void
    {
        $administrador = UserFactory::new()->createOne(['is_active' => true]);

        $this->actingAs($administrador)
            ->patchJson("/api/auth/admin/users/{$this->identificador($administrador)}/estado", [
                'is_active' => false,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('usuarios', [
            'id' => $administrador->getKey(),
            'is_active' => true,
        ]);
    }

    public function test_the_permissions_of_a_role_can_be_modified(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $administrador = UserFactory::new()->createOne();
        $rol = Role::where('name', 'Docente')->firstOrFail();

        /** @var list<int> $permisos */
        $permisos = Permission::whereIn('name', ['reportes_asignatura', 'habilitacion'])
            ->pluck('id')
            ->all();

        $this->actingAs($administrador)
            ->putJson("/api/auth/admin/roles/{$this->identificador($rol)}/permisos", [
                'permisos' => $permisos,
            ])
            ->assertOk()
            ->assertJsonCount(2, 'role.permissions');

        foreach ($permisos as $permiso) {
            $this->assertDatabaseHas('permission_role', [
                'role_id' => $rol->getKey(),
                'permission_id' => $permiso,
            ]);
        }

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'rol.permisos',
            'registro_id' => $rol->getKey(),
        ]);
    }

    public function test_a_role_can_be_left_without_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $administrador = UserFactory::new()->createOne();
        $rol = Role::where('name', 'Administrador')->firstOrFail();

        $this->actingAs($administrador)
            ->putJson("/api/auth/admin/roles/{$this->identificador($rol)}/permisos", ['permisos' => []])
            ->assertOk()
            ->assertJsonCount(0, 'role.permissions');

        $this->assertDatabaseMissing('permission_role', [
            'role_id' => $rol->getKey(),
        ]);
    }

    public function test_the_permission_catalogue_is_available(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $administrador = UserFactory::new()->createOne();

        $this->actingAs($administrador)
            ->getJson('/api/auth/admin/permissions')
            ->assertOk()
            ->assertJsonCount(12);
    }

    public function test_all_of_it_requires_a_session(): void
    {
        $cuenta = UserFactory::new()->createOne();

        $this->putJson("/api/auth/admin/users/{$this->identificador($cuenta)}", [])->assertUnauthorized();
        $this->patchJson("/api/auth/admin/users/{$this->identificador($cuenta)}/estado", [])
            ->assertUnauthorized();
        $this->getJson('/api/auth/admin/permissions')->assertUnauthorized();
        $this->putJson('/api/auth/admin/roles/1/permisos', [])->assertUnauthorized();
    }
}
