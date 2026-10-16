<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class RegistrarIngresoEnPuertaHttpTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private const RUTA = '/api/aulas/1/punto-control/ingresos';

    public function test_rechaza_usuario_no_autenticado(): void
    {
        $this->postJson(self::RUTA, [
            'tipo' => 'codigo',
            'identificador' => '20260017',
        ])->assertUnauthorized();
    }

    public function test_rechaza_usuario_sin_permiso(): void
    {
        $usuario = $this->usuarioConPermisos([]);

        $this->actingAs($usuario)
            ->postJson(self::RUTA, [
                'tipo' => 'codigo',
                'identificador' => '20260017',
            ])
            ->assertForbidden();
    }

    public function test_rechaza_identificador_invalido(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);

        $this->actingAs($usuario)
            ->postJson(self::RUTA, [
                'tipo' => 'codigo',
                'identificador' => '12ABC',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['identificador']);
    }

    public function test_rechaza_tipo_invalido(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);

        $this->actingAs($usuario)
            ->postJson(self::RUTA, [
                'tipo' => 'qr',
                'identificador' => '20260017',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo']);
    }

    public function test_no_registra_sin_asignacion(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);

        $this->actingAs($usuario)
            ->postJson(self::RUTA, [
                'tipo' => 'codigo',
                'identificador' => '20260017',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('ingresos', 0);
    }

    public function test_registra_ingreso_habilitado_desde_el_aula(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta();

        $respuesta = $this->actingAs($usuario)
            ->postJson(
                "/api/aulas/{$aula->id}/punto-control/ingresos",
                [
                    'tipo' => 'codigo',
                    'identificador' => $estudiante->codigo_universitario,
                ],
            );

        $respuesta
            ->assertCreated()
            ->assertJsonPath('data.examen_id', $examen->id)
            ->assertJsonPath('data.veredicto', 'ADMITIDO')
            ->assertJsonPath('data.admitido', true);

        $this->assertDatabaseHas('ingresos', [
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
            'aula_id' => $aula->id,
            'registrado_por' => $usuario->id,
        ]);
    }

    public function test_segundo_ingreso_no_duplica_registro(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta();

        $url = "/api/aulas/{$aula->id}/punto-control/ingresos";
        $datos = [
            'tipo' => 'codigo',
            'identificador' => $estudiante->codigo_universitario,
        ];

        $this->actingAs($usuario)
            ->postJson($url, $datos)
            ->assertCreated();

        $this->postJson($url, $datos)
            ->assertConflict()
            ->assertJsonPath('data.admitido', false);

        self::assertSame(
            1,
            DB::table('ingresos')
                ->where('examen_id', $examen->id)
                ->where('estudiante_id', $estudiante->id)
                ->count(),
        );
    }

    public function test_no_admite_estudiante_inhabilitado(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta(false);

        $this->actingAs($usuario)
            ->postJson(
                "/api/aulas/{$aula->id}/punto-control/ingresos",
                [
                    'tipo' => 'codigo',
                    'identificador' => $estudiante->codigo_universitario,
                ],
            )
            ->assertConflict()
            ->assertJsonPath('data.admitido', false);

        $this->assertDatabaseMissing('ingresos', [
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
        ]);
    }

    /**
     * @return array{
     *   User,
     *   Examen,
     *   Aula,
     *   Estudiante
     * }
     */
    private function escenarioDePuerta(bool $habilitado = true): array
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo($docente);
        $aula = $this->aula('P-201');

        [$estudiante] = $this->estudiantesInscritos($grupo, 1);

        $inicio = now('America/La_Paz')->subMinutes(5);

        $examen = $this->examen(
            $docente,
            [$grupo],
            [$aula],
            [
                'fecha' => $inicio->toDateString(),
                'hora_inicio' => $inicio->format('H:i:00'),
                'duracion_minutos' => 90,
            ],
        );

        if ($habilitado) {
            $this->habilitar($examen, [$estudiante], $aula);
        } else {
            $this->inhabilitar($examen, [$estudiante]);
        }

        $alcance = $this->createMock(AlcancePuntoControl::class);

        $alcance->method('puedeOperar')
            ->willReturnCallback(
                static fn (
                    int $usuarioId,
                    int $examenId,
                    int $aulaId,
                ): bool => $usuarioId === $usuario->id
                    && $examenId === $examen->id
                    && $aulaId === $aula->id,
            );

        $this->app->instance(
            AlcancePuntoControl::class,
            $alcance,
        );

        return [$usuario, $examen, $aula, $estudiante];
    }
}
