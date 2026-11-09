<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Estudiantes\Domain\Models\Inscripcion;
use App\Modules\Examenes\Domain\Models\Examen;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rutas 62 y 63: las aulas del puesto y la cabecera de la puerta, que
 * trabaja por aula y hora y puede tener dos examenes a la vez.
 */
final class PuertaTest extends TestCase
{
    use EscenarioDePuerta;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->armarExamen();
    }

    public function test_a_teacher_lists_the_rooms_of_todays_exams(): void
    {
        $otra = $this->aula('617');
        $this->sumarAula($otra);
        $manana = $this->examen($this->docente, [$this->grupo($this->docente)], [$this->aula('999')], [
            'fecha' => '2026-10-13',
        ]);

        $this->actingAs($this->cuentaDocente)
            ->getJson('/api/puerta/aulas')
            ->assertOk()
            ->assertJsonPath('hoy', '2026-10-12')
            ->assertJsonPath('acceso', null)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.aula_id', $otra->id)
            ->assertJsonPath('data.0.nombre', '617')
            ->assertJsonPath('data.0.ubicacion', null)
            ->assertJsonPath('data.1.aula_id', $this->aula->id)
            ->assertJsonPath('data.1.ubicacion', 'Edificio Académico 2 · 1° Piso')
            ->assertJsonPath('data.1.abierta', true)
            ->assertJsonPath('data.1.examenes.0.id', $this->examen->id)
            ->assertJsonPath('data.1.examenes.0.texto', 'Primer parcial · Introducción a la Programación')
            ->assertJsonPath('data.1.examenes.0.hora', '08:15');

        $this->assertNotSame($manana->id, $this->examen->id);
    }

    public function test_an_assistant_lists_only_the_rooms_it_attends_today(): void
    {
        $otra = $this->aula('617');
        $this->sumarAula($otra);
        $auxiliar = $this->cuentaAuxiliar();
        $this->ponerACargo($this->examen, $otra, $auxiliar);

        $this->actingAs($auxiliar)
            ->getJson('/api/puerta/aulas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.aula_id', $otra->id);
    }

    public function test_a_room_is_listed_closed_before_its_window(): void
    {
        $this->fijarHora('2026-10-12 06:00:00');

        $this->actingAs($this->cuentaDocente)
            ->getJson('/api/puerta/aulas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.abierta', false);
    }

    public function test_the_room_list_requires_a_session_and_the_permission(): void
    {
        $this->getJson('/api/puerta/aulas')->assertUnauthorized();

        $this->actingAs(UserFactory::new()->createOne())
            ->getJson('/api/puerta/aulas')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'punto_control');
    }

    public function test_the_door_shows_the_room_its_exams_and_the_latest_entries(): void
    {
        $this->examen->update(['normas' => 'Sin celular. Hoja de fórmulas A4.']);
        $estudiantes = $this->habilitados(7);

        foreach (array_slice($estudiantes, 0, 6) as $indice => $estudiante) {
            $this->travel(1)->seconds();
            $this->ingresar($this->examen, $estudiante, $this->aula);
        }

        $ultimo = $estudiantes[5];

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/puerta/aulas/{$this->aula->id}")
            ->assertOk()
            ->assertJsonPath('aula.id', $this->aula->id)
            ->assertJsonPath('aula.nombre', '691A')
            ->assertJsonPath('aula.ubicacion', 'Edificio Académico 2 · 1° Piso')
            ->assertJsonPath('aula.edificio_id', $this->aula->edificio_id)
            ->assertJsonPath('hora_servidor', $this->iso('2026-10-12 08:20:06'))
            ->assertJsonPath('abierta', true)
            ->assertJsonCount(1, 'examenes')
            ->assertJsonPath('examenes.0.id', $this->examen->id)
            ->assertJsonPath('examenes.0.asignatura', 'Introducción a la Programación')
            ->assertJsonPath('examenes.0.tipo', 'Primer parcial')
            ->assertJsonPath('examenes.0.hora', '08:15')
            ->assertJsonPath('examenes.0.ingresados', 6)
            ->assertJsonPath('examenes.0.asignados', 7)
            ->assertJsonPath('examenes.0.normas', 'Sin celular. Hoja de fórmulas A4.')
            ->assertJsonCount(5, 'ingresos')
            ->assertJsonPath('total_ingresos', 6)
            ->assertJsonPath('ingresos.0.estudiante_id', $ultimo->id)
            ->assertJsonPath('ingresos.0.codigo', $ultimo->codigo_universitario)
            ->assertJsonPath('ingresos.0.nombre', $ultimo->apellidos.' '.$ultimo->nombres)
            ->assertJsonPath('ingresos.0.hora', '08:20')
            ->assertJsonPath('ingresos.0.examen_id', $this->examen->id)
            ->assertJsonPath('ingresos.0.excepcion', false)
            ->assertJsonPath('ingresos.0.expulsado', false);
    }

    public function test_all_entries_can_be_listed_and_searched(): void
    {
        $estudiantes = $this->habilitados(7);

        foreach ($estudiantes as $estudiante) {
            $this->ingresar($this->examen, $estudiante, $this->aula);
        }

        $estudiantes[2]->update(['apellidos' => 'Aguilar Cossío', 'nombres' => 'Mariana']);

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/puerta/aulas/{$this->aula->id}?ingresos=todos")
            ->assertOk()
            ->assertJsonCount(7, 'ingresos');

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/puerta/aulas/{$this->aula->id}?ingresos=todos&buscar=cossio")
            ->assertOk()
            ->assertJsonCount(1, 'ingresos')
            ->assertJsonPath('ingresos.0.nombre', 'Aguilar Cossío Mariana')
            ->assertJsonPath('total_ingresos', 7);

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/puerta/aulas/{$this->aula->id}?ingresos=todos&buscar=".$estudiantes[4]->codigo_universitario)
            ->assertOk()
            ->assertJsonCount(1, 'ingresos')
            ->assertJsonPath('ingresos.0.estudiante_id', $estudiantes[4]->id);
    }

    public function test_the_door_rejects_an_unknown_entries_mode(): void
    {
        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/puerta/aulas/{$this->aula->id}?ingresos=ninguno")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ingresos']);
    }

    public function test_a_closed_door_shows_todays_exams_without_opening(): void
    {
        // Finalizado por el docente: la puerta ya no abre.
        $this->examen->forceFill(['finalizado_en' => '2026-10-12 10:00:00'])->save();
        $this->fijarHora('2026-10-12 11:00:00');

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/puerta/aulas/{$this->aula->id}")
            ->assertOk()
            ->assertJsonPath('abierta', false)
            ->assertJsonCount(1, 'examenes')
            ->assertJsonPath('examenes.0.id', $this->examen->id);
    }

    public function test_a_room_with_two_exams_at_once_shows_both_with_their_own_figures(): void
    {
        [$otroExamen, $otroGrupo] = $this->examenCompartido();
        $propios = $this->habilitados(3);
        $ajenos = $this->habilitados(2, $this->aula, $otroExamen, $otroGrupo);
        $this->ingresar($this->examen, $propios[0], $this->aula);
        $this->ingresar($otroExamen, $ajenos[0], $this->aula);

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/puerta/aulas/{$this->aula->id}")
            ->assertOk()
            ->assertJsonCount(2, 'examenes')
            ->assertJsonPath('examenes.0.id', $this->examen->id)
            ->assertJsonPath('examenes.0.ingresados', 1)
            ->assertJsonPath('examenes.0.asignados', 3)
            ->assertJsonPath('examenes.1.id', $otroExamen->id)
            ->assertJsonPath('examenes.1.asignatura', 'Cálculo I')
            ->assertJsonPath('examenes.1.ingresados', 1)
            ->assertJsonPath('examenes.1.asignados', 2)
            ->assertJsonPath('total_ingresos', 2);
    }

    public function test_the_door_resolves_the_exam_of_each_student_in_a_shared_room(): void
    {
        [$otroExamen, $otroGrupo] = $this->examenCompartido();
        [$propio] = $this->habilitados(1);
        [$ajeno] = $this->habilitados(1, $this->aula, $otroExamen, $otroGrupo);

        $this->verificar($this->cuentaDocente, $propio->codigo_universitario)
            ->assertJsonPath('veredicto', 'admitido')
            ->assertJsonPath('examen.id', $this->examen->id);

        $this->verificar($this->cuentaDocente, $ajeno->codigo_universitario)
            ->assertJsonPath('veredicto', 'admitido')
            ->assertJsonPath('examen.id', $otroExamen->id)
            ->assertJsonPath('examen.asignatura', 'Cálculo I');

        $this->assertDatabaseHas('ingresos', ['examen_id' => $this->examen->id, 'estudiante_id' => $propio->id]);
        $this->assertDatabaseHas('ingresos', ['examen_id' => $otroExamen->id, 'estudiante_id' => $ajeno->id]);
    }

    public function test_a_student_in_both_exams_of_the_room_enters_the_one_without_entry(): void
    {
        [$otroExamen, $otroGrupo] = $this->examenCompartido();
        [$estudiante] = $this->habilitados(1);
        Inscripcion::create([
            'estudiante_id' => $estudiante->id,
            'grupo_id' => $otroGrupo->id,
            'via' => 'ADMINISTRACION',
        ]);
        $this->habilitar($otroExamen, [$estudiante], $this->aula);

        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
            ->assertJsonPath('veredicto', 'admitido')
            ->assertJsonPath('examen.id', $this->examen->id);

        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
            ->assertJsonPath('veredicto', 'admitido')
            ->assertJsonPath('examen.id', $otroExamen->id);

        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
            ->assertJsonPath('veredicto', 'ya_ingreso');

        $this->assertDatabaseCount('ingresos', 2);
    }

    public function test_with_qr_the_exam_is_the_one_of_the_token(): void
    {
        [$otroExamen, $otroGrupo] = $this->examenCompartido();
        [$estudiante] = $this->habilitados(1);
        Inscripcion::create([
            'estudiante_id' => $estudiante->id,
            'grupo_id' => $otroGrupo->id,
            'via' => 'ADMINISTRACION',
        ]);
        $this->habilitar($otroExamen, [$estudiante], $this->aula);
        $token = $this->emitirCodigo($estudiante, $otroExamen);

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/puerta/aulas/{$this->aula->id}/verificaciones", ['token' => $token])
            ->assertJsonPath('veredicto', 'admitido')
            ->assertJsonPath('examen.id', $otroExamen->id);
    }

    public function test_an_assistant_cannot_open_the_door_of_a_room_it_does_not_attend(): void
    {
        $this->actingAs($this->cuentaAuxiliar())
            ->getJson("/api/puerta/aulas/{$this->aula->id}")
            ->assertForbidden()
            ->assertJsonPath('alcance', true)
            ->assertJsonPath('message', 'Esta aula no está a su cargo.');
    }

    public function test_an_assistant_attends_only_on_the_day_of_the_exam(): void
    {
        $auxiliar = $this->cuentaAuxiliar();
        $this->ponerACargo($this->examen, $this->aula, $auxiliar);

        $this->actingAs($auxiliar)
            ->getJson("/api/puerta/aulas/{$this->aula->id}")
            ->assertOk();

        $this->fijarHora('2026-10-13 08:20:00');

        $this->actingAs($auxiliar)
            ->getJson("/api/puerta/aulas/{$this->aula->id}")
            ->assertForbidden()
            ->assertJsonPath('alcance', true);

        $this->actingAs($auxiliar)
            ->getJson('/api/puerta/aulas')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_door_of_an_unknown_room_is_not_found(): void
    {
        $this->actingAs($this->cuentaDocente)
            ->getJson('/api/puerta/aulas/999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Aula no encontrada.');
    }

    public function test_the_door_requires_a_session_and_the_permission(): void
    {
        $this->getJson("/api/puerta/aulas/{$this->aula->id}")->assertUnauthorized();

        $this->actingAs(UserFactory::new()->createOne())
            ->getJson("/api/puerta/aulas/{$this->aula->id}")
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'punto_control');
    }

    /**
     * Otro examen, de otro docente y otra asignatura, en la misma aula y a
     * la misma hora.
     *
     * @return array{Examen, Grupo}
     */
    private function examenCompartido(): array
    {
        $otroDocente = $this->docenteConCuenta($this->docenteAjenoSinDocente());
        $otroGrupo = $this->grupo($otroDocente, $this->asignatura('Cálculo I'));

        return [$this->examen($otroDocente, [$otroGrupo], [$this->aula]), $otroGrupo];
    }

    private function docenteAjenoSinDocente(): User
    {
        return $this->usuarioConPermisos(['codigos_qr', 'punto_control', 'seguimiento_vivo']);
    }
}
