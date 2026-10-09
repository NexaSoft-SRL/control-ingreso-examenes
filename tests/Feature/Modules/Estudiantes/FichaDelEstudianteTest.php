<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Estudiantes;

use App\Modules\Academico\Domain\Enums\RegimenCarrera;
use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use App\Modules\Academico\Domain\Models\Carrera;
use App\Modules\Academico\Domain\Models\Periodo;
use App\Modules\Estudiantes\Domain\Enums\OrigenEstudiante;
use App\Modules\Estudiantes\Domain\Models\Inscripcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DatosAcademicos;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * La ficha de un estudiante del padron: sus datos, su origen y las
 * materias que lleva en el periodo, con el grupo y el docente de cada una
 * (HU-13, criterio 5).
 */
final class FichaDelEstudianteTest extends TestCase
{
    use ApoyoDeCargas;
    use DatosAcademicos;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_the_record_shows_the_student_and_the_subjects_with_group_and_teacher(): void
    {
        $carrera = Carrera::create([
            'facultad_id' => $this->facultad()->id,
            'codigo' => '419701',
            'nombre' => 'Ing. Informática',
            'regimen' => RegimenCarrera::Semestral,
        ]);

        $estudiante = $this->estudiante([
            'nombres' => 'Mariana',
            'apellidos' => 'Aguilar Cossío',
            'carrera_id' => $carrera->id,
        ]);

        $docente = $this->docenteSinCuenta('Blanco Coca Leticia');
        $programacion = $this->grupo($docente, $this->asignatura('Introducción a la Programación', '2010010'), '2');
        $algebra = $this->grupo(null, $this->asignatura('Álgebra I', '2008019'), '1');

        Inscripcion::create([
            'estudiante_id' => $estudiante->id,
            'grupo_id' => $programacion->id,
            'via' => OrigenEstudiante::Docente,
        ]);
        Inscripcion::create([
            'estudiante_id' => $estudiante->id,
            'grupo_id' => $algebra->id,
            'via' => OrigenEstudiante::Administracion,
        ]);

        $periodo = $this->periodoVigente()->codigo;

        $this->actingAs($this->administrador())
            ->getJson("/api/estudiantes/{$estudiante->id}")
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $estudiante->id,
                    'codigo' => '202104821',
                    'nombre' => 'Aguilar Cossío, Mariana',
                    'nombres' => 'Mariana',
                    'apellidos' => 'Aguilar Cossío',
                    'documento' => '7928194',
                    'correo' => '202104821@est.umss.edu',
                    'facultad' => 'FCyT',
                    'carrera' => 'Ing. Informática',
                    'origen' => 'Verificado',
                    'materias' => [
                        [
                            'asignatura' => ['codigo' => '2010010', 'nombre' => 'Introducción a la Programación'],
                            'grupo' => '2',
                            'grupo_id' => $programacion->id,
                            'docente' => 'Blanco Coca Leticia',
                            'periodo' => $periodo,
                            'via' => 'Docente',
                        ],
                        [
                            // Un grupo sin docente queda «Por designar»: va nulo.
                            'asignatura' => ['codigo' => '2008019', 'nombre' => 'Álgebra I'],
                            'grupo' => '1',
                            'grupo_id' => $algebra->id,
                            'docente' => null,
                            'periodo' => $periodo,
                            'via' => 'Administración',
                        ],
                    ],
                ],
            ]);
    }

    public function test_a_student_without_enrollments_has_no_subjects(): void
    {
        $estudiante = $this->estudiante([
            'origen' => OrigenEstudiante::Docente,
            'verificado' => false,
            'facultad_id' => null,
        ]);

        $this->actingAs($this->administrador())
            ->getJson("/api/estudiantes/{$estudiante->id}")
            ->assertOk()
            ->assertJsonPath('data.origen', 'Docente')
            ->assertJsonPath('data.facultad', null)
            ->assertJsonPath('data.carrera', null)
            ->assertJsonPath('data.materias', []);
    }

    public function test_the_subjects_are_only_those_of_current_periods(): void
    {
        $estudiante = $this->estudiante();

        $cerrado = Periodo::create([
            'codigo' => '1/2020',
            'anio' => 2020,
            'numero' => 1,
            'tipo' => TipoPeriodo::Semestre1,
            'fecha_inicio' => '2020-02-01',
            'fecha_fin' => '2020-07-01',
        ]);

        $vigente = $this->grupo();

        foreach ([$vigente, $this->grupo(null, null, null, $cerrado)] as $grupo) {
            Inscripcion::create([
                'estudiante_id' => $estudiante->id,
                'grupo_id' => $grupo->id,
                'via' => OrigenEstudiante::Administracion,
            ]);
        }

        $this->actingAs($this->administrador())
            ->getJson("/api/estudiantes/{$estudiante->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.materias')
            ->assertJsonPath('data.materias.0.grupo_id', $vigente->id);
    }

    public function test_the_subjects_of_another_student_are_not_mixed(): void
    {
        $estudiante = $this->estudiante();
        $otro = $this->estudiante(['codigo_universitario' => '201901349', 'documento_identidad' => '6492819']);

        Inscripcion::create([
            'estudiante_id' => $otro->id,
            'grupo_id' => $this->grupo()->id,
            'via' => OrigenEstudiante::Administracion,
        ]);

        $this->actingAs($this->administrador())
            ->getJson("/api/estudiantes/{$estudiante->id}")
            ->assertOk()
            ->assertJsonPath('data.materias', []);
    }

    public function test_a_student_that_does_not_exist_is_not_found(): void
    {
        $this->actingAs($this->administrador())
            ->getJson('/api/estudiantes/999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Estudiante no encontrado.');
    }

    public function test_a_guest_cannot_see_the_record(): void
    {
        $estudiante = $this->estudiante();

        $this->getJson("/api/estudiantes/{$estudiante->id}")->assertUnauthorized();
    }

    public function test_a_teacher_cannot_see_the_record(): void
    {
        $estudiante = $this->estudiante();

        $this->actingAs($this->cuentaDe($this->docente()))
            ->getJson("/api/estudiantes/{$estudiante->id}")
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'padron_estudiantes');
    }
}
