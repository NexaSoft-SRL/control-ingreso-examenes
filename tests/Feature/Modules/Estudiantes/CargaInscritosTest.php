<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Estudiantes;

use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use App\Modules\Academico\Domain\Models\Periodo;
use App\Modules\Estudiantes\Domain\Enums\OrigenEstudiante;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Estudiantes\Domain\Models\Inscripcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatosAcademicos;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * La lista de inscritos que el docente sube para su grupo (ruta 41) y las
 * cinco salidas de una fila (3.5.1).
 */
final class CargaInscritosTest extends TestCase
{
    use ApoyoDeCargas;
    use DatosAcademicos;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private const ENCABEZADO = 'codigo_universitario,documento_identidad,nombres,apellidos';

    // --- Formato del archivo ---

    public function test_a_csv_with_a_header_row_loads_the_list(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        $respuesta = $this->cargarLista($docente, $grupo, [
            self::ENCABEZADO,
            '202104821,7928194,Kevin René,Alvarado Claros',
            '201901349,6492819,Diego Andrés,Camacho Zeballos',
        ])->assertOk();

        $respuesta->assertJsonPath('message', 'Carga procesada.');
        $respuesta->assertJsonPath('archivo', 'lista.csv');
        $respuesta->assertJsonPath('resumen.filas', 2);
        $respuesta->assertJsonPath('resumen.nuevos', 2);
        $respuesta->assertJsonPath('resumen.reutilizados', 0);
        $respuesta->assertJsonPath('resumen.ya_inscritos', 0);
        $respuesta->assertJsonPath('resumen.rechazados', 0);
        $respuesta->assertJsonPath('resumen.conflictos', 0);
        $respuesta->assertJsonPath('rechazos', []);
        $respuesta->assertJsonPath('conflictos', []);

        $this->assertSame(2, Estudiante::query()->count());
        $this->assertSame(2, Inscripcion::query()->where('grupo_id', $grupo->id)->count());
    }

    public function test_a_file_without_a_header_row_also_loads(): void
    {
        $docente = $this->docente();

        $this->cargarLista($docente, $this->grupo($docente), [
            '202104821,7928194,Kevin René,Alvarado Claros',
        ])->assertOk()->assertJsonPath('resumen.nuevos', 1);
    }

    public function test_a_file_with_semicolons_is_also_read(): void
    {
        $docente = $this->docente();

        $this->cargarLista($docente, $this->grupo($docente), [
            'codigo_universitario;documento_identidad;nombres;apellidos',
            '202104821;7928194;Kevin René;Alvarado Claros',
        ])->assertOk()->assertJsonPath('resumen.nuevos', 1);

        $this->assertDatabaseHas('estudiantes', [
            'codigo_universitario' => '202104821',
            'documento_identidad' => '7928194',
            'nombres' => 'Kevin René',
            'apellidos' => 'Alvarado Claros',
        ]);
    }

    public function test_an_xlsx_file_is_read_with_its_numeric_cells_as_text(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        $this->cargarLista($docente, $grupo, $this->xlsx([
            ['codigo_universitario', 'documento_identidad', 'nombres', 'apellidos'],
            [202104821, 7928194, 'Kevin René', 'Alvarado Claros'],
            ['201901349', '6492819-1B', 'Diego Andrés', 'Camacho Zeballos'],
        ]))
            ->assertOk()
            ->assertJsonPath('archivo', 'lista.xlsx')
            ->assertJsonPath('resumen.nuevos', 2);

        $this->assertDatabaseHas('estudiantes', [
            'codigo_universitario' => '202104821',
            'documento_identidad' => '7928194',
        ]);
        $this->assertDatabaseHas('estudiantes', [
            'codigo_universitario' => '201901349',
            'documento_identidad' => '6492819-1B',
        ]);
    }

    public function test_blank_lines_are_skipped_and_do_not_shift_the_row_numbers(): void
    {
        $docente = $this->docente();

        $respuesta = $this->cargarLista($docente, $this->grupo($docente), [
            self::ENCABEZADO,
            '202104821,7928194,Kevin René,Alvarado Claros',
            ',,,',
            '201901349,,Diego Andrés,Camacho Zeballos',
        ])->assertOk();

        $respuesta->assertJsonPath('resumen.filas', 2);
        $respuesta->assertJsonPath('rechazos.0.fila', 4);
    }

    public function test_a_file_of_another_type_is_rejected(): void
    {
        $docente = $this->docente();

        $this->cargarLista(
            $docente,
            $this->grupo($docente),
            UploadedFile::fake()->create('lista.pdf', 10, 'application/pdf'),
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo' => 'El archivo debe ser .csv o .xlsx.']);
    }

    public function test_the_file_is_required(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        $this->actingAs($this->cuentaDe($docente))
            ->postJson("/api/docente/grupos/{$grupo->id}/inscritos/carga", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo' => 'El archivo es obligatorio.']);
    }

    public function test_a_file_without_rows_is_rejected(): void
    {
        $docente = $this->docente();

        $this->cargarLista($docente, $this->grupo($docente), [self::ENCABEZADO])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'ARCHIVO_VACIO')
            ->assertJsonValidationErrors(['archivo' => 'El archivo no tiene filas.']);

        $this->assertDatabaseCount('cargas_inscritos', 0);
    }

    public function test_a_broken_xlsx_is_rejected_without_a_server_error(): void
    {
        $docente = $this->docente();

        $this->cargarLista(
            $docente,
            $this->grupo($docente),
            UploadedFile::fake()->createWithContent('lista.xlsx', 'esto no es una hoja de cálculo'),
        )
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'ARCHIVO_NO_CORRESPONDE')
            ->assertJsonValidationErrors(['archivo' => 'El archivo no es una lista de inscritos.']);

        $this->assertDatabaseCount('cargas_inscritos', 0);
    }

    public function test_two_thousand_rows_are_processed_in_under_ten_seconds(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $filas = [self::ENCABEZADO];

        for ($i = 1; $i <= 2000; $i++) {
            $filas[] = sprintf('%d,%d,Nombre %d,Apellido %d', 202100000 + $i, 7000000 + $i, $i, $i);
        }

        $inicio = microtime(true);

        $this->cargarLista($docente, $grupo, $filas)
            ->assertOk()
            ->assertJsonPath('resumen.filas', 2000)
            ->assertJsonPath('resumen.nuevos', 2000)
            ->assertJsonPath('resumen.rechazados', 0);

        $this->assertLessThan(10.0, microtime(true) - $inicio);
        $this->assertSame(2000, Inscripcion::query()->where('grupo_id', $grupo->id)->count());
    }

    // --- Salida 1: rechazada ---

    public function test_a_row_with_missing_data_is_rejected_with_its_line_and_reason(): void
    {
        $docente = $this->docente();

        $respuesta = $this->cargarLista($docente, $this->grupo($docente), [
            self::ENCABEZADO,
            '202104821,7928194,Kevin René,Alvarado Claros',
            '201901349,6492819,,Camacho Zeballos',
            '201901350,6492820',
        ])->assertOk();

        // La fila mala no detiene la carga: la buena entra igual.
        $respuesta->assertJsonPath('resumen.nuevos', 1);
        $respuesta->assertJsonPath('resumen.rechazados', 2);
        $respuesta->assertJsonPath('rechazos.0.fila', 3);
        $respuesta->assertJsonPath('rechazos.0.motivo', 'Faltan datos obligatorios');
        $respuesta->assertJsonPath('rechazos.1.fila', 4);
        $respuesta->assertJsonPath('rechazos.1.motivo', 'Faltan datos obligatorios');
    }

    public function test_a_row_without_document_is_rejected(): void
    {
        $docente = $this->docente();

        $this->cargarLista($docente, $this->grupo($docente), [
            '201901349,,Diego Andrés,Camacho Zeballos',
        ])
            ->assertOk()
            ->assertJsonPath('rechazos.0.fila', 1)
            ->assertJsonPath('rechazos.0.motivo', 'Sin documento de identidad');

        $this->assertDatabaseCount('estudiantes', 0);
    }

    public function test_a_code_that_is_not_five_to_ten_digits_is_rejected(): void
    {
        $docente = $this->docente();

        $respuesta = $this->cargarLista($docente, $this->grupo($docente), [
            '1234,7928194,Kevin,Alvarado',
            '2021A4821,7928195,Diego,Camacho',
            '20210482100,7928196,Ana,Rojas',
            '12345,7928197,Luis,Mamani',
        ])->assertOk();

        $respuesta->assertJsonPath('resumen.rechazados', 3);
        $respuesta->assertJsonPath('resumen.nuevos', 1);
        $respuesta->assertJsonPath('rechazos.0.motivo', 'Código universitario no válido');
        $respuesta->assertJsonPath('rechazos.2.fila', 3);
    }

    public function test_a_code_repeated_inside_the_file_is_rejected(): void
    {
        $docente = $this->docente();

        $respuesta = $this->cargarLista($docente, $this->grupo($docente), [
            self::ENCABEZADO,
            '202104821,7928194,Kevin René,Alvarado Claros',
            '202104821,7928194,Kevin René,Alvarado Claros',
        ])->assertOk();

        $respuesta->assertJsonPath('resumen.nuevos', 1);
        $respuesta->assertJsonPath('rechazos.0.fila', 3);
        $respuesta->assertJsonPath('rechazos.0.motivo', 'Se repite en el archivo');
    }

    public function test_a_new_student_with_the_document_of_another_is_rejected(): void
    {
        $docente = $this->docente();
        $this->estudiante(['codigo_universitario' => '202000001', 'documento_identidad' => '7928194']);

        $respuesta = $this->cargarLista($docente, $this->grupo($docente), [
            '202104821,7928194,Kevin René,Alvarado Claros',
            '201901349,6492819,Diego Andrés,Camacho Zeballos',
            '201901350,6492819,Otro Nombre,Otro Apellido',
        ])->assertOk();

        $respuesta->assertJsonPath('resumen.nuevos', 1);
        $respuesta->assertJsonPath('resumen.rechazados', 2);
        $respuesta->assertJsonPath('rechazos.0.fila', 1);
        $respuesta->assertJsonPath('rechazos.0.motivo', 'El documento ya pertenece a otro estudiante');
        $respuesta->assertJsonPath('rechazos.1.fila', 3);
    }

    // --- Salida 2: nuevo ---

    public function test_an_unknown_code_creates_the_student_and_enrolls_it(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        $this->cargarLista($docente, $grupo, ['202104821,7928194,Kevin René,Alvarado Claros'])
            ->assertOk()
            ->assertJsonPath('resumen.nuevos', 1);

        $this->assertDatabaseHas('estudiantes', [
            'codigo_universitario' => '202104821',
            'origen' => 'DOCENTE',
            'verificado' => false,
            'activo' => true,
            'facultad_id' => $grupo->facultad_id,
        ]);

        $this->assertDatabaseHas('inscripciones', [
            'estudiante_id' => Estudiante::query()->value('id'),
            'grupo_id' => $grupo->id,
            'via' => 'DOCENTE',
            'cargada_por' => $docente->user_id,
            'carga_id' => DB::table('cargas_inscritos')->value('id'),
        ]);
    }

    // --- Salida 3: conflicto ---

    public function test_a_different_document_opens_a_conflict_and_leaves_the_enrollment_waiting(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $guardado = $this->estudiante();

        $respuesta = $this->cargarLista($docente, $grupo, [
            self::ENCABEZADO,
            '202104821,7928149,Kevin René,Alvarado Claros',
        ])->assertOk();

        $respuesta->assertJsonPath('resumen.conflictos', 1);
        $respuesta->assertJsonPath('resumen.nuevos', 0);
        $respuesta->assertJsonPath('resumen.reutilizados', 0);
        $respuesta->assertJsonPath('conflictos.0.fila', 2);
        $respuesta->assertJsonPath('conflictos.0.codigo', '202104821');
        $respuesta->assertJsonPath('conflictos.0.motivo', 'Documento distinto al del padrón');

        $this->assertDatabaseHas('conflictos_padron', [
            'estudiante_id' => $guardado->id,
            'grupo_id' => $grupo->id,
            'fila' => 2,
            'tipo' => 'DOCUMENTO_DISTINTO',
            'documento_nuevo' => '7928149',
            'via' => 'DOCENTE',
            'estado' => 'PENDIENTE',
            'reportado_por' => $docente->user_id,
        ]);
        $this->assertDatabaseCount('inscripciones', 0);
    }

    public function test_a_different_name_opens_a_conflict(): void
    {
        $docente = $this->docente();
        $this->estudiante();

        $this->cargarLista($docente, $this->grupo($docente), [
            '202104821,7928194,Kevin René Luis,Alvarado Claros',
        ])
            ->assertOk()
            ->assertJsonPath('conflictos.0.motivo', 'Nombre distinto al del padrón');

        $this->assertDatabaseHas('conflictos_padron', [
            'tipo' => 'NOMBRE_DISTINTO',
            'nombres_nuevos' => 'Kevin René Luis',
            'apellidos_nuevos' => 'Alvarado Claros',
        ]);
    }

    public function test_the_teacher_never_modifies_an_existing_student(): void
    {
        $docente = $this->docente();
        $guardado = $this->estudiante();
        $antes = $guardado->fresh()?->getAttributes();

        $this->cargarLista($docente, $this->grupo($docente), [
            '202104821,1111111,Otro Nombre,Otro Apellido',
        ])->assertOk();

        $this->assertSame($antes, $guardado->fresh()?->getAttributes());
    }

    public function test_names_that_differ_only_in_case_accents_or_spaces_are_not_a_conflict(): void
    {
        $docente = $this->docente();
        $this->estudiante();

        $this->cargarLista($docente, $this->grupo($docente), [
            '202104821, 7928194 ,KEVIN  RENE,alvarado claros',
        ])
            ->assertOk()
            ->assertJsonPath('resumen.conflictos', 0)
            ->assertJsonPath('resumen.reutilizados', 1);
    }

    public function test_uploading_the_same_file_twice_does_not_open_the_conflict_twice(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $this->estudiante();
        $lineas = ['202104821,7928149,Kevin René,Alvarado Claros'];

        $this->cargarLista($docente, $grupo, $lineas)->assertOk();
        $this->cargarLista($docente, $grupo, $lineas)
            ->assertOk()
            ->assertJsonPath('resumen.conflictos', 1);

        $this->assertDatabaseCount('conflictos_padron', 1);
    }

    // --- Salida 4: ya inscrito ---

    public function test_a_student_already_enrolled_in_the_group_is_left_untouched(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $lineas = ['202104821,7928194,Kevin René,Alvarado Claros'];

        $this->cargarLista($docente, $grupo, $lineas)->assertOk();

        $this->cargarLista($docente, $grupo, $lineas)
            ->assertOk()
            ->assertJsonPath('resumen.ya_inscritos', 1)
            ->assertJsonPath('resumen.nuevos', 0)
            ->assertJsonPath('resumen.reutilizados', 0);

        $this->assertDatabaseCount('estudiantes', 1);
        $this->assertDatabaseCount('inscripciones', 1);
    }

    // --- Salida 5: reutilizado ---

    public function test_a_known_student_is_reused_and_enrolled_without_duplicating_it(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $guardado = $this->estudiante();

        $this->cargarLista($docente, $grupo, ['202104821,7928194,Kevin René,Alvarado Claros'])
            ->assertOk()
            ->assertJsonPath('resumen.reutilizados', 1)
            ->assertJsonPath('resumen.nuevos', 0);

        $this->assertDatabaseCount('estudiantes', 1);
        $this->assertDatabaseHas('inscripciones', [
            'estudiante_id' => $guardado->id,
            'grupo_id' => $grupo->id,
            'via' => 'DOCENTE',
        ]);
        // Lo trajo la administracion: sigue verificado y con su origen.
        $this->assertDatabaseHas('estudiantes', [
            'id' => $guardado->id,
            'origen' => 'ADMINISTRACION',
            'verificado' => true,
        ]);
    }

    public function test_the_load_is_additive_and_never_removes_enrollments(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        $this->cargarLista($docente, $grupo, [
            '202104821,7928194,Kevin René,Alvarado Claros',
            '201901349,6492819,Diego Andrés,Camacho Zeballos',
        ])->assertOk();

        $this->cargarLista($docente, $grupo, ['201901350,6492820,Ana,Rojas'])->assertOk();

        $this->assertSame(3, Inscripcion::query()->where('grupo_id', $grupo->id)->count());
    }

    public function test_every_load_leaves_a_row_with_its_counters_and_rejections(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $this->estudiante(['codigo_universitario' => '202000001', 'documento_identidad' => '5555555']);

        $this->cargarLista($docente, $grupo, $this->csv([
            self::ENCABEZADO,
            '202104821,7928194,Kevin René,Alvarado Claros',
            '202000001,5555555,Kevin René,Alvarado Claros',
            '202000002,,Sin,Documento',
        ], 'inscritos_g1.csv'))->assertOk();

        $this->assertDatabaseHas('cargas_inscritos', [
            'alcance' => 'GRUPO',
            'grupo_id' => $grupo->id,
            'facultad_id' => $grupo->facultad_id,
            'periodo_id' => $grupo->periodo_id,
            'archivo' => 'inscritos_g1.csv',
            'filas' => 3,
            'nuevos' => 1,
            'reutilizados' => 1,
            'ya_inscritos' => 0,
            'rechazados' => 1,
            'conflictos' => 0,
            'cargada_por' => $docente->user_id,
        ]);

        $rechazos = DB::table('cargas_inscritos')->value('rechazos');

        $this->assertIsString($rechazos);
        $this->assertSame(
            [['fila' => 4, 'motivo' => 'Sin documento de identidad']],
            json_decode($rechazos, true),
        );
    }

    // --- Acceso, alcance, periodo y bitacora ---

    public function test_a_guest_cannot_upload(): void
    {
        $grupo = $this->grupo($this->docente());

        $this->postJson("/api/docente/grupos/{$grupo->id}/inscritos/carga", [
            'archivo' => $this->csv(['x']),
        ])->assertUnauthorized();
    }

    public function test_a_role_without_the_permission_cannot_upload(): void
    {
        $grupo = $this->grupo($this->docente());

        // El Administrador tiene todos los permisos (D-8): se prueba con el Auxiliar.
        $this->actingAs($this->usuarioConRol('Auxiliar'))
            ->postJson("/api/docente/grupos/{$grupo->id}/inscritos/carga", [
                'archivo' => $this->csv(['x']),
            ])
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'mis_grupos');
    }

    public function test_the_administrator_does_not_have_the_permission_of_the_teacher_groups(): void
    {
        $grupo = $this->grupo($this->docente());

        $this->actingAs($this->administrador())
            ->postJson("/api/docente/grupos/{$grupo->id}/inscritos/carga", [
                'archivo' => $this->csv(['202104821,7928194,Kevin René,Alvarado Claros']),
            ])
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'mis_grupos');

        $this->assertDatabaseCount('cargas_inscritos', 0);
    }

    public function test_a_teacher_cannot_upload_to_a_group_of_another_teacher(): void
    {
        $docente = $this->docente();
        $ajeno = $this->grupo($this->docente());

        $this->cargarLista($docente, $ajeno, ['202104821,7928194,Kevin René,Alvarado Claros'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Este grupo no es tuyo.', 'alcance' => true]);

        $this->assertDatabaseCount('estudiantes', 0);
        $this->assertDatabaseCount('cargas_inscritos', 0);
    }

    public function test_a_group_that_does_not_exist_is_not_found(): void
    {
        $docente = $this->docente();

        $this->actingAs($this->cuentaDe($docente))
            ->postJson('/api/docente/grupos/999999/inscritos/carga', ['archivo' => $this->csv(['x'])])
            ->assertNotFound()
            ->assertJsonPath('message', 'Grupo no encontrado.');
    }

    public function test_a_group_of_a_closed_period_does_not_accept_lists(): void
    {
        $docente = $this->docente();

        $cerrado = Periodo::create([
            'codigo' => '1/2020',
            'anio' => 2020,
            'numero' => 1,
            'tipo' => TipoPeriodo::Semestre1,
            'fecha_inicio' => '2020-02-01',
            'fecha_fin' => '2020-07-01',
        ]);

        $grupo = $this->grupo($docente, null, null, $cerrado);

        $this->cargarLista($docente, $grupo, ['202104821,7928194,Kevin René,Alvarado Claros'])
            ->assertConflict()
            ->assertJsonPath('codigo', 'PERIODO_CERRADO');

        $this->assertDatabaseCount('estudiantes', 0);
    }

    public function test_a_group_of_a_period_without_dates_does_not_accept_lists(): void
    {
        $docente = $this->docente();

        $sinFechas = Periodo::create([
            'codigo' => '0/2020',
            'anio' => 2020,
            'numero' => 0,
            'tipo' => TipoPeriodo::Anual,
        ]);

        $this->cargarLista($docente, $this->grupo($docente, null, null, $sinFechas), [
            '202104821,7928194,Kevin René,Alvarado Claros',
        ])->assertConflict()->assertJsonPath('codigo', 'PERIODO_CERRADO');
    }

    public function test_the_load_is_recorded_in_the_audit_log(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);

        $this->cargarLista($docente, $grupo, $this->csv(
            ['202104821,7928194,Kevin,Alvarado'],
            'inscritos_2026.csv',
        ))->assertOk();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'inscritos.cargar',
            'usuario_id' => $docente->user_id,
            'tabla_afectada' => 'cargas_inscritos',
            'registro_id' => DB::table('cargas_inscritos')->value('id'),
        ]);

        $descripcion = DB::table('bitacora_operaciones')->where('operacion', 'inscritos.cargar')->value('descripcion');

        $this->assertIsString($descripcion);
        $this->assertStringContainsString('inscritos_2026.csv', $descripcion);
        $this->assertStringContainsString('1 nuevos', $descripcion);
    }

    public function test_a_student_created_by_a_teacher_keeps_that_origin(): void
    {
        $docente = $this->docente();

        $this->cargarLista($docente, $this->grupo($docente), ['202104821,7928194,Kevin,Alvarado'])
            ->assertOk();

        $estudiante = Estudiante::query()->firstOrFail();

        $this->assertSame(OrigenEstudiante::Docente, $estudiante->origen);
        $this->assertFalse($estudiante->verificado);
    }
}
