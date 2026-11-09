<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\Permission;
use App\Modules\Administracion\Domain\Models\Role;
use App\Modules\Administracion\Domain\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PermisosVigentesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_role_without_the_permission_gets_an_explicit_refusal(): void
    {
        $docente = $this->usuarioConRol('Docente');

        $this->actingAs($docente)
            ->getJson('/api/bitacora')
            ->assertForbidden()
            ->assertJson([
                'message' => 'Este rol no tiene permiso para esta sección.',
                'permiso_requerido' => 'bitacora',
            ]);
    }

    public function test_a_user_without_any_role_is_also_refused(): void
    {
        $sinRol = $this->usuario('sinrol@umss.edu.bo');

        $this->actingAs($sinRol)
            ->getJson('/api/bitacora')
            ->assertForbidden();
    }

    public function test_a_guest_is_rejected_before_the_permission_check(): void
    {
        $this->getJson('/api/bitacora')->assertUnauthorized();
    }

    public function test_a_permission_that_has_not_started_yet_does_not_grant_access(): void
    {
        $usuario = $this->usuarioConRol('Docente');
        $this->asignarPermiso('Docente', 'bitacora', now()->addDay()->toDateString(), null);

        $this->actingAs($usuario)
            ->getJson('/api/bitacora')
            ->assertForbidden();
    }

    public function test_an_expired_permission_does_not_grant_access(): void
    {
        $usuario = $this->usuarioConRol('Docente');
        $this->asignarPermiso('Docente', 'bitacora', null, now()->subDay()->toDateString());

        $this->actingAs($usuario)
            ->getJson('/api/bitacora')
            ->assertForbidden();
    }

    public function test_a_permission_valid_today_grants_access(): void
    {
        $usuario = $this->usuarioConRol('Docente');
        $this->asignarPermiso(
            'Docente',
            'bitacora',
            now()->subDay()->toDateString(),
            now()->addDay()->toDateString(),
        );

        $this->actingAs($usuario)
            ->getJson('/api/bitacora')
            ->assertOk();
    }

    public function test_a_permission_without_dates_is_always_valid(): void
    {
        $usuario = $this->usuarioConRol('Docente');
        $this->asignarPermiso('Docente', 'bitacora', null, null);

        $this->actingAs($usuario)
            ->getJson('/api/bitacora')
            ->assertOk();
    }

    public function test_the_session_only_lists_the_permissions_valid_today(): void
    {
        $usuario = $this->usuarioConRol('Auxiliar');
        $this->asignarPermiso('Auxiliar', 'bitacora', now()->addDay()->toDateString(), null);
        $this->asignarPermiso('Auxiliar', 'seguimiento_vivo', null, now()->subDay()->toDateString());

        $this->actingAs($usuario)
            ->getJson('/api/auth/sesion')
            ->assertOk()
            ->assertJsonPath('user.permisos', ['punto_control']);
    }

    private function asignarPermiso(
        string $rol,
        string $permiso,
        ?string $desde,
        ?string $hasta,
    ): void {
        DB::table('permission_role')->updateOrInsert(
            [
                'role_id' => Role::where('name', $rol)->value('id'),
                'permission_id' => Permission::where('name', $permiso)->value('id'),
            ],
            [
                'vigente_desde' => $desde,
                'vigente_hasta' => $hasta,
            ],
        );
    }

    private function usuarioConRol(string $rol): User
    {
        $usuario = $this->usuario(mb_strtolower($rol).'@umss.edu.bo');

        $usuario->forceFill([
            'role_id' => Role::where('name', $rol)->value('id'),
        ])->save();

        return $usuario;
    }

    private function usuario(string $correo): User
    {
        $usuario = User::create([
            'name' => 'Usuario de prueba',
            'usuario' => strstr($correo, '@', true),
            'email' => $correo,
            'password' => 'Clave-inicial-1',
        ]);

        $usuario->forceFill(['password_changed_at' => now()])->save();

        return $usuario;
    }
}
