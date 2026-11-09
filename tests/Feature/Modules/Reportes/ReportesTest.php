<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use App\Modules\Academico\Domain\Models\Carrera;
use App\Modules\Academico\Domain\Models\Periodo;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Estudiantes\Domain\Models\Inscripcion;
use App\Modules\Examenes\Domain\Enums\TipoExamen;
use App\Modules\Ingreso\Domain\Models\Ingreso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Los reportes del administrador (toda la universidad) y del docente (sus
 * examenes y sus grupos): que examenes abarcan, que cifras dan y que filas
 * llevan. La forma del archivo se prueba en `ExportacionTest`.
 */
final class ReportesTest extends TestCase
{
    use EscenarioDeReportes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::AHORA);
    }

    // --- Ruta 73: examenes de la universidad ---------------------------

    public function test_the_administrator_lists_the_exams_with_their_figures(): void
    {
        $docente = $this->docente('Blanco Coca Leticia');
        $grupo = $this->grupo($docente, $this->asignatura('Introducción a la Programación'));
        $aulas = [$this->aula('691A'), $this->aula('691B')];
        $examen = $this->examen($docente, [$grupo], $aulas);

        $inscritos = $this->estudiantesInscritos($grupo, 4);
        $this->habilitar($examen, array_slice($inscritos, 0, 3), $aulas[0]);
        $this->inhabilitar($examen, [$inscritos[3]]);
        $this->ingresar($examen, $inscritos[0], $aulas[0]);

        $this->actingAs($this->administrador())
            ->getJson('/api/reportes/examenes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertExactJson([
                'data' => [[
                    'id' => $examen->id,
                    'asignatura' => 'Introducción a la Programación',
                    'tipo' => 'Primer parcial',
                    'docente' => 'Blanco Coca Leticia',
                    'facultad' => 'FCyT',
                    'fecha' => self::HOY,
                    'hora' => '08:15',
                    'aulas' => 2,
                    'ingresados' => 1,
                    'habilitados' => 3,
                ]],
                'meta' => [
                    'total' => 1,
                    'pagina' => 1,
                    'por_pagina' => 10,
                    'conteos' => ['todas' => 1, 'FCyT' => 1],
                    'cifras' => ['ingresos' => 1, 'examenes' => 1, 'intentos' => 0, 'estudiantes' => 4],
                ],
            ]);
    }

    public function test_the_exams_are_ordered_by_date_and_time_and_paginated(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        $tarde = $this->examen($docente, [$grupo], [], ['hora_inicio' => '14:15:00']);
        $manana = $this->examen($docente, [$grupo], [], ['tipo' => TipoExamen::Final, 'hora_inicio' => '06:45:00']);
        $antes = $this->examen($docente, [$grupo], [], ['tipo' => TipoExamen::SegundoParcial, 'fecha' => '2026-10-05']);

        $administrador = $this->administrador();

        $this->actingAs($administrador)
            ->getJson('/api/reportes/examenes?por_pagina=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $antes->id)
            ->assertJsonPath('data.1.id', $manana->id)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.por_pagina', 2);

        $this->actingAs($administrador)
            ->getJson('/api/reportes/examenes?por_pagina=2&pagina=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tarde->id)
            ->assertJsonPath('meta.pagina', 2);
    }

    public function test_the_faculty_filter_narrows_the_list_but_not_the_counts(): void
    {
        $docente = $this->docente();
        $fcyt = $this->examen($docente, [$this->grupo($docente)]);
        $fce = $this->examen($docente, [$this->grupo($docente, facultad: $this->facultad('fce'))]);
        $this->facultad('fach');

        $administrador = $this->administrador();

        $this->actingAs($administrador)
            ->getJson('/api/reportes/examenes?facultad=fce')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $fce->id)
            ->assertJsonPath('data.0.facultad', 'FCE')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.conteos', ['todas' => 2, 'FCyT' => 1, 'FCE' => 1, 'FACH' => 0])
            ->assertJsonPath('meta.cifras.examenes', 1);

        // La sigla, como la muestra la pantalla, vale igual que la clave.
        $this->actingAs($administrador)
            ->getJson('/api/reportes/examenes?facultad=FCyT')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $fcyt->id);
    }

    public function test_the_search_matches_subject_and_teacher_without_accents(): void
    {
        $leticia = $this->docente('Blanco Coca Leticia');
        $fidel = $this->docente('Taborga Acha Fidel');

        $programacion = $this->examen($leticia, [
            $this->grupo($leticia, $this->asignatura('Introducción a la Programación')),
        ]);
        $calculo = $this->examen($fidel, [
            $this->grupo($fidel, $this->asignatura('Cálculo I')),
        ]);

        $administrador = $this->administrador();

        $this->actingAs($administrador)
            ->getJson('/api/reportes/examenes?buscar=PROGRAMACION')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $programacion->id);

        $this->actingAs($administrador)
            ->getJson('/api/reportes/examenes?buscar=taborga')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $calculo->id)
            ->assertJsonPath('meta.conteos.todas', 1);

        $this->actingAs($administrador)
            ->getJson('/api/reportes/examenes?buscar=100%25')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_list_admits_a_range_of_dates_and_a_period(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        $octubre = $this->examen($docente, [$grupo]);
        $noviembre = $this->examen($docente, [$grupo], [], ['tipo' => TipoExamen::Final, 'fecha' => '2026-11-20']);

        $anterior = Periodo::create([
            'codigo' => '1/2026',
            'anio' => 2026,
            'numero' => 1,
            'tipo' => TipoPeriodo::Semestre1,
            'fecha_inicio' => '2026-02-01',
            'fecha_fin' => '2026-07-01',
        ]);
        $pasado = $this->examen($docente, [$this->grupo($docente, periodo: $anterior)], [], ['fecha' => '2026-04-10']);

        $administrador = $this->administrador();

        $this->actingAs($administrador)
            ->getJson('/api/reportes/examenes?desde=2026-11-01&hasta=2026-11-30')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $noviembre->id);

        $this->actingAs($administrador)
            ->getJson('/api/reportes/examenes?hasta=2026-10-12')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $pasado->id)
            ->assertJsonPath('data.1.id', $octubre->id);

        $this->actingAs($administrador)
            ->getJson("/api/reportes/examenes?periodo={$anterior->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pasado->id);
    }

    public function test_the_figures_cover_the_whole_scope_and_not_only_the_page(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $aula = $this->aula();
        $inscritos = $this->estudiantesInscritos($grupo, 3);

        $primero = $this->examen($docente, [$grupo], [$aula]);
        $segundo = $this->examen($docente, [$grupo], [$aula], ['tipo' => TipoExamen::Final, 'hora_inicio' => '14:15:00']);

        $this->habilitar($primero, $inscritos, $aula);
        $this->habilitar($segundo, $inscritos, $aula);
        $this->ingresar($primero, $inscritos[0], $aula);
        $this->ingresar($primero, $inscritos[1], $aula);
        $this->ingresar($segundo, $inscritos[0], $aula);
        $this->intento($segundo, $aula, null, 'CODIGO_INVALIDO');

        $this->actingAs($this->administrador())
            ->getJson('/api/reportes/examenes?por_pagina=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ingresados', 2)
            ->assertJsonPath('meta.cifras', ['ingresos' => 3, 'examenes' => 2, 'intentos' => 1, 'estudiantes' => 3]);
    }

    public function test_choosing_an_exam_narrows_the_figures_and_keeps_the_list(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $aula = $this->aula();
        $inscritos = $this->estudiantesInscritos($grupo, 2);

        $primero = $this->examen($docente, [$grupo], [$aula]);
        $segundo = $this->examen($docente, [$grupo], [$aula], ['tipo' => TipoExamen::Final, 'fecha' => '2026-12-01']);

        $this->habilitar($primero, $inscritos, $aula);
        $this->ingresar($primero, $inscritos[0], $aula);
        $this->ingresar($primero, $inscritos[1], $aula);
        $this->intento($primero, $aula, null, 'CODIGO_INVALIDO');

        // El examen elegido manda sobre los demas filtros en las cifras.
        $this->actingAs($this->administrador())
            ->getJson("/api/reportes/examenes?desde=2026-10-01&hasta=2026-10-31&examen={$segundo->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $primero->id)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.cifras', ['ingresos' => 0, 'examenes' => 1, 'intentos' => 0, 'estudiantes' => 2]);
    }

    public function test_the_filters_of_the_list_are_validated(): void
    {
        $administrador = $this->administrador();

        $this->actingAs($administrador)
            ->getJson('/api/reportes/examenes?desde=2026-11-30&hasta=2026-11-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hasta' => 'La fecha final no puede ser anterior a la inicial.']);

        $this->actingAs($administrador)
            ->getJson('/api/reportes/examenes?facultad=medicina&periodo=999&examen=999&por_pagina=101&pagina=0&desde=ayer')
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'facultad' => 'La facultad no existe.',
                'periodo' => 'El período no existe.',
                'examen' => 'El examen no existe.',
                'por_pagina' => 'El tamaño de página no puede pasar de 100.',
                'pagina' => 'La página debe ser 1 o mayor.',
                'desde' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            ]);
    }

    public function test_the_list_of_the_university_demands_a_session_and_its_permission(): void
    {
        $this->getJson('/api/reportes/examenes')->assertUnauthorized();

        $this->actingAs($this->cuentaDe($this->docente()))
            ->getJson('/api/reportes/examenes')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'reportes_universidad');

        $this->actingAs($this->usuarioConRol('Auxiliar'))
            ->getJson('/api/reportes/examenes')
            ->assertForbidden();
    }

    // --- Ruta 77: examenes del docente ---------------------------------

    public function test_the_teacher_lists_the_exams_with_their_attendance(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura('Introducción a la Programación');
        $grupo = $this->grupo($docente, $asignatura);
        $aula = $this->aula();
        $examen = $this->examen($docente, [$grupo], [$aula]);

        $inscritos = $this->estudiantesInscritos($grupo, 6);
        $this->habilitar($examen, array_slice($inscritos, 0, 4), $aula);
        $this->inhabilitar($examen, [$inscritos[4]]);
        $this->ingresar($examen, $inscritos[0], $aula);
        $this->ingresar($examen, $inscritos[1], $aula);
        $this->ingresar($examen, $inscritos[2], $aula);
        // Un rechazo con examen y otro que la puerta no pudo atribuir.
        $this->intento($examen, $aula, $inscritos[4], 'NO_HABILITADO');
        $this->intento(null, $aula, null, 'CODIGO_INVALIDO', null, null, 'QR ilegible');

        $this->actingAs($this->cuentaDe($docente))
            ->getJson('/api/reportes/mis-examenes')
            ->assertOk()
            ->assertExactJson([
                'data' => [[
                    'id' => $examen->id,
                    'asignatura' => ['id' => $asignatura->id, 'nombre' => 'Introducción a la Programación'],
                    'tipo' => 'Primer parcial',
                    'fecha' => self::HOY,
                    'hora' => '08:15',
                    'rendido' => true,
                    'presentes' => 3,
                    'habilitados' => 4,
                    'no_habilitados' => 1,
                    'ausentes' => 1,
                    'asistencia' => 75,
                    'incidencias' => 2,
                ]],
                'meta' => [
                    'total' => 1,
                    'pagina' => 1,
                    'por_pagina' => 8,
                    'examenes' => 1,
                    'rendidos' => 1,
                    'asignaturas' => [
                        ['id' => $asignatura->id, 'nombre' => 'Introducción a la Programación', 'examenes' => 1],
                    ],
                ],
            ]);
    }

    public function test_an_exam_that_was_not_taken_yet_has_no_attendance_figures(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $examen = $this->examen($docente, [$grupo], [], ['fecha' => '2026-10-13']);

        $inscritos = $this->estudiantesInscritos($grupo, 3);
        $this->habilitar($examen, [$inscritos[0], $inscritos[1]]);
        $this->inhabilitar($examen, [$inscritos[2]]);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson('/api/reportes/mis-examenes')
            ->assertOk()
            ->assertJsonPath('data.0.rendido', false)
            ->assertJsonPath('data.0.presentes', null)
            ->assertJsonPath('data.0.ausentes', null)
            ->assertJsonPath('data.0.asistencia', null)
            ->assertJsonPath('data.0.habilitados', 2)
            ->assertJsonPath('data.0.no_habilitados', 1)
            ->assertJsonPath('meta.rendidos', 0);
    }

    public function test_an_exam_without_enabled_students_has_zero_attendance(): void
    {
        $docente = $this->docente();
        $this->examen($docente, [$this->grupo($docente)]);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson('/api/reportes/mis-examenes')
            ->assertOk()
            ->assertJsonPath('data.0.presentes', 0)
            ->assertJsonPath('data.0.asistencia', 0);
    }

    public function test_the_teacher_sees_the_exams_of_the_groups_but_not_those_of_others(): void
    {
        $leticia = $this->docente();
        $fidel = $this->docente();
        $asignatura = $this->asignatura();

        $conjunto = $this->examen($fidel, [
            $this->grupo($fidel, $asignatura),
            $this->grupo($leticia, $asignatura),
        ]);
        $this->examen($fidel, [$this->grupo($fidel)], [], ['tipo' => TipoExamen::Final]);

        $this->actingAs($this->cuentaDe($leticia))
            ->getJson('/api/reportes/mis-examenes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conjunto->id);

        $this->actingAs($this->cuentaDe($fidel))
            ->getJson('/api/reportes/mis-examenes')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_the_exams_of_the_teacher_are_filtered_by_subject_and_paginated(): void
    {
        $docente = $this->docente();
        $algebra = $this->asignatura('Álgebra I');
        $calculo = $this->asignatura('Cálculo I');
        $grupoAlgebra = $this->grupo($docente, $algebra);

        $parcial = $this->examen($docente, [$grupoAlgebra], [], ['fecha' => '2026-10-01']);
        $final = $this->examen($docente, [$grupoAlgebra], [], ['tipo' => TipoExamen::Final, 'fecha' => '2026-11-25']);
        $this->examen($docente, [$this->grupo($docente, $calculo)]);

        $cuenta = $this->cuentaDe($docente);

        $this->actingAs($cuenta)
            ->getJson("/api/reportes/mis-examenes?asignatura={$algebra->id}&por_pagina=1&pagina=2")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $final->id)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.pagina', 2)
            ->assertJsonPath('meta.por_pagina', 1)
            // Los conteos de las fichas no siguen al filtro de asignatura.
            ->assertJsonPath('meta.examenes', 3)
            ->assertJsonPath('meta.rendidos', 2)
            ->assertJsonPath('meta.asignaturas', [
                ['id' => $algebra->id, 'nombre' => 'Álgebra I', 'examenes' => 2],
                ['id' => $calculo->id, 'nombre' => 'Cálculo I', 'examenes' => 1],
            ]);

        $this->actingAs($cuenta)
            ->getJson('/api/reportes/mis-examenes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $parcial->id);
    }

    public function test_without_a_period_the_teacher_sees_the_exams_of_the_current_ones(): void
    {
        $docente = $this->docente();
        $vigente = $this->examen($docente, [$this->grupo($docente)]);

        $cerrado = Periodo::create([
            'codigo' => '1/2026',
            'anio' => 2026,
            'numero' => 1,
            'tipo' => TipoPeriodo::Semestre1,
            'fecha_inicio' => '2026-02-01',
            'fecha_fin' => '2026-07-01',
        ]);
        $pasado = $this->examen($docente, [$this->grupo($docente, periodo: $cerrado)], [], ['fecha' => '2026-04-10']);

        $cuenta = $this->cuentaDe($docente);

        $this->actingAs($cuenta)
            ->getJson('/api/reportes/mis-examenes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $vigente->id);

        $this->actingAs($cuenta)
            ->getJson("/api/reportes/mis-examenes?periodo={$cerrado->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pasado->id);
    }

    public function test_the_exams_of_the_teacher_validate_their_filters_and_demand_the_permission(): void
    {
        $this->getJson('/api/reportes/mis-examenes')->assertUnauthorized();

        $this->actingAs($this->administrador())
            ->getJson('/api/reportes/mis-examenes')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'reportes_examenes');

        $this->actingAs($this->cuentaDe($this->docente()))
            ->getJson('/api/reportes/mis-examenes?periodo=999&asignatura=x&por_pagina=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'periodo' => 'El período no existe.',
                'asignatura' => 'La asignatura no es válida.',
                'por_pagina' => 'El tamaño de página debe ser 1 o mayor.',
            ]);
    }

    // --- Rutas 74 a 76: contenido de los reportes de la universidad ----

    public function test_the_schedule_report_groups_the_entries_in_quarters_of_an_hour(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $aula = $this->aula();
        $examen = $this->examen($docente, [$grupo], [$aula]);
        $inscritos = $this->estudiantesInscritos($grupo, 4);
        $this->habilitar($examen, $inscritos, $aula);

        foreach (['07:58:10', '08:01:00', '08:14:59', '08:15:00'] as $i => $hora) {
            $this->travelTo(self::HOY.' '.$hora);
            $this->ingresar($examen, $inscritos[$i], $aula);
        }

        $this->travelTo(self::AHORA);

        $hoja = $this->hoja(
            $this->actingAs($this->administrador())->get('/api/reportes/horarios/exportar?formato=xlsx')
        );

        $this->assertSame(
            [['12 oct 2026', '07:45', '1'], ['12 oct 2026', '08:00', '2'], ['12 oct 2026', '08:15', '1']],
            $this->datos($hoja, 'Día'),
        );

        $texto = $this->texto($hoja);

        $this->assertStringContainsString('Reporte de horarios de ingreso', $texto);
        $this->assertStringContainsString('Primer ingreso: 12 oct 2026, 07:58.', $texto);
        $this->assertStringContainsString('Último ingreso: 12 oct 2026, 08:15.', $texto);
        $this->assertStringContainsString('4 ingresos en total.', $texto);
    }

    public function test_the_schedule_report_says_when_nobody_entered(): void
    {
        $docente = $this->docente();
        $this->examen($docente, [$this->grupo($docente)]);

        $hoja = $this->hoja(
            $this->actingAs($this->administrador())->get('/api/reportes/horarios/exportar?formato=xlsx')
        );

        $this->assertSame([], $this->datos($hoja, 'Tramo de 15 min'));
        $this->assertStringContainsString('Primer ingreso: ninguno.', $this->texto($hoja));
        $this->assertStringContainsString('0 ingresos en total.', $this->texto($hoja));
    }

    public function test_the_registered_report_has_a_row_by_exam_and_room(): void
    {
        $docente = $this->docente('Blanco Coca Leticia');
        $grupo = $this->grupo($docente, $this->asignatura('Álgebra I'));
        $a = $this->aula('691A');
        $b = $this->aula('691B');
        $examen = $this->examen($docente, [$grupo], [$b, $a]);

        $inscritos = $this->estudiantesInscritos($grupo, 6);
        $this->habilitar($examen, [$inscritos[0], $inscritos[1]], $a);
        $this->habilitar($examen, [$inscritos[2]], $b);
        $this->habilitar($examen, [$inscritos[3]]);
        $this->inhabilitar($examen, [$inscritos[4]]);
        $this->ingresar($examen, $inscritos[0], $a);
        $this->ingresar($examen, $inscritos[2], $b);

        // Un examen sin aulas ni habilitados figura igual, en cero.
        $this->examen($docente, [$grupo], [], ['tipo' => TipoExamen::Final, 'fecha' => '2026-11-25']);

        $hoja = $this->hoja(
            $this->actingAs($this->administrador())->get('/api/reportes/registrados/exportar?formato=xlsx')
        );

        $this->assertSame(
            [
                ['12 oct 2026', 'Álgebra I · Primer parcial', 'FCyT', 'Blanco Coca Leticia', '691A', '2', '1'],
                ['12 oct 2026', 'Álgebra I · Primer parcial', 'FCyT', 'Blanco Coca Leticia', '691B', '1', '1'],
                ['12 oct 2026', 'Álgebra I · Primer parcial', 'FCyT', 'Blanco Coca Leticia', 'Sin aula', '1', '0'],
                ['25 nov 2026', 'Álgebra I · Examen final', 'FCyT', 'Blanco Coca Leticia', 'Sin aula', '0', '0'],
            ],
            $this->datos($hoja, 'Fecha'),
        );

        $texto = $this->texto($hoja);

        $this->assertStringContainsString('2 exámenes.', $texto);
        $this->assertStringContainsString('4 habilitados.', $texto);
        $this->assertStringContainsString('2 ingresos.', $texto);
    }

    public function test_the_registered_report_counts_an_entry_by_the_door_it_used(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $asignada = $this->aula('617');
        $otra = $this->aula('622');
        $examen = $this->examen($docente, [$grupo], [$asignada, $otra]);

        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $this->habilitar($examen, [$estudiante], $asignada);

        // Entro por otra aula, con un cambio de aula autorizado.
        Ingreso::create([
            'examen_id' => $examen->id,
            'estudiante_id' => $estudiante->id,
            'aula_id' => $otra->id,
            'medio' => 'QR',
            'es_excepcion' => true,
            'registrado_en' => now(),
            'registrado_por' => $examen->creado_por,
        ]);

        $filas = $this->datos(
            $this->hoja(
                $this->actingAs($this->administrador())->get('/api/reportes/registrados/exportar?formato=xlsx')
            ),
            'Fecha',
        );

        $this->assertSame(['617', '1', '0'], array_slice($filas[0], 4));
        $this->assertSame(['622', '0', '1'], array_slice($filas[1], 4));
    }

    public function test_the_reports_of_the_university_follow_the_filters_or_the_chosen_exam(): void
    {
        $docente = $this->docente();
        $aula = $this->aula();

        $grupoFcyt = $this->grupo($docente, $this->asignatura('Física I'));
        $grupoFce = $this->grupo($docente, $this->asignatura('Contabilidad'), facultad: $this->facultad('fce'));

        $fcyt = $this->examen($docente, [$grupoFcyt], [$aula]);
        $fce = $this->examen($docente, [$grupoFce], [$aula], ['fecha' => '2026-10-20']);

        $administrador = $this->administrador();

        $examenes = fn (string $consulta): array => array_column(
            $this->datos(
                $this->hoja($this->actingAs($administrador)->get("/api/reportes/registrados/exportar?formato=xlsx&{$consulta}")),
                'Fecha',
            ),
            1,
        );

        $this->assertSame(['Contabilidad · Primer parcial'], $examenes('facultad=fce'));
        $this->assertSame(['Física I · Primer parcial'], $examenes('hasta=2026-10-15'));
        $this->assertSame(['Contabilidad · Primer parcial'], $examenes('buscar=contab'));
        $this->assertSame(['Física I · Primer parcial'], $examenes("examen={$fcyt->id}"));
        // El examen elegido manda sobre los demas filtros.
        $this->assertSame(['Contabilidad · Primer parcial'], $examenes("facultad=fcyt&examen={$fce->id}"));
        $this->assertCount(2, $examenes(''));
    }

    public function test_the_attempts_report_says_the_reason_and_the_exam_that_corresponded(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente, $this->asignatura('Álgebra I'));
        $otroGrupo = $this->grupo($docente, $this->asignatura('Cálculo I'));
        $aula = $this->aula('691A');

        $examen = $this->examen($docente, [$grupo], [$aula]);
        $otro = $this->examen($docente, [$otroGrupo], [$this->aula('692')]);

        [$ajeno] = $this->estudiantesInscritos($otroGrupo, 1);
        [$propio] = $this->estudiantesInscritos($grupo, 1);

        $this->intento($examen, $aula, $ajeno, 'NO_INSCRITO', $otro, '2026-10-12 08:05:00');
        $this->intento($examen, $aula, $propio, 'NO_HABILITADO', null, '2026-10-12 08:10:00');
        $this->intento($examen, $aula, null, 'CODIGO_INVALIDO', null, '2026-10-12 08:12:00', 'QR ilegible');
        // El ingreso repetido es una incidencia, no un intento rechazado.
        DB::table('incidencias')->insert([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_id' => $propio->id,
            'tipo' => 'INGRESO_REPETIDO',
            'origen' => 'SISTEMA',
            'detalle' => 'Código QR ya usado.',
            'ocurrida_en' => '2026-10-12 08:14:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hoja = $this->hoja(
            $this->actingAs($this->administrador())
                ->get("/api/reportes/intentos/exportar?formato=xlsx&examen={$examen->id}")
        );

        $this->assertSame(
            [
                [
                    '12 oct 2026, 08:05', $ajeno->codigo_universitario, 'Apellido 1 Nombre 1', '691A',
                    'Álgebra I · Primer parcial', 'Cálculo I · Primer parcial', 'No inscrito en la materia',
                ],
                [
                    '12 oct 2026, 08:10', $propio->codigo_universitario, 'Apellido 2 Nombre 2', '691A',
                    'Álgebra I · Primer parcial', 'Ninguno a esa hora', 'No habilitado',
                ],
                [
                    '12 oct 2026, 08:12', '', 'QR ilegible', '691A',
                    'Álgebra I · Primer parcial', 'Ninguno a esa hora', 'Código no válido',
                ],
            ],
            $this->datos($hoja, 'Hora'),
        );

        $this->assertContains(
            [
                'Hora', 'Código SIS', 'Estudiante', 'Aula',
                'Examen al que intentó ingresar', 'Examen que le correspondía', 'Motivo',
            ],
            $hoja,
        );

        $this->assertStringContainsString('3 intentos rechazados.', $this->texto($hoja));
    }

    public function test_an_attempt_without_exam_is_attributed_by_room_and_window(): void
    {
        $docente = $this->docente();
        $aula = $this->aula('691A');
        $otraAula = $this->aula('692');

        // 08:15 a 09:45, finalizado a las 09:45; la puerta abre a las 07:15.
        $examen = $this->examen($docente, [$this->grupo($docente)], [$aula]);
        $examen->forceFill(['finalizado_en' => '2026-10-12 09:45:00'])->save();
        $this->examen($docente, [$this->grupo($docente)], [$otraAula])
            ->forceFill(['finalizado_en' => '2026-10-12 09:45:00'])->save();

        $this->intento(null, $aula, null, 'CODIGO_INVALIDO', null, '2026-10-12 07:15:00', 'al abrir');
        $this->intento(null, $aula, null, 'CODIGO_INVALIDO', null, '2026-10-12 09:45:00', 'al cerrar');
        $this->intento(null, $aula, null, 'CODIGO_INVALIDO', null, '2026-10-12 07:14:59', 'antes de abrir');
        $this->intento(null, $aula, null, 'CODIGO_INVALIDO', null, '2026-10-12 09:45:01', 'ya cerrado');
        $this->intento(null, $aula, null, 'CODIGO_INVALIDO', null, '2026-10-13 08:30:00', 'otro día');
        $this->intento(null, $otraAula, null, 'CODIGO_INVALIDO', null, '2026-10-12 08:30:00', 'otra aula');

        $administrador = $this->administrador();

        $filas = $this->datos(
            $this->hoja(
                $this->actingAs($administrador)->get("/api/reportes/intentos/exportar?formato=xlsx&examen={$examen->id}")
            ),
            'Hora',
        );

        $this->assertSame(['al abrir', 'al cerrar'], array_column($filas, 2));

        $this->actingAs($administrador)
            ->getJson("/api/reportes/examenes?examen={$examen->id}")
            ->assertOk()
            ->assertJsonPath('meta.cifras.intentos', 2);

        // Con las dos aulas en el alcance entra tambien el de la otra.
        $this->actingAs($administrador)
            ->getJson('/api/reportes/examenes')
            ->assertOk()
            ->assertJsonPath('meta.cifras.intentos', 3);
    }

    public function test_an_attempt_without_exam_names_the_exams_of_the_room_at_that_time(): void
    {
        $docente = $this->docente();
        $aula = $this->aula('691A');

        // Dos examenes comparten el aula a la misma hora; un tercero la usa a la tarde.
        $algebra = $this->examen($docente, [$this->grupo($docente, $this->asignatura('Álgebra I'))], [$aula]);
        $this->examen(
            $docente,
            [$this->grupo($docente, $this->asignatura('Cálculo I'))],
            [$aula],
            ['tipo' => TipoExamen::Mesa],
        );
        $this->examen(
            $docente,
            [$this->grupo($docente, $this->asignatura('Física I'))],
            [$aula],
            ['hora_inicio' => '14:15:00'],
        );

        $this->intento(null, $aula, null, 'CODIGO_INVALIDO', null, '2026-10-12 08:30:00', 'QR ilegible');

        $filas = $this->datos(
            $this->hoja(
                $this->actingAs($this->administrador())
                    ->get("/api/reportes/intentos/exportar?formato=xlsx&examen={$algebra->id}")
            ),
            'Hora',
        );

        $this->assertSame(
            [[
                '12 oct 2026, 08:30', '', 'QR ilegible', '691A',
                'Álgebra I · Primer parcial; Cálculo I · Examen de mesa', 'Ninguno a esa hora', 'Código no válido',
            ]],
            $filas,
        );
    }

    public function test_the_attempts_report_leaves_out_what_people_reported(): void
    {
        $docente = $this->docente();
        $aula = $this->aula();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$aula]);

        DB::table('incidencias')->insert([
            'examen_id' => $examen->id,
            'aula_id' => $aula->id,
            'estudiante_texto' => 'Uno de gorra',
            'tipo' => 'INTENTO_NO_AUTORIZADO',
            'origen' => 'PERSONA',
            'detalle' => 'Quiso entrar sin estar en la lista.',
            'ocurrida_en' => now(),
            'reportada_por' => $examen->creado_por,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hoja = $this->hoja(
            $this->actingAs($this->administrador())->get('/api/reportes/intentos/exportar?formato=xlsx')
        );

        $this->assertSame([], $this->datos($hoja, 'Hora'));
        $this->assertStringContainsString('0 intentos rechazados.', $this->texto($hoja));
    }

    // --- Resumen por estudiante ----------------------------------------

    public function test_the_student_summary_gathers_exams_attempts_expulsions_and_incidents(): void
    {
        $docente = $this->docente();
        $algebra = $this->grupo($docente, $this->asignatura('Álgebra I'));
        $calculo = $this->grupo($docente, $this->asignatura('Cálculo I'));
        $aula = $this->aula('691A');
        $otraAula = $this->aula('692');

        $examenAlgebra = $this->examen($docente, [$algebra], [$aula]);
        $examenCalculo = $this->examen($docente, [$calculo], [$otraAula], ['hora_inicio' => '14:15:00']);

        // Uno rinde las dos materias; dos solo algebra; tres no esta en ninguna.
        [$uno, $dos] = $this->estudiantesInscritos($algebra, 2);
        Inscripcion::create(['estudiante_id' => $uno->id, 'grupo_id' => $calculo->id, 'via' => 'ADMINISTRACION']);
        [$tres] = $this->estudiantesInscritos($this->grupo($docente, $this->asignatura('Física I')), 1);

        $this->habilitar($examenAlgebra, [$uno, $dos], $aula);
        $this->habilitar($examenCalculo, [$uno], $otraAula);

        $ingreso = $this->ingresar($examenAlgebra, $uno, $aula);
        $this->ingresar($examenCalculo, $uno, $otraAula);
        $this->expulsar($ingreso);
        $this->reportar($examenAlgebra, $aula, 'MATERIAL_NO_PERMITIDO', 'Entró con apuntes.', $uno);
        $this->reportar($examenAlgebra, $aula, 'OTRA', 'Faltan pupitres.');

        $this->intento($examenAlgebra, $aula, $dos, 'NO_HABILITADO');
        // Sin examen: se atribuye por el aula y la hora.
        $this->intento(null, $aula, $tres, 'NO_INSCRITO');
        $this->intento(null, $aula, null, 'CODIGO_INVALIDO', null, null, 'QR ilegible');

        $administrador = $this->administrador();

        $hoja = $this->hoja($this->actingAs($administrador)->get('/api/reportes/estudiantes/exportar?formato=xlsx'));

        $this->assertContains(
            [
                'Código SIS', 'Estudiante', 'Carrera', 'Materias',
                'Exámenes', 'Ingresos', 'Intentos', 'Expulsiones', 'Incidencias',
            ],
            $hoja,
        );
        $this->assertSame(
            [
                [$uno->codigo_universitario, 'Apellido 1 Nombre 1', '', 'Álgebra I, Cálculo I', '2', '2', '0', '1', '1'],
                [$dos->codigo_universitario, 'Apellido 2 Nombre 2', '', 'Álgebra I', '1', '0', '1', '0', '0'],
                [$tres->codigo_universitario, 'Apellido 3 Nombre 3', '', '', '0', '0', '1', '0', '0'],
            ],
            $this->datos($hoja, 'Código SIS'),
        );

        $texto = $this->texto($hoja);

        $this->assertStringContainsString('Resumen por estudiante', $texto);
        $this->assertStringContainsString('Sin filtros: todos los exámenes.', $texto);
        $this->assertStringContainsString('Emitido el 12 oct 2026, 08:20', $texto);
        $this->assertStringContainsString('3 estudiantes.', $texto);
        $this->assertStringContainsString('2 ingresos.', $texto);
        $this->assertStringContainsString('2 intentos rechazados.', $texto);
        $this->assertStringContainsString('1 expulsiones.', $texto);
        $this->assertStringContainsString('1 incidencias reportadas.', $texto);

        $this->actingAs($administrador)
            ->getJson('/api/reportes/examenes')
            ->assertOk()
            ->assertJsonPath('meta.cifras.estudiantes', 3);

        // Con un examen elegido, solo lo de ese examen.
        $deCalculo = $this->hoja(
            $this->actingAs($administrador)
                ->get("/api/reportes/estudiantes/exportar?formato=xlsx&examen={$examenCalculo->id}")
        );

        $this->assertSame(
            [[$uno->codigo_universitario, 'Apellido 1 Nombre 1', '', 'Cálculo I', '1', '1', '0', '0', '0']],
            $this->datos($deCalculo, 'Código SIS'),
        );

        $this->actingAs($administrador)
            ->getJson("/api/reportes/examenes?examen={$examenCalculo->id}")
            ->assertOk()
            ->assertJsonPath('meta.cifras.estudiantes', 1);
    }

    public function test_the_student_summary_follows_the_filters(): void
    {
        $docente = $this->docente('Quiroga Marcela');
        $otro = $this->docente('Rocha Julio');
        $algebra = $this->grupo($docente, $this->asignatura('Álgebra I'));
        $calculo = $this->grupo($otro, $this->asignatura('Cálculo I'));

        $this->examen($docente, [$algebra]);
        $this->examen($otro, [$calculo], [], ['fecha' => '2026-11-20']);

        [$uno] = $this->estudiantesInscritos($algebra, 1);
        [$dos] = $this->estudiantesInscritos($calculo, 1);

        $administrador = $this->administrador();
        $codigos = fn (string $filtros): array => array_column(
            $this->datos(
                $this->hoja(
                    $this->actingAs($administrador)->get("/api/reportes/estudiantes/exportar?formato=xlsx&{$filtros}")
                ),
                'Código SIS',
            ),
            0,
        );

        $this->assertSame([$uno->codigo_universitario, $dos->codigo_universitario], $codigos(''));
        $this->assertSame([$uno->codigo_universitario], $codigos('hasta=2026-10-31'));
        $this->assertSame([$dos->codigo_universitario], $codigos('desde=2026-11-01'));
        $this->assertSame([$dos->codigo_universitario], $codigos('buscar=calculo'));
        $this->assertSame([$uno->codigo_universitario], $codigos('buscar=quiroga'));
        $this->assertSame(
            [$uno->codigo_universitario, $dos->codigo_universitario],
            $codigos('facultad=FCyT&periodo='.$algebra->periodo_id),
        );
    }

    // --- Rutas 78 a 80: contenido de los reportes del docente ----------

    public function test_the_incidents_report_gathers_everything_that_happened_in_the_exam(): void
    {
        $docente = $this->docente('Blanco Coca Leticia');
        $grupo = $this->grupo($docente);
        $aula = $this->aula('691A');
        $otraAula = $this->aula('691B');
        $examen = $this->examen($docente, [$grupo], [$aula, $otraAula]);
        // Otro examen, en otra aula: lo suyo no entra.
        $ajeno = $this->examen($docente, [$this->grupo($docente)], [$this->aula('692')]);

        [$uno, $dos, $tres] = $this->estudiantesInscritos($grupo, 3);
        $this->habilitar($examen, [$uno, $dos], $aula);
        $auxiliar = $this->usuarioConRol('Auxiliar');

        $this->travelTo(self::HOY.' 08:01:00');
        $this->intento($examen, $aula, $tres, 'NO_HABILITADO');

        $this->travelTo(self::HOY.' 08:02:00');
        $this->intento(null, $otraAula, null, 'CODIGO_INVALIDO', null, null, 'QR ilegible');
        $this->intento(null, $this->aula('699'), null, 'CODIGO_INVALIDO', null, null, 'en otra aula');
        $this->intento($ajeno, $aula, null, 'CODIGO_INVALIDO', null, null, 'de otro examen');

        $this->travelTo(self::HOY.' 08:03:00');
        $ingreso = $this->ingresar($examen, $uno, $aula);
        $this->reportar($examen, $otraAula, 'CAMBIO_AULA', 'Rinde en planta baja por una lesión.', $dos, $auxiliar);

        $this->travelTo(self::HOY.' 08:04:00');
        $this->expulsar($ingreso);

        $this->travelTo(self::HOY.' 08:05:00');
        $this->reportar($examen, $aula, 'SUPLANTACION', 'La foto no coincide.', null, null, 'Joven de chompa azul');

        $this->travelTo(self::HOY.' 08:06:00');
        $this->reportar($examen, $aula, 'INTENTO_NO_AUTORIZADO', 'Quiso entrar sin estar en la lista.', null, null, null, 'Mamani Diego');

        $this->travelTo(self::AHORA);

        $cuenta = $this->cuentaDe($docente);

        $hoja = $this->hoja(
            $this->actingAs($cuenta)->get("/api/reportes/incidencias/exportar?formato=xlsx&examen={$examen->id}")
        );

        $this->assertSame(
            [
                [
                    '08:01:00', '691A', $tres->codigo_universitario, 'Apellido 3 Nombre 3',
                    'Intento rechazado · No habilitado', 'Grave', 'La puerta rechazó el ingreso.', 'Sistema',
                ],
                [
                    '08:02:00', '691B', '', 'QR ilegible',
                    'Intento rechazado · Código no válido', 'Grave', 'La puerta rechazó el ingreso.', 'Sistema',
                ],
                [
                    '08:03:00', '691B', $dos->codigo_universitario, 'Apellido 2 Nombre 2',
                    'Cambio de aula', 'Leve', 'Rinde en planta baja por una lesión.', $auxiliar->name,
                ],
                [
                    '08:04:00', '691A', $uno->codigo_universitario, 'Apellido 1 Nombre 1',
                    'Expulsión', 'Muy grave', 'Copiaba con el teléfono.', '',
                ],
                ['08:05:00', '691A', '', 'Joven de chompa azul', 'Suplantación de identidad', 'Muy grave', 'La foto no coincide.', 'Blanco Coca Leticia'],
                [
                    '08:06:00', '691A', '', '',
                    'Intento rechazado', 'Grave', 'Quiso entrar sin estar en la lista.', 'Mamani Diego (código de aula)',
                ],
            ],
            $this->datos($hoja, 'Hora'),
        );

        $this->assertContains(
            ['Hora', 'Aula', 'Código SIS', 'Estudiante', 'Tipo', 'Gravedad', 'Detalle', 'Registrado por'],
            $hoja,
        );

        $texto = $this->texto($hoja);

        $this->assertStringContainsString('Reporte de incidencias', $texto);
        $this->assertStringContainsString('Primer parcial (12 oct 2026, 08:15)', $texto);
        $this->assertStringContainsString('Emitido el 12 oct 2026, 08:20', $texto);
        $this->assertStringContainsString('6 registros.', $texto);
        $this->assertStringContainsString('2 intentos rechazados.', $texto);
        $this->assertStringContainsString('1 situaciones excepcionales.', $texto);
        $this->assertStringContainsString('1 expulsiones.', $texto);
        $this->assertStringContainsString('2 incidencias reportadas.', $texto);
        $this->assertStringContainsString('Muy graves: 2 · graves: 3 · leves: 1.', $texto);

        // La lista del docente cuenta las mismas filas.
        $this->actingAs($cuenta)
            ->getJson('/api/reportes/mis-examenes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $examen->id)
            ->assertJsonPath('data.0.incidencias', 6);
    }

    public function test_an_exam_without_incidents_gives_an_empty_report(): void
    {
        $docente = $this->docente();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$this->aula()]);

        $hoja = $this->hoja(
            $this->actingAs($this->cuentaDe($docente))
                ->get("/api/reportes/incidencias/exportar?formato=xlsx&examen={$examen->id}")
        );

        $this->assertSame([], $this->datos($hoja, 'Hora'));
        $this->assertStringContainsString('0 registros.', $this->texto($hoja));
    }

    public function test_the_attendance_report_lists_who_entered_and_their_situation(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupoA = $this->grupo($docente, $asignatura, 'A1');
        $grupoB = $this->grupo($docente, $asignatura, '07');
        $aula = $this->aula('691A');
        $otra = $this->aula('691B');
        $examen = $this->examen($docente, [$grupoA, $grupoB], [$aula, $otra]);

        [$uno, $dos] = $this->estudiantesInscritos($grupoA, 2);
        [$tres] = $this->estudiantesInscritos($grupoB, 1);
        $this->habilitar($examen, [$uno, $dos, $tres], $aula);

        $this->travelTo(self::HOY.' 08:01:02');
        $this->ingresar($examen, $uno, $aula);

        $this->travelTo(self::HOY.' 08:03:04');
        $expulsado = $this->ingresar($examen, $dos, $aula);
        $this->expulsar($expulsado);

        $this->travelTo(self::HOY.' 08:05:06');
        Ingreso::create([
            'examen_id' => $examen->id,
            'estudiante_id' => $tres->id,
            'aula_id' => $otra->id,
            'medio' => 'QR',
            'es_excepcion' => true,
            'registrado_en' => now(),
            'registrado_por' => $examen->creado_por,
        ]);

        $this->travelTo(self::AHORA);

        $hoja = $this->hoja(
            $this->actingAs($this->cuentaDe($docente))
                ->get("/api/reportes/asistencia/exportar?formato=xlsx&examen={$examen->id}")
        );

        $this->assertSame(
            [
                [$uno->codigo_universitario, 'Apellido 1 Nombre 1', 'A1', $docente->nombre_completo, '691A', '08:01:02', 'Código SIS', 'Rindió'],
                [$dos->codigo_universitario, 'Apellido 2 Nombre 2', 'A1', $docente->nombre_completo, '691A', '08:03:04', 'Código SIS', 'Expulsado'],
                [$tres->codigo_universitario, 'Apellido 3 Nombre 3', '07', $docente->nombre_completo, '691B', '08:05:06', 'Código QR', 'Excepción'],
            ],
            $this->datos($hoja, 'Código SIS'),
        );

        $texto = $this->texto($hoja);

        $this->assertStringContainsString('Reporte de asistencia', $texto);
        $this->assertStringContainsString('3 estudiantes con ingreso registrado.', $texto);
        $this->assertStringContainsString('1 expulsados.', $texto);
    }

    public function test_the_absent_report_lists_the_enabled_students_without_entry(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente, codigo: '2');
        $aula = $this->aula('691A');
        $examen = $this->examen($docente, [$grupo], [$aula]);

        $carrera = Carrera::create([
            'facultad_id' => $this->facultad()->id,
            'codigo' => '411702',
            'nombre' => 'Ingeniería de Sistemas',
            'regimen' => 'SEMESTRAL',
        ]);

        [$presente, $ausente, $sinAula, $noHabilitado, $sinRevisar] = $this->estudiantesInscritos($grupo, 5);
        Estudiante::whereKey($ausente->id)->update(['carrera_id' => $carrera->id]);

        $this->habilitar($examen, [$presente, $ausente], $aula);
        $this->habilitar($examen, [$sinAula]);
        $this->inhabilitar($examen, [$noHabilitado]);
        $this->ingresar($examen, $presente, $aula);

        $hoja = $this->hoja(
            $this->actingAs($this->cuentaDe($docente))
                ->get("/api/reportes/ausentes/exportar?formato=xlsx&examen={$examen->id}")
        );

        // Ni el no habilitado ni el que quedo sin revisar son ausentes.
        $this->assertSame(
            [
                [$ausente->codigo_universitario, 'Apellido 2 Nombre 2', '2', $docente->nombre_completo, 'Ingeniería de Sistemas', '691A'],
                [$sinAula->codigo_universitario, 'Apellido 3 Nombre 3', '2', $docente->nombre_completo, '', 'Sin aula'],
            ],
            $this->datos($hoja, 'Código SIS'),
        );

        $this->assertStringContainsString('2 habilitados que no se presentaron.', $this->texto($hoja));
        $this->assertNotContains($sinRevisar->codigo_universitario, array_column($this->datos($hoja, 'Código SIS'), 0));
    }

    public function test_the_list_of_a_group_has_the_students_with_mail_and_origin(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente, $this->asignatura('Introducción a la Programación', '2010010'), '2');

        [$verificado, $delDocente] = $this->estudiantesInscritos($grupo, 2);
        Estudiante::whereKey($delDocente->id)->update([
            'verificado' => false,
            'documento_identidad' => null,
            'apellidos' => 'Aguilar Cossío',
            'nombres' => 'Mariana',
        ]);

        // Los de otro grupo no entran.
        $this->estudiantesInscritos($this->grupo($docente), 1);

        $hoja = $this->hoja(
            $this->actingAs($this->cuentaDe($docente))->get("/api/reportes/grupos/{$grupo->id}/estudiantes")
        );

        $this->assertSame(
            [
                [
                    $delDocente->codigo_universitario, 'Aguilar Cossío Mariana', '',
                    $delDocente->codigo_universitario.'@est.umss.edu', 'Docente',
                ],
                [
                    $verificado->codigo_universitario, 'Apellido 1 Nombre 1', (string) $verificado->documento_identidad,
                    $verificado->codigo_universitario.'@est.umss.edu', 'Administración',
                ],
            ],
            $this->datos($hoja, 'Código SIS'),
        );

        $texto = $this->texto($hoja);

        $this->assertStringContainsString('Asignatura: 2010010 · Introducción a la Programación', $texto);
        $this->assertStringContainsString('Grupo: 2', $texto);
        $this->assertStringContainsString('Período: 2/2026', $texto);
        $this->assertStringContainsString('2 estudiantes inscritos.', $texto);
    }

    // --- Solo lectura ---------------------------------------------------

    public function test_the_reports_do_not_accept_writing(): void
    {
        $administrador = $this->administrador();
        $docente = $this->cuentaDe($this->docente());

        $rutas = [
            [$administrador, '/api/reportes/examenes'],
            [$administrador, '/api/reportes/horarios/exportar'],
            [$administrador, '/api/reportes/registrados/exportar'],
            [$administrador, '/api/reportes/intentos/exportar'],
            [$administrador, '/api/reportes/estudiantes/exportar'],
            [$docente, '/api/reportes/mis-examenes'],
            [$docente, '/api/reportes/asistencia/exportar'],
            [$docente, '/api/reportes/ausentes/exportar'],
            [$docente, '/api/reportes/incidencias/exportar'],
            [$docente, '/api/reportes/grupos/1/estudiantes'],
        ];

        foreach ($rutas as [$cuenta, $ruta]) {
            $this->actingAs($cuenta)->postJson($ruta)->assertStatus(405);
            $this->actingAs($cuenta)->putJson($ruta)->assertStatus(405);
            $this->actingAs($cuenta)->patchJson($ruta)->assertStatus(405);
            $this->actingAs($cuenta)->deleteJson($ruta)->assertStatus(405);
        }
    }

    public function test_a_report_leaves_no_trace_in_the_data(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $aula = $this->aula();
        $examen = $this->examen($docente, [$grupo], [$aula]);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $this->habilitar($examen, [$estudiante], $aula);
        $this->ingresar($examen, $estudiante, $aula);

        $antes = [
            DB::table('ingresos')->count(),
            DB::table('incidencias')->count(),
            DB::table('habilitaciones')->count(),
            DB::table('bitacora_operaciones')->count(),
        ];

        $this->actingAs($this->administrador())->get('/api/reportes/registrados/exportar?formato=xlsx')->assertOk();
        $this->actingAs($this->administrador())->get('/api/reportes/estudiantes/exportar?formato=xlsx')->assertOk();
        $this->actingAs($this->cuentaDe($docente))
            ->get("/api/reportes/incidencias/exportar?formato=xlsx&examen={$examen->id}")
            ->assertOk();
        $this->actingAs($this->cuentaDe($docente))
            ->get("/api/reportes/asistencia/exportar?formato=xlsx&examen={$examen->id}")
            ->assertOk();

        $this->assertSame($antes, [
            DB::table('ingresos')->count(),
            DB::table('incidencias')->count(),
            DB::table('habilitaciones')->count(),
            DB::table('bitacora_operaciones')->count(),
        ]);
    }
}
