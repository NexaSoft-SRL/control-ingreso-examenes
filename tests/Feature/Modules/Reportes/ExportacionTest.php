<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Examenes\Domain\Models\Examen;
use App\Modules\Reportes\Application\Contracts\ExportadorReporte;
use App\Modules\Reportes\Application\DTOs\TablaReporte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Todo reporte se descarga en PDF y en hoja de calculo; el archivo lleva
 * el nombre de su alcance y conserva los filtros con que se lo pidio y su
 * fecha de emision. Aqui van tambien los rechazos de una descarga: sin
 * datos, sin rendir, examen o grupo ajeno, permiso de otro rol.
 */
final class ExportacionTest extends TestCase
{
    use EscenarioDeReportes;
    use RefreshDatabase;

    private const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private Docente $docente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::AHORA);
    }

    /**
     * Un examen de hoy con un estudiante que ingreso; su codigo empieza
     * con ceros.
     */
    private function examenConUnIngreso(): Examen
    {
        $this->docente = $this->docente('Quiroga Marcela');
        $grupo = $this->grupo($this->docente, $this->asignatura('Redes de Computadoras', '2010047'), '1');
        $aula = $this->aula('Aula Magna');
        $examen = $this->examen($this->docente, [$grupo], [$aula]);

        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        Estudiante::whereKey($estudiante->id)->update([
            'codigo_universitario' => '002100001',
            'nombres' => 'Kevin René',
            'apellidos' => 'Alvarado Claros',
        ]);

        $this->habilitar($examen, [$estudiante], $aula);
        $this->ingresar($examen, $estudiante, $aula);
        $this->intento($examen, $aula, $estudiante, 'NO_HABILITADO');

        return $examen;
    }

    public function test_every_report_of_the_university_is_exported_to_pdf(): void
    {
        $this->examenConUnIngreso();
        $administrador = $this->administrador();

        $archivos = [
            'horarios' => 'horarios_ingreso_umss_2026-10-12.pdf',
            'registrados' => 'registrados_examen_umss_2026-10-12.pdf',
            'intentos' => 'intentos_rechazados_umss_2026-10-12.pdf',
            'estudiantes' => 'resumen_estudiantes_umss_2026-10-12.pdf',
        ];

        foreach ($archivos as $reporte => $archivo) {
            $respuesta = $this->actingAs($administrador)
                ->get("/api/reportes/{$reporte}/exportar?formato=pdf")
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf')
                ->assertHeader('Content-Disposition', "attachment; filename=\"{$archivo}\"");

            $contenido = $this->contenido($respuesta);

            $this->assertStringStartsWith('%PDF-', $contenido);
            // El PDF va comprimido: lo que se puede comprobar sin abrirlo
            // es que pesa lo que pesa un documento con contenido.
            $this->assertGreaterThan(1000, strlen($contenido));
        }
    }

    public function test_every_report_of_the_university_is_exported_to_a_spreadsheet(): void
    {
        $this->examenConUnIngreso();
        $administrador = $this->administrador();

        $archivos = [
            'horarios' => 'horarios_ingreso_umss_2026-10-12.xlsx',
            'registrados' => 'registrados_examen_umss_2026-10-12.xlsx',
            'intentos' => 'intentos_rechazados_umss_2026-10-12.xlsx',
            'estudiantes' => 'resumen_estudiantes_umss_2026-10-12.xlsx',
        ];

        foreach ($archivos as $reporte => $archivo) {
            $respuesta = $this->actingAs($administrador)
                ->get("/api/reportes/{$reporte}/exportar?formato=xlsx")
                ->assertOk()
                ->assertHeader('Content-Type', self::XLSX)
                ->assertHeader('Content-Disposition', "attachment; filename=\"{$archivo}\"");

            // Un xlsx es un zip: su firma empieza con PK.
            $this->assertStringStartsWith('PK', $this->contenido($respuesta));
        }
    }

    public function test_the_reports_of_an_exam_are_exported_to_pdf_and_to_a_spreadsheet(): void
    {
        $examen = $this->examenConUnIngreso();
        $cuenta = $this->cuentaDe($this->docente);

        foreach (['asistencia', 'ausentes', 'incidencias', 'bitacora'] as $reporte) {
            $pdf = $this->actingAs($cuenta)
                ->get("/api/reportes/{$reporte}/exportar?formato=pdf&examen={$examen->id}")
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf')
                ->assertHeader('Content-Disposition', "attachment; filename=\"{$reporte}_".self::HOY.'.pdf"');

            $this->assertStringStartsWith('%PDF-', $this->contenido($pdf));

            $hoja = $this->actingAs($cuenta)
                ->get("/api/reportes/{$reporte}/exportar?formato=xlsx&examen={$examen->id}")
                ->assertOk()
                ->assertHeader('Content-Type', self::XLSX)
                ->assertHeader('Content-Disposition', "attachment; filename=\"{$reporte}_".self::HOY.'.xlsx"');

            $this->assertStringStartsWith('PK', $this->contenido($hoja));
        }
    }

    public function test_a_report_of_thousands_of_rows_fits_in_a_pdf(): void
    {
        $filas = [];

        for ($i = 1; $i <= 3000; $i++) {
            $filas[] = [
                (string) (202100000 + $i), 'Alvarado Claros Kevin René', 'Licenciatura en Ingeniería de Sistemas',
                'Introducción a la Programación Cálculo I', '2', '1', '0', '0', '0',
            ];
        }

        $pdf = $this->app->make(ExportadorReporte::class)->pdf(new TablaReporte(
            clave: 'resumen_estudiantes_umss_2026-10-12',
            titulo: 'Resumen por estudiante',
            filtros: ['Sin filtros: todos los exámenes.'],
            encabezados: [
                'Código', 'Estudiante', 'Carrera', 'Materias',
                'Exámenes', 'Ingresos', 'Intentos', 'Expulsiones', 'Incidencias',
            ],
            filas: $filas,
            totales: ['3000 estudiantes.'],
            emitidoEn: '2026-10-12 08:20:00',
        ));

        $this->assertStringStartsWith('%PDF-', $pdf);
        // Mas de cien hojas: cada una es un objeto `/Type /Page`.
        $this->assertGreaterThan(100, preg_match_all('#/Type /Page\b#', $pdf));
    }

    public function test_without_a_format_the_report_comes_in_pdf(): void
    {
        $examen = $this->examenConUnIngreso();

        $this->actingAs($this->administrador())
            ->get('/api/reportes/horarios/exportar')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($this->cuentaDe($this->docente))
            ->get("/api/reportes/asistencia/exportar?examen={$examen->id}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_spreadsheet_keeps_the_exam_the_date_and_the_leading_zeros(): void
    {
        $examen = $this->examenConUnIngreso();

        $hoja = $this->hoja(
            $this->actingAs($this->cuentaDe($this->docente))
                ->get("/api/reportes/asistencia/exportar?formato=xlsx&examen={$examen->id}")
        );

        $texto = $this->texto($hoja);

        $this->assertSame('Reporte de asistencia', $hoja[0][0]);
        $this->assertStringContainsString(
            'Examen: Redes de Computadoras · Primer parcial (12 oct 2026, 08:15)',
            $texto,
        );
        $this->assertStringContainsString('Emitido el 12 oct 2026, 08:20', $texto);
        // Debajo del examen, sus grupos con el docente de cada uno.
        $this->assertStringContainsString('Grupos: 1 · '.$this->docente->nombre_completo, $texto);

        // El codigo viaja como texto: una hoja de calculo le comeria los
        // ceros de la izquierda.
        $this->assertSame(
            [['002100001', 'Alvarado Claros Kevin René', '1', $this->docente->nombre_completo, 'Aula Magna', '08:20:00', 'Código SIS', 'Rindió']],
            $this->datos($hoja, 'Código SIS'),
        );
    }

    public function test_the_file_of_the_university_keeps_the_filters_it_was_asked_with(): void
    {
        $this->examenConUnIngreso();
        $periodo = $this->periodoVigente();

        $hoja = $this->hoja(
            $this->actingAs($this->administrador())->get(
                '/api/reportes/intentos/exportar?formato=xlsx'
                ."&periodo={$periodo->id}&facultad=fcyt&buscar=redes&desde=2026-10-01&hasta=2026-10-31"
            )
        );

        $this->assertSame(
            [
                ['Reporte de intentos rechazados'],
                ['Período: 2/2026'],
                ['Facultad: FCyT'],
                ['Búsqueda: redes'],
                ['Desde: 1 oct 2026'],
                ['Hasta: 31 oct 2026'],
                ['Emitido el 12 oct 2026, 08:20'],
            ],
            array_map(static fn (array $fila): array => [$fila[0]], array_slice($hoja, 0, 7)),
        );

        $this->assertSame('002100001', $this->datos($hoja, 'Hora')[0][1]);
    }

    public function test_the_file_without_filters_says_so(): void
    {
        $this->examenConUnIngreso();

        $hoja = $this->hoja(
            $this->actingAs($this->administrador())->get('/api/reportes/registrados/exportar?formato=xlsx')
        );

        $this->assertSame('Sin filtros: todos los exámenes.', $hoja[1][0]);
    }

    public function test_the_name_of_the_file_carries_its_scope(): void
    {
        $examen = $this->examenConUnIngreso();
        $administrador = $this->administrador();

        $nombre = fn (string $consulta): ?string => $this->actingAs($administrador)
            ->get("/api/reportes/horarios/exportar?formato=xlsx{$consulta}")
            ->assertOk()
            ->headers->get('Content-Disposition');

        $this->assertSame('attachment; filename="horarios_ingreso_umss_2026-10-12.xlsx"', $nombre(''));
        $this->assertSame('attachment; filename="horarios_ingreso_umss_2026-10-12.xlsx"', $nombre('&buscar=redes'));
        $this->assertSame('attachment; filename="horarios_ingreso_fcyt_2026-10-12.xlsx"', $nombre('&facultad=FCyT'));
        $this->assertSame(
            "attachment; filename=\"horarios_ingreso_examen{$examen->id}_2026-10-12.xlsx\"",
            $nombre("&facultad=fcyt&examen={$examen->id}"),
        );
    }

    public function test_the_file_of_a_chosen_exam_names_the_exam_and_not_the_filters(): void
    {
        $examen = $this->examenConUnIngreso();

        $hoja = $this->hoja(
            $this->actingAs($this->administrador())
                ->get("/api/reportes/horarios/exportar?formato=xlsx&desde=2026-01-01&examen={$examen->id}")
        );

        $this->assertSame('Examen: Redes de Computadoras · Primer parcial (12 oct 2026, 08:15)', $hoja[1][0]);
        $this->assertStringNotContainsString('Desde:', $this->texto($hoja));
    }

    public function test_an_unknown_format_is_rejected(): void
    {
        $examen = $this->examenConUnIngreso();
        $cuenta = $this->cuentaDe($this->docente);
        $grupoId = $this->grupo($this->docente)->id;

        $this->actingAs($this->administrador())
            ->getJson('/api/reportes/horarios/exportar?formato=docx')
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'formato' => 'Los reportes se exportan a PDF (pdf) o a hoja de cálculo (xlsx).',
            ]);

        $this->actingAs($cuenta)
            ->getJson("/api/reportes/asistencia/exportar?formato=csv&examen={$examen->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['formato']);

        // La lista del grupo solo sale como hoja de calculo.
        $this->actingAs($cuenta)
            ->getJson("/api/reportes/grupos/{$grupoId}/estudiantes?formato=pdf")
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'formato' => 'La lista del grupo se descarga como hoja de cálculo (xlsx).',
            ]);
    }

    public function test_a_scope_without_exams_has_nothing_to_export(): void
    {
        $this->examenConUnIngreso();
        $administrador = $this->administrador();

        foreach (['horarios', 'registrados', 'intentos', 'estudiantes'] as $reporte) {
            $this->actingAs($administrador)
                ->getJson("/api/reportes/{$reporte}/exportar?formato=pdf&desde=2027-01-01")
                ->assertStatus(409)
                ->assertExactJson([
                    'message' => 'No hay exámenes en ese alcance.',
                    'codigo' => 'SIN_DATOS',
                ]);
        }
    }

    public function test_the_reports_of_an_exam_wait_for_its_date(): void
    {
        $docente = $this->docente();
        $manana = $this->examen($docente, [$this->grupo($docente)], [], ['fecha' => '2026-10-13']);
        $ayer = $this->examen($docente, [$this->grupo($docente)], [], ['fecha' => '2026-10-11']);
        $cuenta = $this->cuentaDe($docente);

        foreach (['asistencia', 'ausentes', 'incidencias'] as $reporte) {
            $this->actingAs($cuenta)
                ->getJson("/api/reportes/{$reporte}/exportar?formato=pdf&examen={$manana->id}")
                ->assertStatus(409)
                ->assertExactJson([
                    'message' => 'El examen todavía no se rindió.',
                    'codigo' => 'SIN_RENDIR',
                ]);

            $this->actingAs($cuenta)
                ->get("/api/reportes/{$reporte}/exportar?formato=xlsx&examen={$ayer->id}")
                ->assertOk()
                ->assertHeader('Content-Disposition', "attachment; filename=\"{$reporte}_2026-10-11.xlsx\"");
        }
    }

    public function test_the_teacher_does_not_download_the_exam_of_another(): void
    {
        $examen = $this->examenConUnIngreso();
        $otro = $this->cuentaDe($this->docente());

        foreach (['asistencia', 'ausentes', 'incidencias'] as $reporte) {
            $this->actingAs($otro)
                ->getJson("/api/reportes/{$reporte}/exportar?formato=pdf&examen={$examen->id}")
                ->assertForbidden()
                ->assertExactJson([
                    'message' => 'Este examen no está a su cargo.',
                    'alcance' => true,
                ]);
        }
    }

    public function test_the_teacher_of_an_included_group_downloads_the_exam(): void
    {
        $registra = $this->docente();
        $invitado = $this->docente();
        $asignatura = $this->asignatura();

        $examen = $this->examen($registra, [
            $this->grupo($registra, $asignatura),
            $this->grupo($invitado, $asignatura),
        ]);

        $this->actingAs($this->cuentaDe($invitado))
            ->get("/api/reportes/asistencia/exportar?formato=xlsx&examen={$examen->id}")
            ->assertOk();
    }

    public function test_the_reports_of_an_exam_need_an_exam_that_exists(): void
    {
        $cuenta = $this->cuentaDe($this->docente());

        foreach (['asistencia', 'ausentes', 'incidencias'] as $reporte) {
            $this->actingAs($cuenta)
                ->getJson("/api/reportes/{$reporte}/exportar?formato=pdf")
                ->assertStatus(422)
                ->assertJsonValidationErrors(['examen' => 'Elija el examen del reporte.']);

            $this->actingAs($cuenta)
                ->getJson("/api/reportes/{$reporte}/exportar?formato=pdf&examen=abc")
                ->assertStatus(422)
                ->assertJsonValidationErrors(['examen' => 'El examen no es válido.']);

            $this->actingAs($cuenta)
                ->getJson("/api/reportes/{$reporte}/exportar?formato=pdf&examen=999")
                ->assertNotFound()
                ->assertExactJson(['message' => 'Examen no encontrado.']);
        }
    }

    public function test_the_list_of_a_group_is_a_spreadsheet_named_after_the_group(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente, $this->asignatura('Introducción a la Programación', '2010010'), '2');
        $this->estudiantesInscritos($grupo, 2);

        $respuesta = $this->actingAs($this->cuentaDe($docente))
            ->get("/api/reportes/grupos/{$grupo->id}/estudiantes?formato=xlsx")
            ->assertOk()
            ->assertHeader('Content-Type', self::XLSX)
            ->assertHeader('Content-Disposition', 'attachment; filename="estudiantes_2010010_g2.xlsx"');

        $this->assertStringStartsWith('PK', $this->contenido($respuesta));
    }

    public function test_the_list_of_a_group_is_only_for_its_teacher(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $sinDocente = $this->grupo();

        $this->getJson("/api/reportes/grupos/{$grupo->id}/estudiantes")->assertUnauthorized();

        $this->actingAs($this->cuentaDe($this->docente()))
            ->getJson("/api/reportes/grupos/{$grupo->id}/estudiantes")
            ->assertForbidden()
            ->assertExactJson(['message' => 'Este grupo no está a su cargo.', 'alcance' => true]);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson("/api/reportes/grupos/{$sinDocente->id}/estudiantes")
            ->assertForbidden()
            ->assertJsonPath('alcance', true);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson('/api/reportes/grupos/999/estudiantes')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Grupo no encontrado.']);

        $this->actingAs($this->administrador())
            ->getJson("/api/reportes/grupos/{$grupo->id}/estudiantes")
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'mis_grupos');
    }

    public function test_an_empty_group_gives_an_empty_list(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        $hoja = $this->hoja(
            $this->actingAs($this->cuentaDe($docente))->get("/api/reportes/grupos/{$grupo->id}/estudiantes")
        );

        $this->assertSame([], $this->datos($hoja, 'Código SIS'));
        $this->assertStringContainsString('0 estudiantes inscritos.', $this->texto($hoja));
    }

    public function test_every_export_demands_a_session(): void
    {
        foreach (['horarios', 'registrados', 'intentos', 'estudiantes', 'asistencia', 'ausentes', 'incidencias'] as $reporte) {
            $this->getJson("/api/reportes/{$reporte}/exportar?formato=pdf")->assertUnauthorized();
        }
    }

    public function test_each_role_downloads_only_its_own_reports(): void
    {
        $examen = $this->examenConUnIngreso();
        $administrador = $this->administrador();
        $cuenta = $this->cuentaDe($this->docente);
        $auxiliar = $this->usuarioConRol('Auxiliar');

        foreach (['horarios', 'registrados', 'intentos', 'estudiantes'] as $reporte) {
            $this->actingAs($cuenta)
                ->getJson("/api/reportes/{$reporte}/exportar?formato=pdf&examen={$examen->id}")
                ->assertForbidden()
                ->assertJsonPath('permiso_requerido', 'reportes_universidad');

            $this->actingAs($auxiliar)
                ->getJson("/api/reportes/{$reporte}/exportar?formato=pdf")
                ->assertForbidden();
        }

        foreach (['asistencia', 'ausentes', 'incidencias'] as $reporte) {
            $this->actingAs($administrador)
                ->getJson("/api/reportes/{$reporte}/exportar?formato=pdf&examen={$examen->id}")
                ->assertForbidden()
                ->assertJsonPath('permiso_requerido', 'reportes_examenes');

            $this->actingAs($auxiliar)
                ->getJson("/api/reportes/{$reporte}/exportar?formato=pdf&examen={$examen->id}")
                ->assertForbidden();
        }
    }
}
