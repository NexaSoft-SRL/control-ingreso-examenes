<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatosAcademicos;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * GET /api/auth/sesion: la fuente de verdad de la sesion del cliente.
 */
final class SesionTest extends TestCase
{
    use DatosAcademicos;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_a_guest_has_no_session(): void
    {
        $this->getJson('/api/auth/sesion')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'No hay una sesión activa.']);
    }

    public function test_the_session_describes_a_teacher_with_their_permissions(): void
    {
        $cuenta = $this->usuarioConRol('Docente', [
            'name' => 'Blanco Coca Leticia',
            'usuario' => 'leticia.blanco',
            'email' => null,
        ]);

        $docente = $this->docenteConCuenta($cuenta);

        $this->actingAs($cuenta)
            ->getJson('/api/auth/sesion')
            ->assertOk()
            ->assertExactJson([
                'user' => [
                    'id' => $cuenta->getKey(),
                    'nombre' => 'Blanco Coca Leticia',
                    'usuario' => 'leticia.blanco',
                    'correo' => null,
                    'rol' => 'Docente',
                    'permisos' => [
                        'mis_grupos',
                        'examenes',
                        'habilitacion',
                        'codigos_qr',
                        'punto_control',
                        'seguimiento_vivo',
                        'reportes_examenes',
                    ],
                    'debe_cambiar_contrasena' => false,
                    'docente_id' => $docente->getKey(),
                ],
            ]);
    }

    public function test_an_administrator_has_no_teacher_and_keeps_the_contact_email(): void
    {
        $cuenta = $this->usuarioConRol('Administrador', [
            'usuario' => 'administracion.academica',
            'email' => 'administracion@umss.edu.bo',
        ]);

        $this->actingAs($cuenta)
            ->getJson('/api/auth/sesion')
            ->assertOk()
            ->assertJsonPath('user.rol', 'Administrador')
            ->assertJsonPath('user.correo', 'administracion@umss.edu.bo')
            ->assertJsonPath('user.docente_id', null)
            ->assertJsonPath('user.permisos', [
                'periodo_oferta',
                'aulas_docentes',
                'padron_estudiantes',
                'reportes_universidad',
                'usuarios_roles',
                'bitacora',
                'respaldo_restauracion',
            ]);
    }

    public function test_an_assistant_only_gets_the_control_point_and_the_live_view(): void
    {
        $this->actingAs($this->usuarioConRol('Auxiliar'))
            ->getJson('/api/auth/sesion')
            ->assertOk()
            ->assertJsonPath('user.rol', 'Auxiliar')
            ->assertJsonPath('user.permisos', ['punto_control', 'seguimiento_vivo']);
    }

    public function test_the_session_is_the_same_object_the_login_returns(): void
    {
        $this->usuarioConRol('Docente', [
            'usuario' => 'leticia.blanco',
            'password' => 'Docente12345',
        ]);

        $acceso = $this->postJson('/api/auth/login', [
            'usuario' => 'leticia.blanco',
            'password' => 'Docente12345',
        ])->assertOk();

        $sesion = $this->getJson('/api/auth/sesion')->assertOk();

        $this->assertSame($acceso->json('user'), $sesion->json('user'));
    }

    public function test_the_session_is_available_with_a_temporary_password(): void
    {
        $cuenta = $this->usuarioConRol('Docente', [
            'password_changed_at' => null,
            'password_temporal_expira_en' => now()->addHours(72),
        ]);

        $this->actingAs($cuenta)
            ->getJson('/api/auth/sesion')
            ->assertOk()
            ->assertJsonPath('user.debe_cambiar_contrasena', true);
    }

    public function test_an_account_without_role_has_an_empty_permission_list(): void
    {
        $cuenta = $this->usuarioConPermisos([]);
        $cuenta->forceFill(['role_id' => null])->save();

        $this->actingAs($cuenta)
            ->getJson('/api/auth/sesion')
            ->assertOk()
            ->assertJsonPath('user.rol', null)
            ->assertJsonPath('user.permisos', []);
    }

    public function test_an_account_blocked_with_an_open_session_loses_it(): void
    {
        $cuenta = $this->usuarioConRol('Auxiliar');

        $this->actingAs($cuenta)
            ->getJson('/api/auth/sesion')
            ->assertOk();

        $cuenta->forceFill(['is_active' => false])->save();

        $this->getJson('/api/auth/sesion')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'No hay una sesión activa.']);

        $this->assertGuest();
    }

    public function test_reading_the_session_is_not_recorded_in_the_audit_log(): void
    {
        $this->actingAs($this->usuarioConRol('Docente'))
            ->getJson('/api/auth/sesion')
            ->assertOk();

        $this->assertSame(0, DB::table('bitacora_operaciones')->count());
    }

    public function test_the_session_ends_with_the_logout(): void
    {
        $this->actingAs($this->usuarioConRol('Docente'))
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        $this->getJson('/api/auth/sesion')->assertUnauthorized();
    }
}
