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
use Illuminate\Support\Facades\DB;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * El padron de la universidad y sus cifras (rutas 34 y 35).
 */
final class PadronTest extends TestCase
{
    use ApoyoDeCargas;
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_the_list_shows_each_student_with_its_faculty_career_groups_and_origin(): void
    {
        $carrera = $this->carrera();
        $estudiante = $this->estudiante([
            'nombres' => 'Mariana',
            'apellidos' => 'Aguilar Cossío',
            'carrera_id' => $carrera->id,
        ]);

        foreach ([$this->grupo(), $this->grupo()] as $grupo) {
            Inscripcion::create([
                'estudiante_id' => $estudiante->id,
                'grupo_id' => $grupo->id,
                'via' => OrigenEstudiante::Administracion,
            ]);
        }

        $this->actingAs($this->administrador())
            ->getJson('/api/estudiantes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0', [
                'id' => $estudiante->id,
                'codigo' => '202104821',
                'nombre' => 'Aguilar Cossío, Mariana',
                'documento' => '7928194',
                'facultad' => 'FCyT',
                'carrera' => 'Ing. Informática',
                'grupos' => 2,
                'origen' => 'Verificado',
            ])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.pagina', 1)
            ->assertJsonPath('meta.por_pagina', 25);
    }

    public function test_groups_only_count_enrollments_of_current_periods(): void
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

        foreach ([$this->grupo(), $this->grupo(null, null, null, $cerrado)] as $grupo) {
            Inscripcion::create([
                'estudiante_id' => $estudiante->id,
                'grupo_id' => $grupo->id,
                'via' => OrigenEstudiante::Administracion,
            ]);
        }

        $administrador = $this->administrador();

        $this->actingAs($administrador)->getJson('/api/estudiantes')->assertJsonPath('data.0.grupos', 1);
        $this->actingAs($administrador)->getJson('/api/estudiantes/resumen')->assertJsonPath('inscripciones', 1);
    }

    public function test_an_unverified_student_shows_teacher_as_origin(): void
    {
        $this->estudiante(['origen' => OrigenEstudiante::Docente, 'verificado' => false]);

        $this->actingAs($this->administrador())
            ->getJson('/api/estudiantes')
            ->assertJsonPath('data.0.origen', 'Docente');
    }

    public function test_the_list_is_ordered_by_surname_and_paginated_by_the_server(): void
    {
        $this->estudiantesInscritos($this->grupo(), 30);
        $administrador = $this->administrador();

        $primera = $this->actingAs($administrador)
            ->getJson('/api/estudiantes?por_pagina=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('meta.por_pagina', 10)
            ->assertJsonPath('data.0.nombre', 'Apellido 1, Nombre 1')
            ->assertJsonPath('data.1.nombre', 'Apellido 10, Nombre 10');

        $tercera = $this->actingAs($administrador)
            ->getJson('/api/estudiantes?por_pagina=10&pagina=3')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.pagina', 3);

        $this->assertNotSame($primera->json('data.0.id'), $tercera->json('data.0.id'));

        $this->actingAs($administrador)
            ->getJson('/api/estudiantes?por_pagina=10&pagina=4')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_search_ignores_case_and_accents_and_covers_name_code_and_document(): void
    {
        $this->estudiante(['nombres' => 'Mariana', 'apellidos' => 'Aguilar Cossío']);
        $this->estudiante([
            'codigo_universitario' => '201901349',
            'documento_identidad' => '6492819',
            'nombres' => 'Diego Andrés',
            'apellidos' => 'Núñez Zeballos',
        ]);

        $administrador = $this->administrador();

        foreach (['cossio' => '202104821', 'MARIANA aguilar' => '202104821', 'Aguilar Cossío, Mariana' => '202104821',
            'nunez' => '201901349', 'andres' => '201901349', '2019013' => '201901349', '649281' => '201901349'] as $texto => $codigo) {
            $this->actingAs($administrador)
                ->getJson('/api/estudiantes?buscar='.urlencode((string) $texto))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.codigo', $codigo);
        }

        $this->actingAs($administrador)
            ->getJson('/api/estudiantes?buscar='.urlencode('mariana zeballos'))
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        // Los comodines de LIKE se buscan como texto.
        $this->actingAs($administrador)
            ->getJson('/api/estudiantes?buscar='.urlencode('%'))
            ->assertJsonCount(0, 'data');
    }

    public function test_the_list_filters_by_faculty_and_career_and_counts_by_faculty(): void
    {
        $carrera = $this->carrera();
        $this->estudiante(['carrera_id' => $carrera->id]);
        $this->estudiante(['codigo_universitario' => '201901349', 'documento_identidad' => '1']);
        $this->estudiante([
            'codigo_universitario' => '201901350',
            'documento_identidad' => '2',
            'facultad_id' => $this->facultad('fce')->id,
        ]);

        $administrador = $this->administrador();

        $this->actingAs($administrador)
            ->getJson('/api/estudiantes?facultad=fcyt')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.conteos', ['todas' => 3, 'FCyT' => 2, 'FCE' => 1]);

        $this->actingAs($administrador)
            ->getJson('/api/estudiantes?facultad=fce')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.facultad', 'FCE');

        $this->actingAs($administrador)
            ->getJson("/api/estudiantes?carrera={$carrera->id}")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.codigo', '202104821');
    }

    public function test_the_list_validates_its_parameters(): void
    {
        $this->actingAs($this->administrador())
            ->getJson('/api/estudiantes?por_pagina=101&pagina=0&carrera=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'por_pagina' => 'El tamaño de página no puede pasar de 100.',
                'pagina' => 'La página debe ser 1 o más.',
                'carrera' => 'La carrera no es válida.',
            ]);
    }

    public function test_the_summary_counts_students_enrollments_teacher_loads_and_conflicts(): void
    {
        $grupo = $this->grupo();
        $estudiantes = $this->estudiantesInscritos($grupo, 3);
        $this->estudiante(['origen' => OrigenEstudiante::Docente, 'verificado' => false]);

        $administrador = $this->administrador();

        DB::table('conflictos_padron')->insert([
            'estudiante_id' => $estudiantes[0]->id,
            'grupo_id' => $grupo->id,
            'tipo' => 'DOCUMENTO_DISTINTO',
            'documento_nuevo' => '1',
            'nombres_nuevos' => 'N',
            'apellidos_nuevos' => 'A',
            'via' => 'DOCENTE',
            'estado' => 'PENDIENTE',
            'reportado_por' => $administrador->id,
        ]);

        $this->actingAs($administrador)
            ->getJson('/api/estudiantes/resumen')
            ->assertOk()
            ->assertExactJson([
                'estudiantes' => 4,
                'inscripciones' => 3,
                'cargados_por_docentes' => 1,
                'conflictos_pendientes' => 1,
            ]);
    }

    public function test_a_guest_cannot_see_the_padron(): void
    {
        $this->getJson('/api/estudiantes')->assertUnauthorized();
        $this->getJson('/api/estudiantes/resumen')->assertUnauthorized();
    }

    public function test_a_teacher_cannot_see_the_padron(): void
    {
        $cuenta = $this->cuentaDe($this->docente());

        $this->actingAs($cuenta)
            ->getJson('/api/estudiantes')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'padron_estudiantes');

        $this->actingAs($cuenta)->getJson('/api/estudiantes/resumen')->assertForbidden();
    }

    public function test_the_padron_has_no_routes_to_edit_a_student(): void
    {
        $estudiante = $this->estudiante();
        $administrador = $this->administrador();

        $this->actingAs($administrador)->postJson('/api/estudiantes', [])->assertStatus(405);
        $this->actingAs($administrador)->putJson("/api/estudiantes/{$estudiante->id}", [])->assertStatus(405);
        $this->actingAs($administrador)->deleteJson("/api/estudiantes/{$estudiante->id}")->assertStatus(405);
    }

    private function carrera(): Carrera
    {
        return Carrera::create([
            'facultad_id' => $this->facultad()->id,
            'codigo' => '419701',
            'nombre' => 'Ing. Informática',
            'regimen' => RegimenCarrera::Semestral,
        ]);
    }
}
