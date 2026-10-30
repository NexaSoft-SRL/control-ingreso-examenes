<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Modules\Reportes\Application\DTOs\AlcanceReporte;
use App\Modules\Reportes\Application\DTOs\ReporteHorarios;
use App\Modules\Reportes\Infrastructure\Persistence\EloquentReporteGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Modules\Examenes\ArmaExamenes;
use Tests\TestCase;

final class EloquentReporteGatewayTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    public function test_examen_inexistente_devuelve_null(): void
    {
        $gateway = new EloquentReporteGateway;

        $this->assertNull($gateway->examen(0));
    }

    public function test_grupo_inexistente_devuelve_null(): void
    {
        $gateway = new EloquentReporteGateway;

        $this->assertNull($gateway->grupo(0));
    }

    public function test_docente_sin_examenes_devuelve_lista_vacia(): void
    {
        $gateway = new EloquentReporteGateway;

        $this->assertSame([], $gateway->examenesDelDocente(0, null));
    }

    public function test_estudiantes_de_grupo_inexistente_devuelve_lista_vacia(): void
    {
        $gateway = new EloquentReporteGateway;

        $this->assertSame([], $gateway->estudiantesDeGrupo(0));
    }

    public function test_incidencias_de_examen_inexistente_devuelve_lista_vacia(): void
    {
        $gateway = new EloquentReporteGateway;

        $this->assertSame([], $gateway->incidenciasDeExamen(0));
    }

    public function test_asistencia_de_examen_inexistente_devuelve_lista_vacia(): void
    {
        $gateway = new EloquentReporteGateway;

        $this->assertSame([], $gateway->asistencia(0));
    }

    public function test_ausentes_de_examen_inexistente_devuelve_lista_vacia(): void
    {
        $gateway = new EloquentReporteGateway;

        $this->assertSame([], $gateway->ausentes(0));
    }

    public function test_asistencia_y_conteos_con_un_ingreso_real(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_examenes']);
        $docente = $this->docenteConCuenta($usuario);

        $asignatura = $this->asignatura('Cálculo I', '0002040');
        $grupo = $this->grupo($docente, $asignatura);
        $aula = $this->aula('624');

        $estudiantes = $this->estudiantesInscritos($grupo, 1);
        $examen = $this->examen($docente, [$grupo], [$aula]);

        $this->habilitar($examen, $estudiantes, $aula);

        $ingresoId = $this->ingresar(
            $examen,
            $estudiantes[0],
            $aula,
        );

        DB::table('ingresos')
            ->where('id', $ingresoId)
            ->update(['medio_identificacion' => 'codigo']);

        $gateway = new EloquentReporteGateway;
        $alcance = AlcanceReporte::deExamen($examen->id);

        $this->assertSame(1, $gateway->contarExamenes($alcance));
        $this->assertSame(1, $gateway->contarIngresos($alcance));
        $this->assertSame(0, $gateway->contarIntentos($alcance));

        $filas = $gateway->asistencia($examen->id);

        $this->assertCount(1, $filas);
        $this->assertSame(
            $estudiantes[0]->codigo_universitario,
            $filas[0]->codigoUniversitario,
        );
        $this->assertSame('Código SIS', $filas[0]->medio);
        $this->assertFalse($filas[0]->expulsado);
        $this->assertFalse($filas[0]->excepcion);
    }

    public function test_consultas_consolidadas_con_un_examen_real(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_examenes']);
        $docente = $this->docenteConCuenta($usuario);
        $asignatura = $this->asignatura('Cálculo I', '0002040');
        $grupo = $this->grupo($docente, $asignatura);
        $aula = $this->aula('624');
        $estudiantes = $this->estudiantesInscritos($grupo, 1);
        $examen = $this->examen($docente, [$grupo], [$aula]);

        $this->habilitar($examen, $estudiantes, $aula);
        $this->ingresar($examen, $estudiantes[0], $aula);

        $gateway = new EloquentReporteGateway;
        $alcance = AlcanceReporte::deExamen($examen->id);

        $this->assertSame(1, $gateway->contarExamenes($alcance));
        $this->assertSame(1, $gateway->contarIngresos($alcance));
        $this->assertSame(0, $gateway->contarIntentos($alcance));
        $this->assertSame(1, $gateway->contarEstudiantes($alcance));

        $this->assertCount(1, $gateway->examenes($alcance, 1, 8));
        $this->assertNotEmpty($gateway->examenesPorFacultad($alcance));
        $this->assertNotEmpty($gateway->registrados($alcance));
        $this->assertSame([], $gateway->intentos($alcance));
        $this->assertCount(1, $gateway->resumenPorEstudiante($alcance));

        $this->assertNotEmpty($gateway->nombresDelAlcance($alcance));
        $this->assertInstanceOf(ReporteHorarios::class, $gateway->horarios($alcance));
    }

    public function test_un_estudiante_habilitado_que_no_ingresa_figura_como_ausente(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_examenes']);
        $docente = $this->docenteConCuenta($usuario);
        $asignatura = $this->asignatura('Álgebra I', '0002050');
        $grupo = $this->grupo($docente, $asignatura);
        $aula = $this->aula('625');

        $estudiantes = $this->estudiantesInscritos($grupo, 2);
        $examen = $this->examen($docente, [$grupo], [$aula]);

        $this->habilitar($examen, $estudiantes, $aula);
        $this->ingresar($examen, $estudiantes[0], $aula);

        $gateway = new EloquentReporteGateway;
        $alcance = AlcanceReporte::deExamen($examen->id);

        $asistencia = $gateway->asistencia($examen->id);
        $ausentes = $gateway->ausentes($examen->id);

        $this->assertCount(1, $asistencia);
        $this->assertCount(1, $ausentes);
        $this->assertSame(
            $estudiantes[0]->codigo_universitario,
            $asistencia[0]->codigoUniversitario,
        );

        $this->assertSame(1, $gateway->contarIngresos($alcance));
        $this->assertSame(2, $gateway->contarEstudiantes($alcance));

        $horarios = $gateway->horarios($alcance);

        $this->assertSame(1, $horarios->total);
        $this->assertCount(1, $horarios->tramos);
        $this->assertSame(1, $horarios->tramos[0]['cantidad']);
        $this->assertNotNull($horarios->primerIngreso);
        $this->assertSame(
            $horarios->primerIngreso,
            $horarios->ultimoIngreso,
        );
    }

    public function test_expulsion_e_intento_no_autorizado_se_reflejan_en_reportes(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_examenes']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo(
            $docente,
            $this->asignatura('Física General', '0002070')
        );
        $aula = $this->aula('626');
        $estudiantes = $this->estudiantesInscritos($grupo, 2);
        $examen = $this->examen($docente, [$grupo], [$aula]);

        $this->habilitar($examen, $estudiantes, $aula);

        $ingresoId = $this->ingresar(
            $examen,
            $estudiantes[0],
            $aula
        );

        $fecha = now();

        DB::table('incidencias')->insert([
            [
                'examen_id' => $examen->id,
                'aula_id' => $aula->id,
                'estudiante_id' => $estudiantes[0]->id,
                'ingreso_id' => $ingresoId,
                'tipo' => 'EXPULSION',
                'origen' => 'PERSONA',
                'causa' => null,
                'detalle' => 'Incumplimiento de las normas del examen.',
                'ocurrida_en' => $fecha,
                'reportada_por' => $usuario->id,
                'created_at' => $fecha,
                'updated_at' => $fecha,
            ],
            [
                'examen_id' => $examen->id,
                'aula_id' => $aula->id,
                'estudiante_id' => $estudiantes[1]->id,
                'ingreso_id' => null,
                'tipo' => 'INTENTO_NO_AUTORIZADO',
                'origen' => 'SISTEMA',
                'causa' => 'NO_HABILITADO',
                'detalle' => 'Intento rechazado por el punto de control.',
                'ocurrida_en' => $fecha,
                'reportada_por' => null,
                'created_at' => $fecha,
                'updated_at' => $fecha,
            ],
        ]);

        $gateway = new EloquentReporteGateway;
        $alcance = AlcanceReporte::deExamen($examen->id);

        $this->assertSame(1, $gateway->contarIngresos($alcance));
        $this->assertSame(1, $gateway->contarIntentos($alcance));
        $this->assertSame(2, $gateway->contarEstudiantes($alcance));

        $asistencia = $gateway->asistencia($examen->id);
        $this->assertCount(1, $asistencia);
        $this->assertTrue($asistencia[0]->expulsado);

        $intentos = $gateway->intentos($alcance);
        $this->assertCount(1, $intentos);
        $this->assertSame('NO_HABILITADO', $intentos[0]->causa);
        $this->assertSame(
            $estudiantes[1]->codigo_universitario,
            $intentos[0]->codigoUniversitario
        );

        $incidencias = $gateway->incidenciasDeExamen($examen->id);
        $this->assertCount(2, $incidencias);
        $this->assertSame(
            ['EXPULSION', 'INTENTO_NO_AUTORIZADO'],
            array_map(
                static fn ($fila): string => $fila->tipo,
                $incidencias
            )
        );
    }

    public function test_intento_sin_examen_en_aula_compartida_no_duplica_el_consolidado(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_examenes']);
        $docente = $this->docenteConCuenta($usuario);

        $grupoA = $this->grupo(
            $docente,
            $this->asignatura('Cálculo I', '0002040')
        );

        $grupoB = $this->grupo(
            $docente,
            $this->asignatura('Física General', '0002070')
        );

        $aula = $this->aula('624');

        $fecha = now()->toDateString();

        $opciones = [
            'fecha' => $fecha,
            'hora_inicio' => '08:00:00',
            'duracion_minutos' => 120,
        ];

        $examenA = $this->examen(
            $docente,
            [$grupoA],
            [$aula],
            $opciones
        );

        $examenB = $this->examen(
            $docente,
            [$grupoB],
            [$aula],
            $opciones
        );

        $instante = Carbon::parse(
            $fecha.' 08:30:00'
        );

        DB::table('incidencias')->insert([
            'examen_id' => null,
            'aula_id' => $aula->id,
            'estudiante_id' => null,
            'estudiante_texto' => 'Identificador no reconocido',
            'tipo' => 'INTENTO_NO_AUTORIZADO',
            'origen' => 'SISTEMA',
            'causa' => 'NO_REGISTRADO',
            'detalle' => 'Intento sin examen identificado.',
            'ocurrida_en' => $instante,
            'created_at' => $instante,
            'updated_at' => $instante,
        ]);

        $gateway = new EloquentReporteGateway;

        $alcanceGeneral = new AlcanceReporte;
        $alcanceA = AlcanceReporte::deExamen($examenA->id);
        $alcanceB = AlcanceReporte::deExamen($examenB->id);

        $this->assertSame(
            2,
            $gateway->contarExamenes($alcanceGeneral)
        );

        $this->assertSame(
            1,
            $gateway->contarIntentos($alcanceGeneral)
        );

        $this->assertSame(
            1,
            $gateway->contarIntentos($alcanceA)
        );

        $this->assertSame(
            1,
            $gateway->contarIntentos($alcanceB)
        );

        $this->assertCount(
            1,
            $gateway->intentos($alcanceGeneral)
        );

        $incidenciasA = $gateway->incidenciasDeExamen($examenA->id);
        $incidenciasB = $gateway->incidenciasDeExamen($examenB->id);

        $this->assertCount(1, $incidenciasA);
        $this->assertCount(1, $incidenciasB);

        $this->assertSame(
            'INTENTO_NO_AUTORIZADO',
            $incidenciasA[0]->tipo
        );

        $this->assertSame(
            'INTENTO_NO_AUTORIZADO',
            $incidenciasB[0]->tipo
        );

        $this->assertSame(
            'Identificador no reconocido',
            $incidenciasA[0]->estudiante
        );
    }

    public function test_filtros_de_alcance_y_prioridad_del_examen_seleccionado(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_examenes']);
        $docente = $this->docenteConCuenta($usuario);

        $grupoA = $this->grupo(
            $docente,
            $this->asignatura('Cálculo I', '0002040')
        );
        $grupoB = $this->grupo(
            $docente,
            $this->asignatura('Física General', '0002070')
        );

        $fechaA = now()->addDays(2)->toDateString();
        $fechaB = now()->addDays(3)->toDateString();

        $examenA = $this->examen($docente, [$grupoA], [], [
            'fecha' => $fechaA,
            'hora_inicio' => '08:00:00',
        ]);

        $examenB = $this->examen($docente, [$grupoB], [], [
            'fecha' => $fechaB,
            'hora_inicio' => '10:00:00',
        ]);

        $gateway = new EloquentReporteGateway;

        $general = new AlcanceReporte;
        $this->assertSame(2, $gateway->contarExamenes($general));

        $porFecha = new AlcanceReporte(
            desde: $fechaA,
            hasta: $fechaA,
        );
        $this->assertSame(1, $gateway->contarExamenes($porFecha));
        $this->assertSame(
            $examenA->id,
            $gateway->examenes($porFecha, 1, 8)[0]->id,
        );

        $porBusqueda = new AlcanceReporte(buscar: 'Física');
        $this->assertSame(1, $gateway->contarExamenes($porBusqueda));
        $this->assertSame(
            $examenB->id,
            $gateway->examenes($porBusqueda, 1, 8)[0]->id,
        );

        $this->assertSame(
            0,
            $gateway->contarExamenes(
                new AlcanceReporte(periodoId: 999999999)
            ),
        );

        $claveFacultad = DB::table('facultades')
            ->where('id', $grupoA->facultad_id)
            ->value('clave');

        $this->assertIsString($claveFacultad);

        $porFacultad = new AlcanceReporte(
            facultad: $claveFacultad,
        );

        $this->assertSame(
            2,
            $gateway->contarExamenes($porFacultad),
        );

        $this->assertSame(
            0,
            $gateway->contarExamenes(
                new AlcanceReporte(facultad: 'facultad_inexistente')
            ),
        );

        // El examen elegido tiene prioridad sobre filtros contradictorios.
        $seleccionado = new AlcanceReporte(
            periodoId: 999999999,
            facultad: 'facultad_inexistente',
            buscar: 'Asignatura inexistente',
            desde: '2099-01-01',
            hasta: '2099-12-31',
            examenId: $examenA->id,
        );

        $this->assertSame(
            1,
            $gateway->contarExamenes($seleccionado),
        );
        $this->assertSame(
            $examenA->id,
            $gateway->examenes($seleccionado, 1, 8)[0]->id,
        );

        $this->assertSame(
            0,
            $gateway->contarExamenes($seleccionado->sinExamen()),
        );
    }

    public function test_ingreso_repetido_no_cuenta_como_intento_rechazado(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_examenes']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo($docente, $this->asignatura('Álgebra I'));
        $aula = $this->aula('701');
        $estudiantes = $this->estudiantesInscritos($grupo, 1);
        $examen = $this->examen($docente, [$grupo], [$aula]);

        $this->habilitar($examen, $estudiantes, $aula);
        $ingresoId = $this->ingresar($examen, $estudiantes[0], $aula);

        DB::table('incidencias')->insert([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_id' => $estudiantes[0]->id,
            'ingreso_id' => $ingresoId,
            'tipo' => 'INGRESO_REPETIDO',
            'origen' => 'SISTEMA',
            'detalle' => 'Ingreso repetido detectado por la puerta.',
            'ocurrida_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $gateway = new EloquentReporteGateway;
        $alcance = AlcanceReporte::deExamen($examen->id);

        $this->assertSame(0, $gateway->contarIntentos($alcance));
        $this->assertSame([], $gateway->intentos($alcance));
        $this->assertCount(1, $gateway->incidenciasDeExamen($examen->id));

        $resumen = $gateway->resumenPorEstudiante($alcance);

        $this->assertCount(1, $resumen);
        $this->assertSame(
            $estudiantes[0]->codigo_universitario,
            $resumen[0]->codigoUniversitario
        );
        $this->assertSame(0, $resumen[0]->intentos);
        $this->assertSame(1, $resumen[0]->ingresos);
    }

    public function test_no_inscrito_no_inventa_examen_correspondiente_historico(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_examenes']);
        $docente = $this->docenteConCuenta($usuario);

        $grupoA = $this->grupo(
            $docente,
            $this->asignatura('Cálculo I')
        );
        $grupoB = $this->grupo(
            $docente,
            $this->asignatura('Física General')
        );

        $aulaA = $this->aula('702');
        $aulaB = $this->aula('703');
        $estudiantes = $this->estudiantesInscritos($grupoB, 1);

        $fecha = now()->toDateString();

        $examenA = $this->examen($docente, [$grupoA], [$aulaA], [
            'fecha' => $fecha,
            'hora_inicio' => '08:00:00',
        ]);

        $examenB = $this->examen($docente, [$grupoB], [$aulaB], [
            'fecha' => $fecha,
            'hora_inicio' => '10:00:00',
        ]);

        DB::table('incidencias')->insert([
            'examen_id' => $examenA->id,
            'aula_id' => $aulaA->id,
            'estudiante_id' => $estudiantes[0]->id,
            'tipo' => 'INTENTO_NO_AUTORIZADO',
            'origen' => 'SISTEMA',
            'causa' => 'NO_INSCRITO',
            'detalle' => 'No inscrito en el examen del aula.',
            'ocurrida_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $gateway = new EloquentReporteGateway;
        $alcance = AlcanceReporte::deExamen($examenA->id);

        $this->assertNotSame($examenA->id, $examenB->id);
        $this->assertSame(1, $gateway->contarIntentos($alcance));

        $intentos = $gateway->intentos($alcance);

        $this->assertCount(1, $intentos);
        $this->assertSame('NO_INSCRITO', $intentos[0]->causa);
        $this->assertNull($intentos[0]->examenCorrespondiente);
        $this->assertSame(
            $estudiantes[0]->codigo_universitario,
            $intentos[0]->codigoUniversitario
        );
    }

    public function test_docente_y_grupo_devuelven_cifras_reales(): void
    {
        $usuario = $this->usuarioConPermisos(['reportes_examenes']);
        $docente = $this->docenteConCuenta($usuario);
        $grupo = $this->grupo(
            $docente,
            $this->asignatura('Programación I')
        );
        $aula = $this->aula('704');
        $estudiantes = $this->estudiantesInscritos($grupo, 3);

        $examen = $this->examen($docente, [$grupo], [$aula]);

        $this->habilitar($examen, array_slice($estudiantes, 0, 2), $aula);
        $this->inhabilitar($examen, [$estudiantes[2]]);
        $this->ingresar($examen, $estudiantes[0], $aula);

        DB::table('incidencias')->insert([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_id' => $estudiantes[1]->id,
            'tipo' => 'MATERIAL_NO_PERMITIDO',
            'origen' => 'PERSONA',
            'detalle' => 'Material no permitido durante el examen.',
            'ocurrida_en' => now(),
            'reportada_por' => $usuario->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $gateway = new EloquentReporteGateway;

        $filas = $gateway->examenesDelDocente(
            $usuario->id,
            $examen->periodo_id
        );

        $this->assertCount(1, $filas);
        $this->assertSame($examen->id, $filas[0]->id);
        $this->assertSame(1, $filas[0]->presentes);
        $this->assertSame(2, $filas[0]->habilitados);
        $this->assertSame(1, $filas[0]->noHabilitados);
        $this->assertSame(1, $filas[0]->ausentes);
        $this->assertSame(1, $filas[0]->incidencias);
        $this->assertSame(50, $filas[0]->asistencia());

        $datosGrupo = $gateway->grupo($grupo->id);

        $this->assertNotNull($datosGrupo);
        $this->assertSame($grupo->id, $datosGrupo->id);

        $inscritos = $gateway->estudiantesDeGrupo($grupo->id);

        $this->assertCount(3, $inscritos);
        $this->assertEqualsCanonicalizing(
            array_map(
                static fn ($estudiante): string => $estudiante->codigo_universitario,
                $estudiantes
            ),
            array_map(
                static fn ($fila): string => $fila->codigoUniversitario,
                $inscritos
            )
        );
    }
}
