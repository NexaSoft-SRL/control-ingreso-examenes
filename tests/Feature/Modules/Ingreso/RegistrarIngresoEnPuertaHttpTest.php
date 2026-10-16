<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Estudiantes\Domain\Enums\OrigenEstudiante;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Estudiantes\Domain\Models\Inscripcion;
use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
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
                'tipo' => 'invalido',
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

    public function test_qr_valido_registra_ingreso(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta();

        $qr = app(CodigoQrGateway::class)->generar(
            $examen->id,
            $estudiante->codigo_universitario,
        );

        $this->actingAs($usuario)
            ->postJson(
                "/api/aulas/{$aula->id}/punto-control/ingresos",
                ['tipo' => 'qr', 'identificador' => $qr],
            )
            ->assertCreated()
            ->assertJsonPath('data.examen_id', $examen->id)
            ->assertJsonPath('data.veredicto', 'ADMITIDO');

        $this->assertDatabaseHas('ingresos', [
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
            'aula_id' => $aula->id,
        ]);
    }

    public function test_qr_de_otro_examen_no_registra_ingreso(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta();

        $qr = app(CodigoQrGateway::class)->generar(
            $examen->id + 10000,
            $estudiante->codigo_universitario,
        );

        $this->actingAs($usuario)
            ->postJson(
                "/api/aulas/{$aula->id}/punto-control/ingresos",
                ['tipo' => 'qr', 'identificador' => $qr],
            )
            ->assertUnprocessable();

        $this->assertDatabaseCount('ingresos', 0);
    }

    public function test_qr_manipulado_no_registra_ingreso(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta();

        $qr = app(CodigoQrGateway::class)->generar(
            $examen->id,
            $estudiante->codigo_universitario,
        );

        $this->actingAs($usuario)
            ->postJson(
                "/api/aulas/{$aula->id}/punto-control/ingresos",
                [
                    'tipo' => 'qr',
                    'identificador' => $qr.'alterado',
                ],
            )
            ->assertUnprocessable();

        $this->assertDatabaseCount('ingresos', 0);
    }

    public function test_qr_selecciona_examen_correcto_entre_dos(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta();

        $docente = Docente::query()
            ->where('user_id', $usuario->id)
            ->firstOrFail();
        $grupo = $this->grupo($docente);

        $otroExamen = $this->examen(
            $docente,
            [$grupo],
            [$aula],
            [
                'fecha' => $examen->fecha->format('Y-m-d'),
                'hora_inicio' => $examen->hora_inicio,
            ],
        );

        $this->habilitar($otroExamen, [$estudiante], $aula);

        $qr = app(CodigoQrGateway::class)->generar(
            $examen->id,
            $estudiante->codigo_universitario,
        );

        $this->actingAs($usuario)
            ->postJson(
                "/api/aulas/{$aula->id}/punto-control/ingresos",
                ['tipo' => 'qr', 'identificador' => $qr],
            )
            ->assertCreated()
            ->assertJsonPath('data.examen_id', $examen->id);

        $this->assertDatabaseCount('ingresos', 1);

        $this->assertDatabaseHas('ingresos', [
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
        ]);
    }

    public function test_identifica_qr_sin_registrar_ingreso(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta();

        $qr = app(CodigoQrGateway::class)->generar(
            $examen->id,
            $estudiante->codigo_universitario,
        );

        $this->actingAs($usuario)
            ->postJson(
                "/api/aulas/{$aula->id}/punto-control/identificar",
                ['tipo' => 'qr', 'identificador' => $qr],
            )
            ->assertOk()
            ->assertJsonPath('data.examen_id', $examen->id)
            ->assertJsonPath('data.veredicto', 'ADMITIDO')
            ->assertJsonPath('data.admisible', true);

        $this->assertDatabaseCount('ingresos', 0);
    }

    public function test_qr_de_examen_no_autorizado_no_registra(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta();

        $docente = Docente::query()
            ->where('user_id', $usuario->id)
            ->firstOrFail();
        $grupo = $this->grupo($docente);

        $otroExamen = $this->examen(
            $docente,
            [$grupo],
            [$aula],
            [
                'fecha' => $examen->fecha->format('Y-m-d'),
                'hora_inicio' => $examen->hora_inicio,
            ],
        );

        $this->habilitar($otroExamen, [$estudiante], $aula);

        $qr = app(CodigoQrGateway::class)->generar(
            $otroExamen->id,
            $estudiante->codigo_universitario,
        );

        $this->actingAs($usuario)
            ->postJson(
                "/api/aulas/{$aula->id}/punto-control/ingresos",
                ['tipo' => 'qr', 'identificador' => $qr],
            )
            ->assertUnprocessable();

        $this->assertDatabaseCount('ingresos', 0);
    }

    public function test_identificacion_manual_ambigua_no_registra(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta();

        $otroExamen = $this->segundoExamenAutorizado(
            $usuario,
            $examen,
            $aula,
            $estudiante,
        );

        $this->actingAs($usuario)
            ->postJson(
                "/api/aulas/{$aula->id}/punto-control/identificar",
                [
                    'tipo' => 'codigo',
                    'identificador' => $estudiante->codigo_universitario,
                ],
            )
            ->assertOk()
            ->assertJsonPath('data.ambiguo', true)
            ->assertJsonPath('data.admisible', false)
            ->assertJsonPath('data.examen_id', null);

        $this->postJson(
            "/api/aulas/{$aula->id}/punto-control/ingresos",
            [
                'tipo' => 'codigo',
                'identificador' => $estudiante->codigo_universitario,
            ],
        )
            ->assertConflict()
            ->assertJsonPath('data.ambiguo', true);

        $this->assertDatabaseCount('ingresos', 0);
    }

    public function test_qr_distingue_dos_examenes_autorizados(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta();

        $otroExamen = $this->segundoExamenAutorizado(
            $usuario,
            $examen,
            $aula,
            $estudiante,
        );

        $qr = app(CodigoQrGateway::class)->generar(
            $otroExamen->id,
            $estudiante->codigo_universitario,
        );

        $this->actingAs($usuario)
            ->postJson(
                "/api/aulas/{$aula->id}/punto-control/ingresos",
                ['tipo' => 'qr', 'identificador' => $qr],
            )
            ->assertCreated()
            ->assertJsonPath('data.examen_id', $otroExamen->id);

        $this->assertDatabaseHas('ingresos', [
            'examen_id' => $otroExamen->id,
            'estudiante_id' => $estudiante->id,
            'aula_id' => $aula->id,
        ]);

        $this->assertDatabaseMissing('ingresos', [
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
        ]);
    }

    private function segundoExamenAutorizado(
        User $usuario,
        Examen $examen,
        Aula $aula,
        Estudiante $estudiante,
    ): Examen {
        $docente = Docente::query()
            ->where('user_id', $usuario->id)
            ->firstOrFail();

        $grupo = $this->grupo($docente);

        // El estudiante debe estar inscrito en ambos exámenes.
        Inscripcion::create([
            'estudiante_id' => $estudiante->id,
            'grupo_id' => $grupo->id,
            'via' => OrigenEstudiante::Administracion,
        ]);

        $otroExamen = $this->examen(
            $docente,
            [$grupo],
            [$aula],
            [
                'fecha' => $examen->fecha->format('Y-m-d'),
                'hora_inicio' => $examen->hora_inicio,
                'duracion_minutos' => 90,
            ],
        );

        $this->habilitar($otroExamen, [$estudiante], $aula);

        $alcance = $this->createMock(AlcancePuntoControl::class);
        $ids = [$examen->id, $otroExamen->id];

        $alcance->method('puedeOperar')
            ->willReturnCallback(
                static fn (
                    int $usuarioId,
                    int $examenId,
                    int $aulaId,
                ): bool => $usuarioId === $usuario->id
                    && in_array($examenId, $ids, true)
                    && $aulaId === $aula->id,
            );

        $this->app->instance(AlcancePuntoControl::class, $alcance);

        return $otroExamen;
    }

    public function test_ingreso_duplicado_genera_incidencia_auditada(): void
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
            ->assertJsonPath('data.veredicto', 'YA_INGRESO')
            ->assertJsonPath('data.admitido', false);

        $this->assertDatabaseCount('ingresos', 1);
        $this->assertDatabaseCount('incidencias_ingreso', 1);

        $this->assertDatabaseHas('incidencias_ingreso', [
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_id' => $estudiante->id,
            'registrado_por' => $usuario->id,
            'tipo_rechazo' => 'YA_INGRESO',
        ]);
    }

    public function test_estudiante_inhabilitado_genera_incidencia_auditada(): void
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
            ->assertJsonPath('data.veredicto', 'NO_HABILITADO')
            ->assertJsonPath('data.admitido', false);

        $this->assertDatabaseCount('ingresos', 0);
        $this->assertDatabaseCount('incidencias_ingreso', 1);

        $this->assertDatabaseHas('incidencias_ingreso', [
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_id' => $estudiante->id,
            'registrado_por' => $usuario->id,
            'tipo_rechazo' => 'NO_HABILITADO',
        ]);
    }

    public function test_identificacion_sin_registro_no_genera_incidencia(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta(false);

        $this->actingAs($usuario)
            ->postJson(
                "/api/aulas/{$aula->id}/punto-control/identificar",
                [
                    'tipo' => 'codigo',
                    'identificador' => $estudiante->codigo_universitario,
                ],
            )
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'NO_HABILITADO');

        $this->assertDatabaseCount('ingresos', 0);
        $this->assertDatabaseCount('incidencias_ingreso', 0);
    }
}
