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

        $this->assertDatabaseHas('incidencias', [
            'ingreso_id' => $ingresoId,
            'reportada_por' => $usuario->id,
            'detalle' => 'Incumplimiento de normas del examen',
            'tipo' => 'EXPULSION',
        ]);

        $fecha = DB::table('incidencias')->where('tipo', 'EXPULSION')
            ->where('ingreso_id', $ingresoId)
            ->value('ocurrida_en');

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
            DB::table('incidencias')->where('tipo', 'EXPULSION')
                ->where('ingreso_id', $ingresoId)
                ->count(),
        );

        $this->assertDatabaseHas('incidencias', [
            'ingreso_id' => $ingresoId,
            'detalle' => 'Primera expulsión correctamente registrada',
            'tipo' => 'EXPULSION',
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

        $this->assertSame(0, DB::table('incidencias')->where('tipo', 'EXPULSION')->count());
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

        $this->assertSame(0, DB::table('incidencias')->where('tipo', 'EXPULSION')->count());
    }

    public function test_bug06_registra_situaciones_excepcionales_con_auditoria(): void
    {
        [$usuario, $examen, $aula, $estudiante] =
            $this->escenarioDePuerta(false);

        $ruta = "/api/aulas/{$aula->id}/punto-control/excepciones";

        foreach ([
            'EXCEPCION_IDENTIFICACION' => ['OTRA', 'Problema de identificación · '],
            'EXCEPCION_NO_HABILITADO' => ['INTENTO_NO_AUTORIZADO', 'Estudiante no habilitado · '],
        ] as $tipo => [$catalogo, $prefijo]) {
            $this->actingAs($usuario)->postJson($ruta, [
                'examen_id' => $examen->id,
                'estudiante_id' => $estudiante->id,
                'tipo' => $tipo,
                'motivo' => 'Situación excepcional documentada y verificada.',
            ])
                ->assertCreated()
                ->assertJsonPath('data.tipo', $tipo)
                ->assertJsonPath('data.admitido', false);

            // Queda en las incidencias, con su tipo del catalogo.
            $this->assertDatabaseHas('incidencias', [
                'examen_id' => $examen->id,
                'aula_id' => $aula->id,
                'estudiante_id' => $estudiante->id,
                'reportada_por' => $usuario->id,
                'tipo' => $catalogo,
                'origen' => 'PERSONA',
                'detalle' => $prefijo.'Situación excepcional documentada y verificada.',
            ]);
        }

        // A un no habilitado el cambio de aula no lo deja entrar.
        $this->actingAs($usuario)->postJson($ruta, [
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
            'tipo' => 'EXCEPCION_CAMBIO_AULA',
            'motivo' => 'Situación excepcional documentada y verificada.',
        ])->assertConflict();

        $this->assertDatabaseCount('incidencias', 2);
        $this->assertDatabaseCount('ingresos', 0);
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

        $this->assertDatabaseHas('incidencias', [
            'examen_id' => null,
            'aula_id' => $aula->id,
            'causa' => 'QR_NO_VALIDO',
            'reportada_por' => $usuario->id,
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

        $this->assertDatabaseHas('incidencias', [
            'examen_id' => null,
            'aula_id' => $aula->id,
            'causa' => 'QR_NO_VALIDO',
            'reportada_por' => $usuario->id,
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

    public function test_en_dos_examenes_del_aula_elige_el_que_lo_admite(): void
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
            ->assertJsonPath('data.admisible', true)
            ->assertJsonPath('data.examen_id', $examen->id);

        $this->postJson(
            "/api/aulas/{$aula->id}/punto-control/ingresos",
            [
                'tipo' => 'codigo',
                'identificador' => $estudiante->codigo_universitario,
            ],
        )
            ->assertCreated()
            ->assertJsonPath('data.examen_id', $examen->id);

        $this->assertDatabaseHas('ingresos', [
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
        ]);
        $this->assertDatabaseMissing('ingresos', ['examen_id' => $otroExamen->id]);
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

        // Pasada la relectura, otra lectura del mismo ingreso es un intento.
        DB::table('ingresos')->update([
            'registrado_en' => DB::raw("registrado_en - interval '3 minutes'"),
        ]);

        $this->postJson($url, $datos)
            ->assertConflict()
            ->assertJsonPath('data.veredicto', 'YA_INGRESO')
            ->assertJsonPath('data.admitido', false)
            ->assertJsonPath('data.pie', 'Incidencia registrada');

        $this->assertDatabaseCount('ingresos', 1);
        $this->assertDatabaseCount('incidencias', 1);

        $this->assertDatabaseHas('incidencias', [
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_id' => $estudiante->id,
            'reportada_por' => $usuario->id,
            'tipo' => 'INGRESO_REPETIDO',
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
        $this->assertDatabaseCount('incidencias', 1);

        $this->assertDatabaseHas('incidencias', [
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_id' => $estudiante->id,
            'reportada_por' => $usuario->id,
            'causa' => 'NO_HABILITADO',
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
        $this->assertDatabaseHas('incidencias', [
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
            'causa' => 'NO_HABILITADO',
            'detalle' => 'No cumple el requisito.',
        ]);

        // Confirmar enseguida no duplica el mismo intento.
        $this->actingAs($usuario)->postJson(
            "/api/aulas/{$aula->id}/punto-control/ingresos",
            ['tipo' => 'codigo', 'identificador' => $estudiante->codigo_universitario],
        )->assertConflict()
            ->assertJsonPath('data.dato', 'No cumple el requisito.');

        $this->assertDatabaseCount('incidencias', 1);
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
        $this->assertDatabaseHas('incidencias', [
            'examen_id' => null,
            'aula_id' => $aula->id,
            'reportada_por' => $usuario->id,
            'causa' => 'NO_REGISTRADO',
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

        $this->assertDatabaseHas('incidencias', [
            'ingreso_id' => $ingresoId,
            'reportada_por' => $usuario->id,
            'tipo' => 'EXPULSION',
        ]);

        $this->postJson($ruta, ['motivo' => 'Segunda expulsión'])
            ->assertConflict();
        $this->assertSame(1, DB::table('incidencias')->where('tipo', 'EXPULSION')->count());
    }
}
