<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\Permission;
use App\Modules\Administracion\Domain\Models\Role;
use Database\Factories\UserFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La administracion de usuarios y roles (HU-02) expone la lista completa de
 * cuentas: estas pruebas fijan que no vuelva a quedar accesible sin sesion.
 */
final class AdministracionUsuariosRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_list_the_users(): void
    {
        $this->getJson('/api/auth/admin/users')->assertUnauthorized();
    }

    public function test_a_guest_cannot_list_the_roles(): void
    {
        $this->getJson('/api/auth/admin/roles')->assertUnauthorized();
    }

    public function test_a_guest_cannot_create_users(): void
    {
        $this->postJson('/api/auth/admin/users', [
            'nombre' => 'Intruso',
            'correo' => 'intruso@umss.edu.bo',
        ])->assertUnauthorized();
    }

    public function test_an_authenticated_user_lists_users_and_roles(): void
    {
        $usuario = UserFactory::new()->createOne();

        $this->actingAs($usuario)->getJson('/api/auth/admin/users')->assertOk();
        $this->actingAs($usuario)->getJson('/api/auth/admin/roles')->assertOk();
    }

    public function test_the_seeder_loads_the_roles_and_permissions_of_the_backlog(): void
    {
        $this->seed(RolePermissionSeeder::class);

        foreach (['Administrador', 'Docente', 'Personal', 'Responsable'] as $nombre) {
            $this->assertTrue(
                Role::where('name', $nombre)->exists(),
                "Falta el rol {$nombre}."
            );
        }

        $this->assertGreaterThan(0, Permission::count());
    }
}
