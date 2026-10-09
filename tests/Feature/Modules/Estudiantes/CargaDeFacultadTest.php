<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Estudiantes;

use App\Modules\Academico\Domain\Enums\RegimenCarrera;
use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use App\Modules\Academico\Domain\Models\Carrera;
use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Academico\Domain\Models\Periodo;
use App\Modules\Estudiantes\Domain\Enums\OrigenEstudiante;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Estudiantes\Domain\Models\Inscripcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatosAcademicos;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * La carga de inscripciones de una facultad, que sube la administracion
 * (ruta 36): una fila por inscripcion, con asignatura y grupo.
 */
final class CargaDeFacultadTest extends TestCase
{
    use ApoyoDeCargas;
    use DatosAcademicos;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private const ENCABEZADO = 'codigo_universitario,documento_identidad,nombres,apellidos,codigo_asignatura,grupo,carrera';

    public function test_each_row_enrolls_the_student_in_the_group_of_its_subject(): void
    {
        [$algebra, $programacion] = $this->oferta();
        $administrador = $this->administrador();

        $respuesta = $this->cargarFacultad($this->csv([
            self::ENCABEZADO,
            '202104821,7928194,Kevin René,Alvarado Claros,2008019,1,',
            '202104821,7928194,Kevin René,Alvarado Claros,2010010,2,',
            '201901349,6492819,Diego Andrés,Camacho Zeballos,2008019,1,',
        ], 'inscripciones_fcyt.csv'), 'fcyt', $administrador)->assertOk();

        $respuesta->assertJsonPath('message', 'Carga procesada.');
        $respuesta->assertJsonPath('archivo', 'inscripciones_fcyt.csv');
        $respuesta->assertJsonPath('resumen.filas', 3);
        $respuesta->assertJsonPath('resumen.nuevos', 2);
        $respuesta->assertJsonPath('resumen.reutilizados', 1);
        $respuesta->assertJsonPath('resumen.rechazados', 0);

        $this->assertSame(2, Estudiante::query()->count());
        $this->assertSame(2, Inscripcion::query()->where('grupo_id', $algebra->id)->count());
        $this->assertSame(1, Inscripcion::query()->where('grupo_id', $programacion->id)->count());

        $this->assertDatabaseHas('inscripciones', [
            'grupo_id' => $programacion->id,
            'via' => 'ADMINISTRACION',
            'cargada_por' => $administrador->id,
        ]);

        $this->assertDatabaseHas('cargas_inscritos', [
            'alcance' => 'FACULTAD',
            'grupo_id' => null,
            'facultad_id' => $this->facultad()->id,
            'periodo_id' => $this->periodoVigente()->id,
            'filas' => 3,
            'nuevos' => 2,
            'reutilizados' => 1,
        ]);
    }

    public function test_students_created_by_administration_are_verified(): void
    {
        $this->oferta();

        $this->cargarFacultad(['202104821,7928194,Kevin René,Alvarado Claros,2008019,1'])->assertOk();

        $this->assertDatabaseHas('estudiantes', [
            'codigo_universitario' => '202104821',
            'origen' => 'ADMINISTRACION',
            'verificado' => true,
            'facultad_id' => $this->facultad()->id,
        ]);
    }

    public function test_the_optional_career_column_sets_the_career_of_a_new_student(): void
    {
        $this->oferta();

        $carrera = Carrera::create([
            'facultad_id' => $this->facultad()->id,
            'codigo' => '411702',
            'nombre' => 'Licenciatura en Ingeniería de Sistemas',
            'regimen' => RegimenCarrera::Semestral,
        ]);

        $this->cargarFacultad([
            self::ENCABEZADO,
            '202104821,7928194,Kevin René,Alvarado Claros,2008019,1,411702',
            '201901349,6492819,Diego Andrés,Camacho Zeballos,2008019,1,999999',
        ])->assertOk()->assertJsonPath('resumen.nuevos', 2);

        $this->assertDatabaseHas('estudiantes', ['codigo_universitario' => '202104821', 'carrera_id' => $carrera->id]);
        $this->assertDatabaseHas('estudiantes', ['codigo_universitario' => '201901349', 'carrera_id' => null]);
    }

    public function test_a_group_that_is_not_in_the_offer_is_rejected(): void
    {
        $this->oferta();

        $respuesta = $this->cargarFacultad([
            self::ENCABEZADO,
            '202104821,7928194,Kevin René,Alvarado Claros,2008019,9,',
            '201901349,6492819,Diego Andrés,Camacho Zeballos,9999999,1,',
            '201901350,6492820,Ana,Rojas,2008019,1,',
        ])->assertOk();

        $respuesta->assertJsonPath('resumen.rechazados', 2);
        $respuesta->assertJsonPath('resumen.nuevos', 1);
        $respuesta->assertJsonPath('rechazos.0.fila', 2);
        $respuesta->assertJsonPath('rechazos.0.motivo', 'El grupo no existe en la oferta');
        $respuesta->assertJsonPath('rechazos.1.fila', 3);
    }

    public function test_a_group_of_another_faculty_or_of_a_closed_period_is_not_in_the_offer(): void
    {
        $asignatura = $this->asignatura('Álgebra I', '2008019');
        $this->grupo(null, $asignatura, '1', null, $this->facultad('fce'));

        $cerrado = Periodo::create([
            'codigo' => '1/2020',
            'anio' => 2020,
            'numero' => 1,
            'tipo' => TipoPeriodo::Semestre1,
            'fecha_inicio' => '2020-02-01',
            'fecha_fin' => '2020-07-01',
        ]);
        $this->grupo(null, $asignatura, '2', $cerrado);
        $this->periodoVigente();

        $this->cargarFacultad([
            '202104821,7928194,Kevin René,Alvarado Claros,2008019,1',
            '201901349,6492819,Diego Andrés,Camacho Zeballos,2008019,2',
        ])
            ->assertOk()
            ->assertJsonPath('resumen.rechazados', 2)
            ->assertJsonPath('rechazos.1.motivo', 'El grupo no existe en la oferta');
    }

    public function test_a_row_without_subject_or_group_is_rejected(): void
    {
        $this->oferta();

        $this->cargarFacultad([
            '202104821,7928194,Kevin René,Alvarado Claros',
            '201901349,6492819,Diego Andrés,Camacho Zeballos,2008019,',
            // Una fila completa: sin ella al archivo le faltarian columnas.
            '202000347,8115726,Sofía,Mamani Quispe,9999999,1',
        ])
            ->assertOk()
            ->assertJsonPath('resumen.rechazados', 3)
            ->assertJsonPath('rechazos.0.motivo', 'Faltan datos obligatorios')
            ->assertJsonPath('rechazos.1.motivo', 'Faltan datos obligatorios');
    }

    public function test_the_same_student_twice_in_the_same_group_is_a_repetition(): void
    {
        $this->oferta();

        $this->cargarFacultad([
            '202104821,7928194,Kevin René,Alvarado Claros,2008019,1',
            '202104821,7928194,Kevin René,Alvarado Claros,2010010,2',
            '202104821,7928194,Kevin René,Alvarado Claros,2008019,1',
        ])
            ->assertOk()
            ->assertJsonPath('resumen.nuevos', 1)
            ->assertJsonPath('resumen.reutilizados', 1)
            ->assertJsonPath('rechazos.0.fila', 3)
            ->assertJsonPath('rechazos.0.motivo', 'Se repite en el archivo');
    }

    public function test_a_student_that_differs_between_two_rows_of_the_file_is_a_conflict(): void
    {
        [, $programacion] = $this->oferta();

        $this->cargarFacultad([
            '202104821,7928194,Kevin René,Alvarado Claros,2008019,1',
            '202104821,7928149,Kevin René,Alvarado Claros,2010010,2',
        ])
            ->assertOk()
            ->assertJsonPath('resumen.nuevos', 1)
            ->assertJsonPath('resumen.conflictos', 1)
            ->assertJsonPath('conflictos.0.fila', 2);

        $this->assertDatabaseHas('conflictos_padron', [
            'estudiante_id' => Estudiante::query()->value('id'),
            'grupo_id' => $programacion->id,
            'documento_nuevo' => '7928149',
            'via' => 'ADMINISTRACION',
        ]);
        $this->assertDatabaseHas('estudiantes', ['documento_identidad' => '7928194']);
    }

    public function test_a_matching_load_marks_a_teacher_student_as_verified(): void
    {
        $this->oferta();
        $guardado = $this->estudiante(['origen' => OrigenEstudiante::Docente, 'verificado' => false]);

        $this->cargarFacultad(['202104821,7928194,KEVIN RENE,ALVARADO CLAROS,2008019,1'])
            ->assertOk()
            ->assertJsonPath('resumen.reutilizados', 1);

        $this->assertDatabaseHas('estudiantes', [
            'id' => $guardado->id,
            'verificado' => true,
            'origen' => 'DOCENTE',
            'nombres' => 'Kevin René',
        ]);
    }

    public function test_a_load_that_does_not_match_does_not_verify_nor_change_the_student(): void
    {
        $this->oferta();
        $guardado = $this->estudiante(['origen' => OrigenEstudiante::Docente, 'verificado' => false]);

        $this->cargarFacultad(['202104821,7928149,Kevin René,Alvarado Claros,2008019,1'])
            ->assertOk()
            ->assertJsonPath('resumen.conflictos', 1);

        $this->assertDatabaseHas('estudiantes', [
            'id' => $guardado->id,
            'verificado' => false,
            'documento_identidad' => '7928194',
        ]);
        $this->assertDatabaseCount('inscripciones', 0);
    }

    public function test_an_xlsx_reads_a_group_code_that_lost_its_leading_zero(): void
    {
        $grupo = $this->grupo(null, $this->asignatura('Física', '2006063'), '07');

        $this->cargarFacultad($this->xlsx([
            [202104821, 7928194, 'Kevin René', 'Alvarado Claros', 2006063, 7],
        ]))->assertOk()->assertJsonPath('resumen.nuevos', 1);

        $this->assertSame(1, Inscripcion::query()->where('grupo_id', $grupo->id)->count());
    }

    public function test_without_a_current_period_nothing_is_loaded(): void
    {
        $this->facultad();

        $this->cargarFacultad(['202104821,7928194,Kevin René,Alvarado Claros,2008019,1'])
            ->assertConflict()
            ->assertJsonPath('codigo', 'PERIODO_CERRADO');
    }

    public function test_the_faculty_is_required_and_must_exist(): void
    {
        $this->oferta();
        $administrador = $this->administrador();

        $this->actingAs($administrador)
            ->postJson('/api/estudiantes/cargas', ['archivo' => $this->csv(['x'])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['facultad' => 'La facultad es obligatoria.']);

        $this->cargarFacultad(['x'], 'nada', $administrador)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['facultad' => 'La facultad no existe.']);

        $this->actingAs($administrador)
            ->postJson('/api/estudiantes/cargas', ['facultad' => 'fcyt'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo' => 'El archivo es obligatorio.']);
    }

    public function test_a_guest_cannot_upload(): void
    {
        $this->postJson('/api/estudiantes/cargas', [
            'facultad' => 'fcyt',
            'archivo' => $this->csv(['x']),
        ])->assertUnauthorized();
    }

    public function test_a_teacher_cannot_upload_the_enrollments_of_a_faculty(): void
    {
        $this->oferta();

        $this->cargarFacultad(['x'], 'fcyt', $this->cuentaDe($this->docente()))
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'padron_estudiantes');
    }

    public function test_the_load_is_recorded_in_the_audit_log(): void
    {
        $this->oferta();
        $administrador = $this->administrador();

        $this->cargarFacultad(
            $this->csv(['202104821,7928194,Kevin René,Alvarado Claros,2008019,1'], 'inscripciones_fcyt.csv'),
            'fcyt',
            $administrador,
        )->assertOk();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'inscritos.cargar',
            'usuario_id' => $administrador->id,
            'tabla_afectada' => 'cargas_inscritos',
        ]);

        $descripcion = DB::table('bitacora_operaciones')->where('operacion', 'inscritos.cargar')->value('descripcion');

        $this->assertIsString($descripcion);
        $this->assertStringContainsString('inscripciones_fcyt.csv', $descripcion);
    }

    /**
     * Dos grupos de la FCyT en el periodo vigente: Algebra I grupo 1 e
     * Introduccion a la Programacion grupo 2.
     *
     * @return array{0: Grupo, 1: Grupo}
     */
    private function oferta(): array
    {
        return [
            $this->grupo(null, $this->asignatura('Álgebra I', '2008019'), '1'),
            $this->grupo(null, $this->asignatura('Introducción a la Programación', '2010010'), '2'),
        ];
    }
}
