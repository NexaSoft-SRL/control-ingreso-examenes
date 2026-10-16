<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\IncidenciasIngresoGateway;
use App\Modules\Ingreso\Application\Contracts\IngresosRecientesGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class PuntoControlHttpTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    private const BASE = '/api/examenes/1/aulas/1/punto-control';

    public function test_visitante_no_puede_consultar_incidencias(): void
    {
        $this->getJson(self::BASE.'/incidencias')
            ->assertUnauthorized();
    }

    public function test_usuario_sin_permiso_no_consulta_incidencias(): void
    {
        $usuario = $this->usuarioConPermisos([]);

        $this->actingAs($usuario)
            ->getJson(self::BASE.'/incidencias')
            ->assertForbidden();
    }

    public function test_permiso_general_no_revela_incidencias(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);

        $this->actingAs($usuario)
            ->getJson(self::BASE.'/incidencias')
            ->assertForbidden();
    }

    public function test_operador_autorizado_obtiene_incidencias_http(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);

        $alcance = $this->createMock(AlcancePuntoControl::class);
        $alcance->method('puedeOperar')->willReturn(true);

        $incidencias = $this->createMock(IncidenciasIngresoGateway::class);
        $incidencias->expects(self::once())
            ->method('recientes')
            ->with(1, 1, 20)
            ->willReturn([
                [
                    'incidencia_id' => 42,
                    'estudiante_id' => 15,
                    'codigo' => '20260015',
                    'nombres' => 'Ana',
                    'apellidos' => 'Rojas',
                    'aula_id' => 1,
                    'tipo_rechazo' => 'NO_HABILITADO',
                    'detalle' => 'No cumple el requisito.',
                    'registrado_en' => '2026-10-16 08:15:00',
                    'operador' => 'Personal de control',
                ],
            ]);

        $this->app->instance(AlcancePuntoControl::class, $alcance);
        $this->app->instance(IncidenciasIngresoGateway::class, $incidencias);

        $this->actingAs($usuario)
            ->getJson(self::BASE.'/incidencias')
            ->assertOk()
            ->assertJsonPath('meta.cantidad', 1)
            ->assertJsonPath('data.0.estudiante_id', 15)
            ->assertJsonPath('data.0.tipo_rechazo', 'NO_HABILITADO')
            ->assertJsonPath('data.0.detalle', 'No cumple el requisito.');
    }

    public function test_limite_invalido_no_consulta_incidencias(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);

        $alcance = $this->createMock(AlcancePuntoControl::class);
        $alcance->method('puedeOperar')->willReturn(true);

        $incidencias = $this->createMock(IncidenciasIngresoGateway::class);
        $incidencias->expects(self::never())->method('recientes');

        $this->app->instance(AlcancePuntoControl::class, $alcance);
        $this->app->instance(IncidenciasIngresoGateway::class, $incidencias);

        $this->actingAs($usuario)
            ->getJson(self::BASE.'/incidencias?limite=51')
            ->assertUnprocessable();
    }

    public function test_visitante_no_puede_consultar_estudiante(): void
    {
        $this->getJson(self::BASE.'/candidato?tipo=codigo&identificador=123')
            ->assertUnauthorized();
    }

    public function test_visitante_no_puede_registrar_ingreso(): void
    {
        $this->postJson(self::BASE.'/ingresos', [
            'tipo' => 'codigo',
            'identificador' => '123',
        ])->assertUnauthorized();
    }

    public function test_visitante_no_puede_consultar_historial(): void
    {
        $this->getJson(self::BASE.'/ingresos')
            ->assertUnauthorized();
    }

    public function test_usuario_sin_permiso_no_accede(): void
    {
        $usuario = $this->usuarioConPermisos([]);

        $this->actingAs($usuario)
            ->getJson(self::BASE.'/ingresos')
            ->assertForbidden();
    }

    public function test_permiso_general_no_autoriza_consulta(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);

        $this->actingAs($usuario)
            ->getJson(self::BASE.'/candidato?tipo=codigo&identificador=123')
            ->assertForbidden();
    }

    public function test_permiso_general_no_autoriza_registro(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);

        $this->actingAs($usuario)
            ->postJson(self::BASE.'/ingresos', [
                'tipo' => 'codigo',
                'identificador' => '123',
            ])->assertForbidden();

        $this->assertDatabaseCount('ingresos', 0);
    }

    public function test_permiso_general_no_revela_ingresos_recientes(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);

        $this->actingAs($usuario)
            ->getJson(self::BASE.'/ingresos')
            ->assertForbidden();
    }

    public function test_no_crea_ingresos_ante_solicitudes_sin_asignacion(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);

        $this->actingAs($usuario)
            ->postJson(self::BASE.'/ingresos', [
                'tipo' => 'documento',
                'identificador' => '9876543',
            ])->assertForbidden();

        self::assertSame(0, DB::table('ingresos')->count());
    }

    public function test_operador_autorizado_obtiene_historial_http(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);

        $alcance = $this->createMock(AlcancePuntoControl::class);
        $alcance->method('puedeOperar')->willReturn(true);

        $ingresos = $this->createMock(IngresosRecientesGateway::class);
        $ingresos->expects(self::once())
            ->method('recientes')
            ->with(1, 1, 20)
            ->willReturn([]);

        $this->app->instance(AlcancePuntoControl::class, $alcance);
        $this->app->instance(IngresosRecientesGateway::class, $ingresos);

        $this->actingAs($usuario)
            ->getJson(self::BASE.'/ingresos')
            ->assertOk()
            ->assertJsonPath('meta.cantidad', 0)
            ->assertJsonPath('data', []);
    }

    public function test_operador_autorizado_recibe_422_con_limite_invalido(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);

        $alcance = $this->createMock(AlcancePuntoControl::class);
        $alcance->method('puedeOperar')->willReturn(true);

        $ingresos = $this->createMock(IngresosRecientesGateway::class);
        $ingresos->expects(self::never())->method('recientes');

        $this->app->instance(AlcancePuntoControl::class, $alcance);
        $this->app->instance(IngresosRecientesGateway::class, $ingresos);

        $this->actingAs($usuario)
            ->getJson(self::BASE.'/ingresos?limite=51')
            ->assertUnprocessable();
    }
}
