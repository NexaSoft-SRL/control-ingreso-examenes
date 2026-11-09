<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\Permission;
use App\Modules\Administracion\Domain\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * Los roles de inicio son tres, y cada pantalla tiene su permiso. El
 * reparto es el de la matriz de la especificacion (3.1).
 */
final class RolesDelSistemaTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    private const CATALOGO = [
        'periodo_oferta',
        'aulas_docentes',
        'padron_estudiantes',
        'mis_grupos',
        'examenes',
        'habilitacion',
        'codigos_qr',
        'punto_control',
        'seguimiento_vivo',
        'reportes_universidad',
        'reportes_examenes',
        'usuarios_roles',
        'bitacora',
        'respaldo_restauracion',
    ];

    public function test_the_seeder_creates_exactly_the_three_system_roles(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(
            ['Administrador', 'Auxiliar', 'Docente'],
            Role::orderBy('name')->pluck('name')->all()
        );

        $this->assertSame(3, Role::where('es_sistema', true)->count());
    }

    public function test_the_permission_catalog_has_the_fourteen_screens_in_matrix_order(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(
            self::CATALOGO,
            Permission::orderBy('id')->pluck('name')->all()
        );

        $this->assertSame(
            'Período y oferta académica',
            Permission::where('name', 'periodo_oferta')->value('screen_name')
        );
    }

    /**
     * @param  list<string>  $esperados
     */
    #[DataProvider('repartoPorRol')]
    public function test_each_role_has_exactly_its_permissions(string $rol, array $esperados): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(
            $esperados,
            Role::where('name', $rol)
                ->firstOrFail()
                ->permissions()
                ->orderBy('permissions.id')
                ->pluck('name')
                ->all()
        );
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function repartoPorRol(): array
    {
        return [
            'administrador' => ['Administrador', [
                'periodo_oferta',
                'aulas_docentes',
                'padron_estudiantes',
                'reportes_universidad',
                'usuarios_roles',
                'bitacora',
                'respaldo_restauracion',
            ]],
            'docente' => ['Docente', [
                'mis_grupos',
                'examenes',
                'habilitacion',
                'codigos_qr',
                'punto_control',
                'seguimiento_vivo',
                'reportes_examenes',
            ]],
            'auxiliar' => ['Auxiliar', [
                'punto_control',
                'seguimiento_vivo',
            ]],
        ];
    }

    public function test_every_permission_belongs_to_at_least_one_role(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(
            0,
            Permission::whereDoesntHave('roles')->count()
        );
    }

    public function test_the_seeder_can_run_twice_without_duplicating(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(3, Role::count());
        $this->assertSame(14, Permission::count());
        $this->assertSame(16, DB::table('permission_role')->count());
    }

    public function test_the_administrator_does_not_reach_the_teacher_screens(): void
    {
        $administrador = $this->usuarioConRol('Administrador');

        $this->actingAs($administrador)
            ->getJson('/api/bitacora')
            ->assertOk();

        $this->actingAs($administrador)
            ->getJson('/api/auth/sesion')
            ->assertOk()
            ->assertJsonPath('user.rol', 'Administrador')
            ->assertJsonMissing(['examenes'])
            ->assertJsonMissing(['punto_control']);
    }

    public function test_teacher_and_assistant_are_refused_on_an_administration_screen(): void
    {
        foreach (['Docente', 'Auxiliar'] as $rol) {
            $this->actingAs($this->usuarioConRol($rol))
                ->getJson('/api/bitacora')
                ->assertForbidden()
                ->assertExactJson([
                    'message' => 'Este rol no tiene permiso para esta sección.',
                    'permiso_requerido' => 'bitacora',
                ]);
        }
    }

    public function test_the_unprotected_role_endpoints_no_longer_exist(): void
    {
        $this->getJson('/api/auth/admin/users')->assertNotFound();
        $this->getJson('/api/auth/admin/roles')->assertNotFound();
        // Los roles se gestionan en `/api/roles`, con sesion y permiso.
        $this->getJson('/api/roles')->assertUnauthorized();
    }
}
