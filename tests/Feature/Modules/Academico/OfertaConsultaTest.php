<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Academico;

use App\Modules\Academico\Domain\Enums\RegimenCarrera;
use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class OfertaConsultaTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use OfertaDePrueba;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_careers_are_listed_by_name_and_filtered_by_faculty(): void
    {
        $fcyt = $this->facultad();
        $fach = $this->facultad('fach');
        $sistemas = $this->carrera($fcyt, 'Licenciatura en Ingeniería de Sistemas');
        $this->carrera($fcyt, 'Licenciatura en Física');
        $this->carrera($fach, 'Arquitectura', RegimenCarrera::Anual);

        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        $sesion->getJson('/api/oferta/carreras?facultad=fcyt')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Licenciatura en Física', 'Licenciatura en Ingeniería de Sistemas'])
            ->assertJsonPath('data.1', [
                'id' => $sistemas->id,
                'codigo' => $sistemas->codigo,
                'nombre' => 'Licenciatura en Ingeniería de Sistemas',
                'regimen' => 'Semestral',
            ]);

        $sesion->getJson('/api/oferta/carreras?facultad=FACH')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.regimen', 'Anual');

        $sesion->getJson('/api/oferta/carreras')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_careers_reject_an_unknown_faculty(): void
    {
        $this->facultad();

        $this->actingAs($this->usuarioConPermisos(['periodo_oferta']))
            ->getJson('/api/oferta/carreras?facultad=fcjp')
            ->assertUnprocessable()
            ->assertJsonPath('errors.facultad.0', 'La facultad indicada no existe.');
    }

    public function test_careers_accept_either_the_offer_or_the_roster_permission(): void
    {
        $this->getJson('/api/oferta/carreras')->assertUnauthorized();

        $this->actingAs($this->usuarioConPermisos(['padron_estudiantes']))
            ->getJson('/api/oferta/carreras')
            ->assertOk();

        $this->actingAs($this->usuarioConRol('Docente'))
            ->getJson('/api/oferta/carreras')
            ->assertForbidden()
            ->assertJsonPath('message', 'Este rol no tiene permiso para esta sección.')
            ->assertJsonPath('permiso_requerido', 'periodo_oferta');
    }

    public function test_subjects_show_their_level_groups_and_enrolled_students(): void
    {
        $fcyt = $this->facultad();
        $sistemas = $this->carrera($fcyt, 'Ingeniería de Sistemas');
        $algebra = $this->asignatura('Álgebra I', '2008019');
        $calculo = $this->asignatura('Cálculo I', '2008054');
        $this->enPlan($sistemas, $algebra, 'SEMESTRE 1');
        $this->enPlan($sistemas, $calculo, 'SEMESTRE 2');

        $grupo = $this->grupo(null, $algebra, '1');
        $this->grupo(null, $algebra, '2');
        $this->grupo(null, $calculo, '1');
        $this->estudiantesInscritos($grupo, 3);
        // Sin grupos no esta en la oferta.
        $this->asignatura('Sin Grupos', '999');

        $this->actingAs($this->usuarioConPermisos(['periodo_oferta']))
            ->getJson('/api/oferta/asignaturas?facultad=fcyt')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0', [
                'id' => $algebra->id,
                'codigo' => '2008019',
                'nombre' => 'Álgebra I',
                'nivel' => 'Semestre 1',
                'grupos' => 2,
                'inscritos' => 3,
            ])
            ->assertJsonPath('data.1.nombre', 'Cálculo I')
            ->assertJsonPath('data.1.inscritos', 0)
            ->assertJsonPath('meta', [
                'total' => 2,
                'pagina' => 1,
                'por_pagina' => 15,
                'grupos' => 3,
                'niveles' => ['Semestre 1', 'Semestre 2'],
            ]);
    }

    public function test_subjects_with_a_group_without_list_are_filtered(): void
    {
        $this->facultad();
        $algebra = $this->asignatura('Álgebra I', '2008019');
        $calculo = $this->asignatura('Cálculo I', '2008054');
        $this->estudiantesInscritos($this->grupo(null, $algebra, '1'), 2);
        $this->grupo(null, $algebra, '2');
        $this->estudiantesInscritos($this->grupo(null, $calculo, '1'), 2);

        $this->actingAs($this->usuarioConPermisos(['periodo_oferta']))
            ->getJson('/api/oferta/asignaturas?sin_lista=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Álgebra I');
    }

    public function test_subjects_are_filtered_by_period_faculty_career_and_level(): void
    {
        $vigente = $this->periodoVigente();
        $anterior = $this->periodo('1/2020', -900, -800, TipoPeriodo::Semestre1);
        $fcyt = $this->facultad();
        $fce = $this->facultad('fce');
        $sistemas = $this->carrera($fcyt, 'Ingeniería de Sistemas');
        $civil = $this->carrera($fcyt, 'Ingeniería Civil');

        $algebra = $this->asignatura('Álgebra I');
        $fisica = $this->asignatura('Física I');
        $conta = $this->asignatura('Contabilidad');
        $historia = $this->asignatura('Historia');
        $this->enPlan($sistemas, $algebra, 'SEMESTRE 1');
        $this->enPlan($civil, $algebra, 'SEMESTRE 2');
        $this->enPlan($civil, $fisica, 'SEMESTRE 1');

        $this->grupo(null, $algebra, '1', $vigente, $fcyt);
        $this->grupo(null, $fisica, '1', $vigente, $fcyt);
        $this->grupo(null, $conta, '1', $vigente, $fce);
        $this->grupo(null, $historia, '1', $anterior, $fcyt);

        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        // Sin periodo: los vigentes.
        $sesion->getJson('/api/oferta/asignaturas')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Álgebra I', 'Contabilidad', 'Física I']);

        $sesion->getJson("/api/oferta/asignaturas?periodo={$anterior->id}")
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Historia']);

        $sesion->getJson('/api/oferta/asignaturas?facultad=fce')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Contabilidad'])
            ->assertJsonPath('data.0.nivel', null)
            ->assertJsonPath('meta.niveles', []);

        $sesion->getJson("/api/oferta/asignaturas?facultad=fcyt&carrera={$civil->id}")
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Álgebra I', 'Física I'])
            ->assertJsonPath('data.0.nivel', 'Semestre 2')
            ->assertJsonPath('meta.niveles', ['Semestre 1', 'Semestre 2']);

        $sesion->getJson("/api/oferta/asignaturas?facultad=fcyt&carrera={$civil->id}&nivel=SEMESTRE 1")
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Física I'])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.grupos', 1)
            // El filtro de nivel no achica la lista de niveles que ofrece.
            ->assertJsonPath('meta.niveles', ['Semestre 1', 'Semestre 2']);

        $sesion->getJson('/api/oferta/asignaturas?facultad=fcyt&nivel=Semestre 1')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Álgebra I', 'Física I']);
    }

    public function test_subjects_are_searched_by_name_code_or_teacher_ignoring_accents(): void
    {
        $algebra = $this->asignatura('Álgebra I', '2008019');
        $calculo = $this->asignatura('Cálculo I', '2008054');
        $this->grupo($this->docenteSinCuenta('Taborga Acha Fidel'), $algebra, '5');
        $this->grupo($this->docenteSinCuenta('Peñaranda Muñoz José'), $calculo, '1');

        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        $sesion->getJson('/api/oferta/asignaturas?buscar=ALGEBRA')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Álgebra I']);

        $sesion->getJson('/api/oferta/asignaturas?buscar=8054')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Cálculo I']);

        $sesion->getJson('/api/oferta/asignaturas?buscar=penaranda munoz')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Cálculo I']);

        $sesion->getJson('/api/oferta/asignaturas?buscar=100%')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.grupos', 0);
    }

    public function test_subjects_are_paginated_on_the_server(): void
    {
        foreach (['A', 'B', 'C', 'D'] as $letra) {
            $this->grupo(null, $this->asignatura('Materia '.$letra));
        }

        $this->actingAs($this->usuarioConPermisos(['periodo_oferta']))
            ->getJson('/api/oferta/asignaturas?pagina=2&por_pagina=3')
            ->assertOk()
            ->assertJsonPath('data.*.nombre', ['Materia D'])
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.pagina', 2)
            ->assertJsonPath('meta.por_pagina', 3)
            ->assertJsonPath('meta.grupos', 4);
    }

    public function test_subjects_validate_their_filters(): void
    {
        $this->actingAs($this->usuarioConPermisos(['periodo_oferta']))
            ->getJson('/api/oferta/asignaturas?periodo=abc&carrera=0&facultad=nada&por_pagina=500')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['periodo', 'carrera', 'facultad', 'por_pagina'])
            ->assertJsonPath('errors.periodo.0', 'El campo período debe ser un número entero.');
    }

    public function test_subjects_require_session_and_permission(): void
    {
        $this->getJson('/api/oferta/asignaturas')->assertUnauthorized();

        $this->actingAs($this->usuarioConPermisos(['padron_estudiantes']))
            ->getJson('/api/oferta/asignaturas')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'periodo_oferta');
    }

    public function test_groups_of_a_subject_show_teacher_schedule_and_enrolled_students(): void
    {
        $algebra = $this->asignatura('Álgebra I');
        $docente = $this->docenteSinCuenta('Taborga Acha Fidel');
        $aula = $this->aula('617');
        $grupo = $this->grupo($docente, $algebra, '5');
        $porDesignar = $this->grupo(null, $algebra, '10');
        $this->grupo(null, $algebra, '2');
        $this->horario($grupo, 'VI', '08:15', '09:45');
        $this->horario($grupo, 'MI', '14:15', '15:45', $aula);
        $this->estudiantesInscritos($grupo, 2);

        $this->actingAs($this->usuarioConPermisos(['periodo_oferta']))
            ->getJson("/api/oferta/asignaturas/{$algebra->id}/grupos")
            ->assertOk()
            // Orden natural: 2, 5, 10.
            ->assertJsonPath('data.*.codigo', ['2', '5', '10'])
            ->assertJsonPath('data.1', [
                'id' => $grupo->id,
                'codigo' => '5',
                'docente' => 'Taborga Acha Fidel',
                'horarios' => [
                    ['dia' => 'MI', 'hora' => '14:15-15:45', 'aula' => '617'],
                    ['dia' => 'VI', 'hora' => '08:15-09:45', 'aula' => null],
                ],
                'inscritos' => 2,
            ])
            ->assertJsonPath('data.2.id', $porDesignar->id)
            ->assertJsonPath('data.2.docente', null)
            ->assertJsonPath('data.2.horarios', [])
            ->assertJsonPath('meta', ['total' => 3, 'pagina' => 1, 'por_pagina' => 10]);
    }

    public function test_groups_of_a_subject_put_the_matching_teacher_first_and_filter_by_period_and_faculty(): void
    {
        $vigente = $this->periodoVigente();
        $anterior = $this->periodo('1/2020', -900, -800, TipoPeriodo::Semestre1);
        $fcyt = $this->facultad();
        $fce = $this->facultad('fce');
        $algebra = $this->asignatura('Álgebra I');

        $this->grupo($this->docenteSinCuenta('Arce Rojas Ana'), $algebra, '1', $vigente, $fcyt);
        $this->grupo($this->docenteSinCuenta('Núñez Vela Raúl'), $algebra, '2', $vigente, $fcyt);
        $this->grupo(null, $algebra, '3', $vigente, $fce);
        $this->grupo(null, $algebra, '9', $anterior, $fcyt);

        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        $sesion->getJson("/api/oferta/asignaturas/{$algebra->id}/grupos?buscar=nunez")
            ->assertOk()
            ->assertJsonPath('data.*.codigo', ['2', '1', '3'])
            ->assertJsonPath('meta.total', 3);

        $sesion->getJson("/api/oferta/asignaturas/{$algebra->id}/grupos?facultad=fcyt")
            ->assertOk()
            ->assertJsonPath('data.*.codigo', ['1', '2']);

        $sesion->getJson("/api/oferta/asignaturas/{$algebra->id}/grupos?periodo={$anterior->id}")
            ->assertOk()
            ->assertJsonPath('data.*.codigo', ['9']);

        $sesion->getJson("/api/oferta/asignaturas/{$algebra->id}/grupos?pagina=2&por_pagina=2")
            ->assertOk()
            ->assertJsonPath('data.*.codigo', ['3'])
            ->assertJsonPath('meta', ['total' => 3, 'pagina' => 2, 'por_pagina' => 2]);
    }

    public function test_groups_of_an_unknown_subject_are_not_found_and_filters_are_validated(): void
    {
        $algebra = $this->asignatura();
        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        $sesion->getJson('/api/oferta/asignaturas/999/grupos')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Asignatura no encontrada.']);

        $sesion->getJson("/api/oferta/asignaturas/{$algebra->id}/grupos?pagina=0")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pagina']);
    }

    public function test_groups_of_a_subject_require_session_and_permission(): void
    {
        $algebra = $this->asignatura();

        $this->getJson("/api/oferta/asignaturas/{$algebra->id}/grupos")->assertUnauthorized();

        $this->actingAs($this->usuarioConPermisos(['mis_grupos']))
            ->getJson("/api/oferta/asignaturas/{$algebra->id}/grupos")
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'periodo_oferta');
    }
}
