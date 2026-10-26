<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Academico;

use App\Modules\Academico\Domain\Enums\RegimenCarrera;
use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DatosAcademicos;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class OfertaConsultaTest extends TestCase
{
    use DatosAcademicos;
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
            ->assertJsonPath('permiso_requerido', 'periodo_oferta|padron_estudiantes')
            ->assertJsonPath('rol', 'Docente');
    }

    public function test_offer_subjects_are_paginated_and_ordered_by_name(): void
    {
        $facultad = $this->facultad();
        $periodo = $this->periodo('2/2026', -10, 10);
        $docente = $this->docenteSinCuenta('Salazar Serrudo Carla');

        $a = $this->asignatura('Introducción a la Programación', '2010010');
        $b = $this->asignatura('Álgebra I', '2008053');
        $c = $this->asignatura('Aeropuertos', '2012043');

        foreach ([$a, $b, $c] as $asignatura) {
            $this->grupo($docente, $asignatura, null, $periodo, $facultad);
        }

        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        $respuesta = $sesion->getJson('/api/oferta/asignaturas?facultad=fcyt&por_pagina=2')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.pagina', 1)
            ->assertJsonPath('meta.por_pagina', 2);

        /** @var list<array{nombre: string}> $filas */
        $filas = (array) $respuesta->json('data');
        $nombres = array_column($filas, 'nombre');
        // Orden natural sin tildes: Aeropuertos, Álgebra I.
        $this->assertSame(['Aeropuertos', 'Álgebra I'], $nombres);

        $sesion->getJson('/api/oferta/asignaturas?facultad=fcyt&por_pagina=2&pagina=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Introducción a la Programación');
    }

    public function test_offer_subjects_can_be_filtered_by_period_faculty_career_and_level(): void
    {
        $fcyt = $this->facultad();
        $fce = $this->facultad('fce');

        $semestre = $this->periodo('2/2026', -10, 10);
        $anual = $this->periodo('0/2026', -100, 100, TipoPeriodo::Anual);

        $sistemas = $this->carrera($fcyt, 'Ingeniería de Sistemas');
        $civil = $this->carrera($fcyt, 'Ingeniería Civil');

        $programacion = $this->asignatura('Programación I', '2010010');
        $this->enPlan($sistemas, $programacion, 'SEMESTRE 1');
        $this->grupo(null, $programacion, null, $semestre, $fcyt);

        $algebra = $this->asignatura('Álgebra I', '2008053');
        $this->enPlan($civil, $algebra, 'SEMESTRE 1');
        $this->grupo(null, $algebra, null, $semestre, $fcyt);

        $anualAsignatura = $this->asignatura('Taller Anual', '2016006');
        $this->enPlan($sistemas, $anualAsignatura, 'AÑO 1');
        $this->grupo(null, $anualAsignatura, null, $anual, $fcyt);

        $otra = $this->asignatura('Otra Facultad', '9000001');
        $this->grupo(null, $otra, null, $semestre, $fce);

        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        // Por periodo.
        $sesion->getJson("/api/oferta/asignaturas?periodo={$anual->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Taller Anual');

        // Por facultad.
        $sesion->getJson('/api/oferta/asignaturas?facultad=fce')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Otra Facultad');

        // Por carrera.
        $sesion->getJson("/api/oferta/asignaturas?carrera={$civil->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Álgebra I');

        // Por nivel.
        $sesion->getJson('/api/oferta/asignaturas?nivel=SEMESTRE 1')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_offer_subjects_are_searched_by_name_code_or_teacher(): void
    {
        $facultad = $this->facultad();
        $periodo = $this->periodo('2/2026', -10, 10);
        $docente = $this->docenteSinCuenta('Salazar Serrudo Carla');
        $otro = $this->docenteSinCuenta('Blanco Coca Leticia');

        $this->grupo($docente, $this->asignatura('Introducción a la Programación', '2010010'), null, $periodo, $facultad);
        $this->grupo($otro, $this->asignatura('Álgebra I', '2008053'), null, $periodo, $facultad);

        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        // Por nombre, sin tildes ni mayúsculas.
        $sesion->getJson('/api/oferta/asignaturas?buscar=programacion')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Introducción a la Programación');

        // Por código.
        $sesion->getJson('/api/oferta/asignaturas?buscar=2008053')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Álgebra I');

        // Por docente.
        $sesion->getJson('/api/oferta/asignaturas?buscar=salazar')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Introducción a la Programación');
    }

    public function test_offer_subject_groups_are_paginated_and_ordered_by_code(): void
    {
        $facultad = $this->facultad();
        $periodo = $this->periodo('2/2026', -10, 10);
        $asignatura = $this->asignatura('Introducción a la Programación', '2010010');

        foreach (['3', '1', '2'] as $codigo) {
            $this->grupo(null, $asignatura, $codigo, $periodo, $facultad);
        }

        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        $respuesta = $sesion->getJson("/api/oferta/asignaturas/{$asignatura->id}/grupos?por_pagina=2")
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.por_pagina', 2);

        /** @var list<array{codigo: string}> $filas */
        $filas = (array) $respuesta->json('data');
        $codigos = array_column($filas, 'codigo');

    }

    public function test_offer_subject_groups_can_be_filtered_by_teacher_and_move_matches_first(): void
    {
        $facultad = $this->facultad();
        $periodo = $this->periodo('2/2026', -10, 10);
        $asignatura = $this->asignatura('Introducción a la Programación', '2010010');

        $salazar = $this->docenteSinCuenta('Salazar Serrudo Carla');
        $blanco = $this->docenteSinCuenta('Blanco Coca Leticia');

        $this->grupo($blanco, $asignatura, '2', $periodo, $facultad);
        $this->grupo($salazar, $asignatura, '1', $periodo, $facultad);

        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        $respuesta = $sesion->getJson(
            "/api/oferta/asignaturas/{$asignatura->id}/grupos?buscar=salazar"
        )->assertOk();

        $this->assertSame('Salazar Serrudo Carla', $respuesta->json('data.0.docente'));
    }

    public function test_offer_subject_groups_include_schedule_and_classroom(): void
    {
        $facultad = $this->facultad();
        $edificio = $this->edificio($facultad, 'Edificio Académico 2');
        $aula = $this->aula('691A', $edificio);
        $periodo = $this->periodo('2/2026', -10, 10);
        $asignatura = $this->asignatura('Introducción a la Programación', '2010010');
        $grupo = $this->grupo(null, $asignatura, null, $periodo, $facultad);

        $this->horario($grupo, 'JU', '11:15', '12:45', $aula);

        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        $sesion->getJson("/api/oferta/asignaturas/{$asignatura->id}/grupos")
            ->assertOk()
            ->assertJsonPath('data.0.horarios.0.dia', 'JU')
            ->assertJsonPath('data.0.horarios.0.hora', '11:15-12:45')
            ->assertJsonPath('data.0.horarios.0.aula', '691A');
    }

    public function test_offer_levels_are_listed_in_study_plan_order(): void
    {
        $facultad = $this->facultad();
        $periodo = $this->periodo('2/2026', -10, 10);
        $carrera = $this->carrera($facultad, 'Ingeniería de Sistemas');

        $s1 = $this->asignatura('Programación I', '2010010');
        $this->enPlan($carrera, $s1, 'SEMESTRE 1');
        $this->grupo(null, $s1, null, $periodo, $facultad);

        $s2 = $this->asignatura('Álgebra I', '2008053');
        $this->enPlan($carrera, $s2, 'SEMESTRE 2');
        $this->grupo(null, $s2, null, $periodo, $facultad);

        $a1 = $this->asignatura('Taller Anual', '2016006');
        $this->enPlan($carrera, $a1, 'AÑO 1');
        $this->grupo(null, $a1, null, $periodo, $facultad);

        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        $respuesta = $sesion->getJson('/api/oferta/niveles?facultad=fcyt')->assertOk();

        // Los años van primero, luego los semestres por número natural.
        $this->assertSame(['Año 1', 'Semestre 1', 'Semestre 2'], $respuesta->json('data'));
    }

    public function test_a_subject_shared_by_two_faculties_gets_one_row_per_faculty(): void
    {
        $fcyt = $this->facultad();
        $fhce = $this->facultad('fhce');
        $periodo = $this->periodo('2/2026', -10, 10);
        $didactica = $this->asignatura('Didáctica General', '1801033');

        $this->grupo(null, $didactica, '1', $periodo, $fhce);
        $this->grupo(null, $didactica, '2', $periodo, $fhce);
        $this->grupo(null, $didactica, '1', $periodo, $fcyt);
        $this->grupo(null, $this->asignatura('Álgebra I', '2008053'), '1', $periodo, $fcyt);

        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        // Con todas las facultades: una fila por asignatura y facultad, y el
        // total cuenta esas filas.
        $sesion->getJson('/api/oferta/asignaturas')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.total_grupos', 4)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.*.nombre', ['Álgebra I', 'Didáctica General', 'Didáctica General'])
            ->assertJsonPath('data.1.facultad', 'FCyT')
            ->assertJsonPath('data.1.facultad_clave', 'fcyt')
            ->assertJsonPath('data.1.grupos', 1)
            ->assertJsonPath('data.2.facultad', 'FHCE')
            ->assertJsonPath('data.2.facultad_clave', 'fhce')
            ->assertJsonPath('data.2.grupos', 2);

        $sesion->getJson('/api/oferta/asignaturas?buscar=didactica general')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data');

        // Los grupos de cada fila: solo los de su facultad.
        $sesion->getJson("/api/oferta/asignaturas/{$didactica->id}/grupos?facultad=fhce")
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $sesion->getJson("/api/oferta/asignaturas/{$didactica->id}/grupos?facultad=fcyt")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_offer_subject_groups_follow_the_period_faculty_and_search_filters(): void
    {
        $fcyt = $this->facultad();
        $fce = $this->facultad('fce');
        $vigente = $this->periodo('2/2026', -10, 10);
        $cerrado = $this->periodo('1/2026', -200, -100, TipoPeriodo::Semestre1);
        $calculo = $this->asignatura('Cálculo I', '2008054');

        $fiorilo = $this->docenteSinCuenta('Fiorilo Lozada Amilcar');
        $otro = $this->docenteSinCuenta('Blanco Coca Leticia');

        $this->grupo($otro, $calculo, '1', $vigente, $fcyt);
        $this->grupo($fiorilo, $calculo, '2', $vigente, $fcyt);
        $this->grupo($otro, $calculo, '3', $vigente, $fcyt);
        $this->grupo($fiorilo, $calculo, '4', $vigente, $fce);
        $this->grupo($otro, $calculo, '9', $cerrado, $fcyt);

        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));
        $grupos = "/api/oferta/asignaturas/{$calculo->id}/grupos";

        // Por defecto, solo los períodos vigentes.
        $sesion->getJson($grupos)->assertOk()->assertJsonPath('meta.total', 4);

        // Por período: el cerrado sigue consultable.
        $sesion->getJson("{$grupos}?periodo={$cerrado->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.codigo', '9');

        // Por facultad.
        $sesion->getJson("{$grupos}?facultad=fcyt")
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.*.codigo', ['1', '2', '3']);

        // Buscando un docente: solo sus grupos, y la fila cuenta los mismos.
        $sesion->getJson('/api/oferta/asignaturas?facultad=fcyt&buscar=fiorilo')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.grupos', 1);

        $sesion->getJson("{$grupos}?facultad=fcyt&buscar=fiorilo")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.*.codigo', ['2']);

        // Buscando la asignatura: todos sus grupos.
        $sesion->getJson("{$grupos}?facultad=fcyt&buscar=calculo")
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_without_a_current_period_the_default_offer_is_empty(): void
    {
        $facultad = $this->facultad();
        $carrera = $this->carrera($facultad, 'Ingeniería de Sistemas');
        $cerrado = $this->periodo('1/2026', -200, -100, TipoPeriodo::Semestre1);
        $asignatura = $this->asignatura('Programación I', '2010010');
        $this->enPlan($carrera, $asignatura, 'SEMESTRE 1');
        $this->grupo(null, $asignatura, '1', $cerrado, $facultad);

        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        // «Vigentes» sin ningún período vigente: nada, no todo.
        $sesion->getJson('/api/oferta/asignaturas')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.total_grupos', 0)
            ->assertJsonCount(0, 'data');

        $sesion->getJson("/api/oferta/asignaturas/{$asignatura->id}/grupos")
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $sesion->getJson('/api/oferta/niveles')->assertOk()->assertJsonCount(0, 'data');

        // El período cerrado, pedido por su id, sí se ve.
        $sesion->getJson("/api/oferta/asignaturas?periodo={$cerrado->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_offer_subjects_reject_invalid_filters_and_unknown_assignments(): void
    {
        $this->facultad();
        $this->asignatura('Introducción a la Programación', '2010010');

        $sesion = $this->actingAs($this->usuarioConPermisos(['periodo_oferta']));

        // Facultad inexistente.
        $sesion->getJson('/api/oferta/asignaturas?facultad=fcjp')
            ->assertUnprocessable()
            ->assertJsonPath('errors.facultad.0', 'La facultad indicada no existe.');

        // Período no numérico.
        $sesion->getJson('/api/oferta/asignaturas?periodo=abc')
            ->assertUnprocessable();

        // Carrera no numérica.
        $sesion->getJson('/api/oferta/asignaturas?carrera=abc')
            ->assertUnprocessable();

        // Búsqueda por encima de 100 caracteres.
        $sesion->getJson('/api/oferta/asignaturas?buscar='.str_repeat('a', 101))
            ->assertUnprocessable();

        // Asignatura inexistente en grupos.
        $sesion->getJson('/api/oferta/asignaturas/999999/grupos')
            ->assertNotFound()
            ->assertJsonPath('message', 'Asignatura no encontrada.');
    }

    public function test_offer_subjects_require_the_offer_permission(): void
    {
        $this->getJson('/api/oferta/asignaturas')->assertUnauthorized();

        $this->actingAs($this->usuarioConRol('Docente'))
            ->getJson('/api/oferta/asignaturas')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'periodo_oferta')
            ->assertJsonPath('rol', 'Docente');
    }
}
