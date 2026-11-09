<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * Ruta 12: la matriz de permisos por rol, de solo lectura.
 */
final class MatrizPermisosTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_the_matrix_lists_the_three_roles_and_thirteen_screens(): void
    {
        $respuesta = $this->actingAs($this->usuarioConRol('Administrador'))
            ->getJson('/api/permisos/matriz')
            ->assertOk()
            ->assertJsonPath('roles', ['Administrador', 'Docente', 'Auxiliar'])
            ->assertJsonCount(13, 'pantallas')
            ->assertJsonPath('pantallas.0', [
                'pantalla' => 'Período y oferta académica',
                'roles' => ['Administrador'],
            ])
            ->assertJsonPath('pantallas.6', [
                'pantalla' => 'Punto de control',
                'roles' => ['Docente', 'Auxiliar'],
            ])
            ->assertJsonPath('pantallas.12', [
                'pantalla' => 'Respaldo',
                'roles' => ['Administrador'],
            ]);

        // En el orden de la matriz, que es el del catalogo.
        $this->assertSame(
            array_values(array_unique(array_map(
                static fn (string $pantalla): string => str_replace(' (sus grupos)', '', $pantalla),
                array_values(RolePermissionSeeder::PERMISOS),
            ))),
            array_column((array) $respuesta->json('pantallas'), 'pantalla'),
        );
    }

    public function test_the_two_roster_permissions_come_out_as_a_single_row(): void
    {
        $this->actingAs($this->usuarioConRol('Administrador'))
            ->getJson('/api/permisos/matriz')
            ->assertOk()
            ->assertJsonPath('pantallas.2', [
                'pantalla' => 'Padrón e inscripciones',
                'roles' => ['Administrador', 'Docente'],
            ])
            ->assertJsonPath('pantallas.3.pantalla', 'Exámenes');
    }

    public function test_a_permission_that_is_no_longer_in_force_is_not_shown(): void
    {
        $administrador = $this->usuarioConRol('Administrador');

        DB::table('permission_role')
            ->where('role_id', DB::table('roles')->where('name', 'Auxiliar')->value('id'))
            ->where('permission_id', DB::table('permissions')->where('name', 'seguimiento_vivo')->value('id'))
            ->update(['vigente_hasta' => now()->subDay()->toDateString()]);

        $this->actingAs($administrador)
            ->getJson('/api/permisos/matriz')
            ->assertOk()
            ->assertJsonPath('pantallas.7', [
                'pantalla' => 'Seguimiento en vivo',
                'roles' => ['Docente'],
            ]);
    }

    /**
     * Esta vista es de lectura; los roles se editan por `/api/roles`.
     */
    public function test_the_fixed_matrix_is_read_only(): void
    {
        $rutas = collect(Route::getRoutes()->getRoutes())
            ->filter(static fn ($ruta): bool => str_starts_with($ruta->uri(), 'api/permisos'))
            ->values();

        $this->assertCount(1, $rutas);
        $this->assertSame(['GET', 'HEAD'], $rutas[0]?->methods());
    }

    public function test_a_guest_cannot_see_the_matrix(): void
    {
        $this->getJson('/api/permisos/matriz')->assertUnauthorized();
    }

    public function test_the_permission_is_required(): void
    {
        $this->actingAs($this->usuarioConRol('Docente'))
            ->getJson('/api/permisos/matriz')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'usuarios_roles');
    }
}
