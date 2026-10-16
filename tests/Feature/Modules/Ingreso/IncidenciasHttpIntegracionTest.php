<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class IncidenciasHttpIntegracionTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_http_real_aisla_incidencias_por_examen_y_aula(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo($docente);

        $aulaA = $this->aula('INC-HTTP-A');
        $aulaB = $this->aula('INC-HTTP-B');

        [$estudiante] = $this->estudiantesInscritos($grupo, 1);

        $examenA = $this->examen(
            $docente,
            [$grupo],
            [$aulaA, $aulaB],
        );

        $examenB = $this->examen(
            $docente,
            [$grupo],
            [$aulaA],
        );

        $filas = [
            [$examenA->id, $aulaA->id, 'Visible en el punto autorizado.'],
            [$examenA->id, $aulaB->id, 'Incidencia de otra aula.'],
            [$examenB->id, $aulaA->id, 'Incidencia de otro examen.'],
        ];

        foreach ($filas as [$examenId, $aulaId, $detalle]) {
            DB::table('incidencias_ingreso')->insert([
                'examen_id' => $examenId,
                'aula_id' => $aulaId,
                'estudiante_id' => $estudiante->id,
                'registrado_por' => $usuario->id,
                'tipo_rechazo' => 'NO_HABILITADO',
                'detalle' => $detalle,
                'registrado_en' => now(),
            ]);
        }

        $alcance = $this->createMock(AlcancePuntoControl::class);

        $alcance->method('puedeOperar')
            ->willReturnCallback(
                static fn (
                    int $usuarioId,
                    int $examenId,
                    int $aulaId,
                ): bool => $usuarioId === $usuario->id
                    && $examenId === $examenA->id
                    && $aulaId === $aulaA->id,
            );

        $this->app->instance(AlcancePuntoControl::class, $alcance);

        $url = sprintf(
            '/api/examenes/%d/aulas/%d/punto-control/incidencias',
            $examenA->id,
            $aulaA->id,
        );

        $this->actingAs($usuario)
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('meta.cantidad', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estudiante_id', $estudiante->id)
            ->assertJsonPath('data.0.codigo', $estudiante->codigo_universitario)
            ->assertJsonPath('data.0.tipo_rechazo', 'NO_HABILITADO')
            ->assertJsonPath(
                'data.0.detalle',
                'Visible en el punto autorizado.',
            );

        $this->getJson(sprintf(
            '/api/examenes/%d/aulas/%d/punto-control/incidencias',
            $examenA->id,
            $aulaB->id,
        ))->assertForbidden();

        $this->getJson(sprintf(
            '/api/examenes/%d/aulas/%d/punto-control/incidencias',
            $examenB->id,
            $aulaA->id,
        ))->assertForbidden();

        $this->assertDatabaseCount('incidencias_ingreso', 3);
    }
}
