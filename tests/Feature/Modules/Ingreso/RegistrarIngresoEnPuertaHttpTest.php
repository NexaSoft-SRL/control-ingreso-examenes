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
            ->assertJsonPath('data.admitido', true)
            ->assertJsonPath('data.codigo', $estudiante->codigo_universitario)
            ->assertJsonStructure(['data' => [
                'nombre',
                'registrado_en',
            ]]);

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

    public function test_bug05_expulsion_persistida_y_visible_en_historial(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta();

        $base = "/api/aulas/{$aula->id}/punto-control";

        $this->actingAs($usuario)
            ->postJson("{$base}/ingresos", [
                'tipo' => 'codigo',
                'identificador' => $estudiante->codigo_universitario,
            ])
            ->assertCreated();

        $ingresoId = DB::table('ingresos')
            ->where('examen_id', $examen->id)
            ->where('estudiante_id', $estudiante->id)
            ->value('id');

        self::assertIsInt($ingresoId);

        $this->postJson("{$base}/ingresos/{$ingresoId}/expulsion", [
            'motivo' => 'Incumplimiento de normas del examen',
        ])
            ->assertCreated()
            ->assertJsonPath('data.ingreso_id', $ingresoId)
            ->assertJsonPath('data.expulsado', true);

        $this->assertDatabaseHas('expulsiones', [
            'ingreso_id' => $ingresoId,
            'registrada_por' => $usuario->id,
            'motivo' => 'Incumplimiento de normas del examen',
        ]);

        $fecha = DB::table('expulsiones')
            ->where('ingreso_id', $ingresoId)
            ->value('registrada_en');

        self::assertNotNull($fecha);

        $this->getJson("{$base}/ingresos?limite=5")
            ->assertOk()
            ->assertJsonPath('data.0.ingreso_id', $ingresoId)
            ->assertJsonPath('data.0.expulsado', true)
            ->assertJsonPath('data.0.codigo', $estudiante->codigo_universitario);
    }

    public function test_bug05_no_permite_expulsion_duplicada(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta();

        $base = "/api/aulas/{$aula->id}/punto-control";

        $this->actingAs($usuario)
            ->postJson("{$base}/ingresos", [
                'tipo' => 'codigo',
                'identificador' => $estudiante->codigo_universitario,
            ])
            ->assertCreated();

        $ingresoId = DB::table('ingresos')
            ->where('examen_id', $examen->id)
            ->where('estudiante_id', $estudiante->id)
            ->value('id');

        self::assertIsInt($ingresoId);

        $ruta = "{$base}/ingresos/{$ingresoId}/expulsion";

        $this->postJson($ruta, [
            'motivo' => 'Primera expulsión correctamente registrada',
        ])->assertCreated();

        $this->postJson($ruta, [
            'motivo' => 'Intento de reemplazar la primera expulsión',
        ])->assertConflict();

        self::assertSame(
            1,
            DB::table('expulsiones')
                ->where('ingreso_id', $ingresoId)
                ->count(),
        );

        $this->assertDatabaseHas('expulsiones', [
            'ingreso_id' => $ingresoId,
            'motivo' => 'Primera expulsión correctamente registrada',
        ]);
    }

    public function test_bug05_rechaza_expulsion_desde_otra_aula(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta();

        $this->actingAs($usuario)
            ->postJson("/api/aulas/{$aula->id}/punto-control/ingresos", [
                'tipo' => 'codigo',
                'identificador' => $estudiante->codigo_universitario,
            ])
            ->assertCreated();

        $ingresoId = DB::table('ingresos')
            ->where('examen_id', $examen->id)
            ->where('estudiante_id', $estudiante->id)
            ->value('id');

        self::assertIsInt($ingresoId);

        $otraAula = $this->aula('P-202');

        $this->postJson(
            "/api/aulas/{$otraAula->id}/punto-control/ingresos/{$ingresoId}/expulsion",
            ['motivo' => 'Intento de expulsión desde aula diferente'],
        )->assertNotFound();

        $this->assertDatabaseCount('expulsiones', 0);
    }

    public function test_bug05_rechaza_operador_sin_alcance(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta();

        $this->actingAs($usuario)
            ->postJson("/api/aulas/{$aula->id}/punto-control/ingresos", [
                'tipo' => 'codigo',
                'identificador' => $estudiante->codigo_universitario,
            ])
            ->assertCreated();

        $ingresoId = DB::table('ingresos')
            ->where('examen_id', $examen->id)
            ->where('estudiante_id', $estudiante->id)
            ->value('id');

        self::assertIsInt($ingresoId);

        $otroOperador = $this->usuarioConPermisos(['punto_control']);

        $this->actingAs($otroOperador)
            ->postJson(
                "/api/aulas/{$aula->id}/punto-control/ingresos/{$ingresoId}/expulsion",
                ['motivo' => 'Operador sin asignación al examen'],
            )
            ->assertForbidden();

        $this->assertDatabaseCount('expulsiones', 0);
    }

    public function test_bug09_registra_incidencia_manual_con_auditoria(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta();

        $ruta = "/api/aulas/{$aula->id}/punto-control/incidencias-manuales";

        $respuesta = $this->actingAs($usuario)->postJson($ruta, [
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
            'tipo' => 'DOCUMENTO_OBSERVADO',
            'detalle' => 'El estudiante presentó un documento deteriorado.',
        ]);

        $respuesta
            ->assertCreated()
            ->assertJsonPath('data.examen_id', $examen->id)
            ->assertJsonPath('data.aula_id', $aula->id)
            ->assertJsonPath('data.estudiante_id', $estudiante->id)
            ->assertJsonPath('data.tipo', 'DOCUMENTO_OBSERVADO');

        $incidenciaId = $respuesta->json('data.incidencia_id');

        self::assertIsInt($incidenciaId);

        $this->assertDatabaseHas('incidencias_aula', [
            'id' => $incidenciaId,
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_id' => $estudiante->id,
            'reportada_por' => $usuario->id,
            'tipo' => 'DOCUMENTO_OBSERVADO',
            'detalle' => 'El estudiante presentó un documento deteriorado.',
        ]);

        self::assertNotNull(
            DB::table('incidencias_aula')
                ->where('id', $incidenciaId)
                ->value('reportada_en'),
        );
    }

    public function test_bug09_permite_incidencia_sin_estudiante(): void
    {
        [$usuario, $examen, $aula] =
            $this->escenarioDePuerta();

        $ruta = "/api/aulas/{$aula->id}/punto-control/incidencias-manuales";

        $this->actingAs($usuario)->postJson($ruta, [
            'examen_id' => $examen->id,
            'tipo' => 'INCIDENTE_GENERAL',
            'detalle' => 'Interrupción momentánea de energía eléctrica.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.estudiante_id', null);

        $this->assertDatabaseHas('incidencias_aula', [
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_id' => null,
            'reportada_por' => $usuario->id,
        ]);
    }

    public function test_bug09_rechaza_examen_ajeno_al_aula(): void
    {
        [$usuario, $examen, $aula] =
            $this->escenarioDePuerta();

        $otraAula = $this->aula('P-903');

        $this->actingAs($usuario)->postJson(
            "/api/aulas/{$otraAula->id}/punto-control/incidencias-manuales",
            [
                'examen_id' => $examen->id,
                'tipo' => 'INCIDENTE_GENERAL',
                'detalle' => 'No corresponde al aula del examen.',
            ],
        )->assertNotFound();

        $this->assertDatabaseCount('incidencias_aula', 0);
    }

    public function test_bug09_rechaza_usuario_sin_alcance_y_datos_invalidos(): void
    {
        [$usuario, $examen, $aula] =
            $this->escenarioDePuerta();

        $ruta = "/api/aulas/{$aula->id}/punto-control/incidencias-manuales";

        $this->actingAs($usuario)->postJson($ruta, [
            'examen_id' => $examen->id,
            'tipo' => 'OK',
            'detalle' => 'abc',
        ])->assertUnprocessable();

        $otroUsuario = $this->usuarioConPermisos(['punto_control']);

        $this->actingAs($otroUsuario)->postJson($ruta, [
            'examen_id' => $examen->id,
            'tipo' => 'INCIDENTE_GENERAL',
            'detalle' => 'No debe permitirse esta operación.',
        ])->assertForbidden();

        $this->assertDatabaseCount('incidencias_aula', 0);
    }

    public function test_bug09_get_recupera_incidencia_persistida(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta();

        $ruta = "/api/aulas/{$aula->id}/punto-control/incidencias-manuales";

        $this->actingAs($usuario)->postJson($ruta, [
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
            'tipo' => 'DOCUMENTO_OBSERVADO',
            'detalle' => 'Documento deteriorado observado en puerta.',
        ])->assertCreated();

        $respuesta = $this->getJson(
            "{$ruta}?examen_id={$examen->id}&limite=20",
        );

        $respuesta
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.examen_id', $examen->id)
            ->assertJsonPath('data.0.aula_id', $aula->id)
            ->assertJsonPath('data.0.estudiante_id', $estudiante->id)
            ->assertJsonPath('data.0.codigo', $estudiante->codigo_universitario)
            ->assertJsonPath('data.0.tipo', 'DOCUMENTO_OBSERVADO')
            ->assertJsonPath(
                'data.0.detalle',
                'Documento deteriorado observado en puerta.',
            )
            ->assertJsonPath('data.0.reportada_por', $usuario->id);

        self::assertNotNull($respuesta->json('data.0.reportada_en'));
    }

    public function test_bug09_get_no_revela_incidencias_a_otro_operador(): void
    {
        [$usuario, $examen, $aula] =
            $this->escenarioDePuerta();

        $ruta = "/api/aulas/{$aula->id}/punto-control/incidencias-manuales";

        $this->actingAs($usuario)->postJson($ruta, [
            'examen_id' => $examen->id,
            'tipo' => 'INCIDENTE_GENERAL',
            'detalle' => 'Incidencia reservada al personal asignado.',
        ])->assertCreated();

        $ajeno = $this->usuarioConPermisos(['punto_control']);

        $this->actingAs($ajeno)
            ->getJson("{$ruta}?examen_id={$examen->id}")
            ->assertForbidden();

        $this->assertDatabaseCount('incidencias_aula', 1);
    }

    public function test_bug09_get_rechaza_filtros_invalidos_y_aula_ajena(): void
    {
        [$usuario, $examen, $aula] =
            $this->escenarioDePuerta();

        $ruta = "/api/aulas/{$aula->id}/punto-control/incidencias-manuales";

        $this->actingAs($usuario)
            ->getJson("{$ruta}?examen_id={$examen->id}&limite=51")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limite']);

        $this->getJson("{$ruta}?limite=5")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['examen_id']);

        $otraAula = $this->aula('P-905');

        $this->getJson(
            "/api/aulas/{$otraAula->id}/punto-control/incidencias-manuales?examen_id={$examen->id}",
        )->assertNotFound();
    }

    public function test_bug09_rechaza_estudiante_inscrito_en_grupo_ajeno(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta();

        $grupoAjeno = $this->grupo();
        [$estudianteAjeno] = $this->estudiantesInscritos($grupoAjeno, 1);

        self::assertNotSame($estudiante->id, $estudianteAjeno->id);

        $ruta = "/api/aulas/{$aula->id}/punto-control/incidencias-manuales";

        $this->actingAs($usuario)->postJson($ruta, [
            'examen_id' => $examen->id,
            'estudiante_id' => $estudianteAjeno->id,
            'tipo' => 'DOCUMENTO_OBSERVADO',
            'detalle' => 'Este estudiante pertenece a otro grupo.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['estudiante_id']);

        $this->assertDatabaseCount('incidencias_aula', 0);

        // El estudiante ajeno sigue existiendo: no es un error de FK.
        $this->assertDatabaseHas('estudiantes', [
            'id' => $estudianteAjeno->id,
        ]);

        // Una incidencia general sigue siendo válida.
        $this->postJson($ruta, [
            'examen_id' => $examen->id,
            'tipo' => 'INCIDENTE_GENERAL',
            'detalle' => 'Incidente general sin estudiante asociado.',
        ])->assertCreated();

        $this->assertDatabaseCount('incidencias_aula', 1);
    }

    public function test_bug09_rechaza_identificadores_simultaneos(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta();

        $ruta = "/api/aulas/{$aula->id}/punto-control/incidencias-manuales";

        $this->actingAs($usuario)->postJson($ruta, [
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
            'codigo_universitario' => $estudiante->codigo_universitario,
            'tipo' => 'DOCUMENTO_OBSERVADO',
            'detalle' => 'No se admiten dos identificadores simultáneos.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'estudiante_id',
                'codigo_universitario',
            ]);

        $this->assertDatabaseCount('incidencias_aula', 0);
    }

    public function test_bug09_codigo_universitario_resuelve_estudiante(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta();

        $ruta = "/api/aulas/{$aula->id}/punto-control/incidencias-manuales";

        $respuesta = $this->actingAs($usuario)->postJson($ruta, [
            'examen_id' => $examen->id,
            'codigo_universitario' => $estudiante->codigo_universitario,
            'tipo' => 'DOCUMENTO_OBSERVADO',
            'detalle' => 'Documento universitario deteriorado.',
        ]);

        $respuesta
            ->assertCreated()
            ->assertJsonPath('data.estudiante_id', $estudiante->id);

        $this->assertDatabaseHas('incidencias_aula', [
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_id' => $estudiante->id,
            'reportada_por' => $usuario->id,
            'tipo' => 'DOCUMENTO_OBSERVADO',
        ]);
    }

    public function test_bug09_codigo_universitario_inexistente_es_rechazado(): void
    {
        [$usuario, $examen, $aula] =
            $this->escenarioDePuerta();

        $ruta = "/api/aulas/{$aula->id}/punto-control/incidencias-manuales";

        $this->actingAs($usuario)->postJson($ruta, [
            'examen_id' => $examen->id,
            'codigo_universitario' => '9999999999',
            'tipo' => 'DOCUMENTO_OBSERVADO',
            'detalle' => 'Código que no corresponde a este examen.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo_universitario']);

        $this->assertDatabaseCount('incidencias_aula', 0);
    }

    public function test_bug09_codigo_universitario_de_otro_grupo_es_rechazado(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta();

        $grupoAjeno = $this->grupo();
        [$ajeno] = $this->estudiantesInscritos($grupoAjeno, 1);

        self::assertNotSame($estudiante->id, $ajeno->id);

        $ruta = "/api/aulas/{$aula->id}/punto-control/incidencias-manuales";

        $this->actingAs($usuario)->postJson($ruta, [
            'examen_id' => $examen->id,
            'codigo_universitario' => $ajeno->codigo_universitario,
            'tipo' => 'DOCUMENTO_OBSERVADO',
            'detalle' => 'Código de estudiante perteneciente a otro grupo.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo_universitario']);

        $this->assertDatabaseCount('incidencias_aula', 0);
    }

    public function test_bug09_codigo_universitario_invalido_es_rechazado(): void
    {
        [$usuario, $examen, $aula] =
            $this->escenarioDePuerta();

        $ruta = "/api/aulas/{$aula->id}/punto-control/incidencias-manuales";

        foreach (['12', 'ABC123', '12345678901'] as $codigo) {
            $this->actingAs($usuario)->postJson($ruta, [
                'examen_id' => $examen->id,
                'codigo_universitario' => $codigo,
                'tipo' => 'DOCUMENTO_OBSERVADO',
                'detalle' => 'Identificador universitario inválido.',
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['codigo_universitario']);
        }

        $this->assertDatabaseCount('incidencias_aula', 0);
    }

    public function test_bug06_registra_situaciones_excepcionales_con_auditoria(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta(false);

        $ruta = "/api/aulas/{$aula->id}/punto-control/excepciones";

        foreach ([
            'EXCEPCION_IDENTIFICACION',
            'EXCEPCION_NO_HABILITADO',
        ] as $tipo) {
            $this->actingAs($usuario)->postJson($ruta, [
                'examen_id' => $examen->id,
                'estudiante_id' => $estudiante->id,
                'tipo' => $tipo,
                'motivo' => 'Situación excepcional documentada y verificada.',
            ])
                ->assertCreated()
                ->assertJsonPath('data.tipo', $tipo)
                ->assertJsonPath('data.admitido', false);

            $this->assertDatabaseHas('incidencias_aula', [
                'examen_id' => $examen->id,
                'aula_id' => $aula->id,
                'estudiante_id' => $estudiante->id,
                'reportada_por' => $usuario->id,
                'tipo' => $tipo,
                'detalle' => 'Situación excepcional documentada y verificada.',
            ]);
        }

        // A un no habilitado el cambio de aula no lo deja entrar.
        $this->actingAs($usuario)->postJson($ruta, [
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
            'tipo' => 'EXCEPCION_CAMBIO_AULA',
            'motivo' => 'Situación excepcional documentada y verificada.',
        ])->assertConflict();

        $this->assertDatabaseCount('incidencias_aula', 2);
        $this->assertDatabaseCount('ingresos', 0);

        // Las excepciones no entran como incidencia comun (N5).
        $this->actingAs($usuario)->postJson(
            "/api/aulas/{$aula->id}/punto-control/incidencias-manuales",
            [
                'examen_id' => $examen->id,
                'tipo' => 'EXCEPCION_CAMBIO_AULA',
                'detalle' => 'Situación excepcional documentada y verificada.',
            ],
        )->assertUnprocessable()->assertJsonValidationErrors('tipo');
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
            ->assertConflict()
            ->assertJsonPath('data.causa', 'QR_NO_VALIDO')
            ->assertJsonPath('data.dato', 'Código QR no válido');

        $this->assertDatabaseHas('incidencias_ingreso', [
            'examen_id' => null,
            'aula_id' => $aula->id,
            'tipo_rechazo' => 'QR_NO_VALIDO',
            'registrado_por' => $usuario->id,
        ]);

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
            ->assertConflict()
            ->assertJsonPath('data.causa', 'QR_NO_VALIDO')
            ->assertJsonPath('data.dato', 'Código QR no válido');

        $this->assertDatabaseHas('incidencias_ingreso', [
            'examen_id' => null,
            'aula_id' => $aula->id,
            'tipo_rechazo' => 'QR_NO_VALIDO',
            'registrado_por' => $usuario->id,
        ]);

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
            ->assertConflict()
            ->assertJsonPath('data.causa', 'QR_NO_VALIDO');

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

    public function test_identificacion_no_habilitada_registra_el_intento(): void
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
        // BUG-04: identificar a quien no puede entrar deja el intento.
        $this->assertDatabaseHas('incidencias_ingreso', [
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
            'tipo_rechazo' => 'NO_HABILITADO',
            'detalle' => 'No cumple el requisito.',
        ]);

        // Confirmar enseguida no duplica el mismo intento.
        $this->actingAs($usuario)->postJson(
            "/api/aulas/{$aula->id}/punto-control/ingresos",
            ['tipo' => 'codigo', 'identificador' => $estudiante->codigo_universitario],
        )->assertConflict()
            ->assertJsonPath('data.dato', 'No cumple el requisito.');

        $this->assertDatabaseCount('incidencias_ingreso', 1);
    }

    public function test_intento_sin_examen_tiene_auditoria_independiente(): void
    {
        [$usuario, $examen, $aula] = $this->escenarioDePuerta();

        $this->actingAs($usuario)->postJson(
            "/api/aulas/{$aula->id}/punto-control/ingresos",
            ['tipo' => 'codigo', 'identificador' => '9999999999'],
        )->assertConflict()
            ->assertJsonPath('data.causa', 'NO_REGISTRADO')
            ->assertJsonPath('data.dato', '9999999999 · sin registro en el padrón');

        // BUG-04: sin examen propio, pero en las incidencias del aula.
        $this->assertDatabaseHas('incidencias_ingreso', [
            'examen_id' => null,
            'aula_id' => $aula->id,
            'registrado_por' => $usuario->id,
            'tipo_rechazo' => 'NO_REGISTRADO',
            'detalle' => 'Código 9999999999 sin registro en el padrón.',
        ]);
        $this->assertDatabaseCount('ingresos', 0);
    }

    public function test_expulsion_registrada_no_se_puede_repetir(): void
    {
        [$usuario, $examen, $aula, $estudiante] = $this->escenarioDePuerta();

        $this->actingAs($usuario)->postJson(
            "/api/aulas/{$aula->id}/punto-control/ingresos",
            ['tipo' => 'codigo', 'identificador' => $estudiante->codigo_universitario],
        )->assertCreated();

        $idPersistido = DB::table('ingresos')->where('examen_id', $examen->id)
            ->where('estudiante_id', $estudiante->id)->value('id');

        $this->assertTrue(is_int($idPersistido) || is_string($idPersistido));

        $ingresoId = (int) $idPersistido;
        $ruta = "/api/aulas/{$aula->id}/punto-control/ingresos/{$ingresoId}/expulsion";

        $this->postJson($ruta, ['motivo' => 'Incumplimiento de normas del examen'])
            ->assertCreated()->assertJsonPath('data.expulsado', true);

        $this->assertDatabaseHas('expulsiones', [
            'ingreso_id' => $ingresoId,
            'registrada_por' => $usuario->id,
        ]);

        $this->postJson($ruta, ['motivo' => 'Segunda expulsión'])
            ->assertConflict();
        $this->assertDatabaseCount('expulsiones', 1);
    }
}
