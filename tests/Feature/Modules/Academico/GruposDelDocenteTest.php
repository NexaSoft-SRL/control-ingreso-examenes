<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Academico;

use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class GruposDelDocenteTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use OfertaDePrueba;
    use RefreshDatabase;
    use UsuarioConPermisos;

    public function test_a_teacher_sees_only_their_groups_with_schedule_and_enrolled_students(): void
    {
        $cuenta = $this->usuarioConPermisos(['mis_grupos']);
        $docente = $this->docenteConCuenta($cuenta);
        $fcyt = $this->facultad();
        $periodo = $this->periodoVigente();

        $intro = $this->asignatura('Introducción a la Programación', '2010010');
        $taller = $this->asignatura('Taller de Ingeniería de Software', '2010020');
        $this->enPlan($this->carrera($fcyt, 'Ingeniería de Sistemas'), $intro, 'SEMESTRE 1');

        $conLista = $this->grupo($docente, $intro, '2');
        $this->grupo($docente, $intro, '10');
        $this->grupo($docente, $taller, '1');
        // De otro docente y sin docente: no son suyos.
        $this->grupo($this->docenteConCuenta(), $intro, '3');
        $this->grupo(null, $intro, '4');

        $this->horario($conLista, 'LU', '08:15', '09:45', $this->aula('691B'));
        $this->estudiantesInscritos($conLista, 3);

        $this->actingAs($cuenta)
            ->getJson('/api/docente/grupos')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0', [
                'id' => $conLista->id,
                'codigo' => '2',
                'asignatura' => [
                    'id' => $intro->id,
                    'codigo' => '2010010',
                    'nombre' => 'Introducción a la Programación',
                ],
                'nivel' => 'Semestre 1',
                'facultad' => 'FCyT',
                'periodo' => $periodo->codigo,
                'horarios' => [['dia' => 'LU', 'hora' => '08:15-09:45', 'aula' => '691B']],
                'inscritos' => 3,
                'con_lista' => true,
            ])
            ->assertJsonPath('data.1.codigo', '10')
            ->assertJsonPath('data.1.con_lista', false)
            ->assertJsonPath('data.1.inscritos', 0)
            ->assertJsonPath('data.2.asignatura.nombre', 'Taller de Ingeniería de Software')
            ->assertJsonPath('data.2.nivel', null)
            ->assertJsonPath('meta', [
                'periodo' => $periodo->codigo,
                'asignaturas' => 2,
                'sin_lista' => 2,
            ]);
    }

    public function test_groups_default_to_the_current_periods_and_accept_another_one(): void
    {
        $cuenta = $this->usuarioConPermisos(['mis_grupos']);
        $docente = $this->docenteConCuenta($cuenta);
        $anterior = $this->periodo('1/2020', -900, -800, TipoPeriodo::Semestre1);
        $anual = $this->periodo('0/2030', -100, 100, TipoPeriodo::Anual);

        $this->grupo($docente, null, '1');
        $this->grupo($docente, null, '2', $anual);
        $viejo = $this->grupo($docente, null, '9', $anterior);

        $sesion = $this->actingAs($cuenta);

        $sesion->getJson('/api/docente/grupos')
            ->assertOk()
            ->assertJsonPath('data.*.codigo', ['2', '1']);

        $sesion->getJson("/api/docente/grupos?periodo={$anterior->id}")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$viejo->id])
            ->assertJsonPath('meta.periodo', '1/2020');
    }

    public function test_an_account_that_is_not_a_teacher_has_no_groups(): void
    {
        $this->grupo($this->docenteConCuenta());

        $this->actingAs($this->usuarioConPermisos(['mis_grupos']))
            ->getJson('/api/docente/grupos')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.asignaturas', 0)
            ->assertJsonPath('meta.sin_lista', 0);
    }

    public function test_groups_validate_the_period(): void
    {
        $this->actingAs($this->usuarioConPermisos(['mis_grupos']))
            ->getJson('/api/docente/grupos?periodo=dos')
            ->assertUnprocessable()
            ->assertJsonPath('errors.periodo.0', 'El campo período debe ser un número entero.');
    }

    public function test_groups_require_session_and_permission(): void
    {
        $this->getJson('/api/docente/grupos')->assertUnauthorized();

        // El Administrador tiene todos los permisos (D-8): se prueba con el Auxiliar.
        $this->actingAs($this->usuarioConRol('Auxiliar'))
            ->getJson('/api/docente/grupos')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'mis_grupos');
    }
}
