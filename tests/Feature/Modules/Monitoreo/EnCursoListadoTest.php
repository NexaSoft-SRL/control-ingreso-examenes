<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Monitoreo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El selector de «En curso» y el destino de «Mi aula» (ruta 70): los
 * examenes de hoy y los del proximo dia con examen. El docente, con todas
 * sus aulas; el auxiliar, solo con las que atiende.
 */
final class EnCursoListadoTest extends TestCase
{
    use EscenarioDeSeguimiento;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detenerReloj();
    }

    public function test_a_teacher_gets_the_exams_of_today_and_of_the_next_day_with_exams(): void
    {
        $docente = $this->docenteQueSigue();
        $primera = $this->aula('691A');
        $segunda = $this->aula('691B');

        $ayer = $this->examen($docente, [$this->grupo($docente)], [$primera], [
            'fecha' => now()->subDay()->toDateString(),
        ]);
        $tarde = $this->examen($docente, [$this->grupo($docente)], [$primera], [
            'hora_inicio' => '16:00:00',
        ]);
        $manana = $this->examen($docente, [$this->grupo($docente)], [$primera, $segunda]);
        $proximo = $this->examen($docente, [$this->grupo($docente)], [], [
            'fecha' => now()->addDays(3)->toDateString(),
        ]);
        $lejano = $this->examen($docente, [$this->grupo($docente)], [$primera], [
            'fecha' => now()->addDays(5)->toDateString(),
        ]);

        $respuesta = $this->actingAs($this->cuentaDe($docente))
            ->getJson('/api/en-curso')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.examen_id', $manana->id)
            ->assertJsonPath('data.0.aulas', [
                ['aula_id' => $primera->id, 'nombre' => '691A'],
                ['aula_id' => $segunda->id, 'nombre' => '691B'],
            ])
            ->assertJsonPath('data.1.examen_id', $tarde->id)
            ->assertJsonPath('data.2.examen_id', $proximo->id)
            ->assertJsonPath('data.2.aulas', []);

        $ids = array_column((array) $respuesta->json('data'), 'examen_id');

        $this->assertNotContains($ayer->id, $ids);
        $this->assertNotContains($lejano->id, $ids);
    }

    public function test_each_exam_carries_its_text_its_time_and_whether_it_is_open(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 12)->setTimeFromTimeString('08:21:07'));

        $docente = $this->docenteQueSigue();
        $asignatura = $this->asignatura('Introducción a la Programación');
        $this->examen($docente, [$this->grupo($docente, $asignatura)], [$this->aula()], [
            'hora_inicio' => '08:15:00',
        ]);
        $this->examen($docente, [$this->grupo($docente, $this->asignatura('Cálculo I'))], [], [
            'hora_inicio' => '16:00:00',
            'tipo' => 'FINAL',
        ]);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson('/api/en-curso')
            ->assertOk()
            ->assertJsonPath('data.0.texto', '12 oct · Primer parcial · Introducción a la Programación')
            ->assertJsonPath('data.0.asignatura', 'Introducción a la Programación')
            ->assertJsonPath('data.0.tipo', 'PRIMER_PARCIAL')
            ->assertJsonPath('data.0.tipo_texto', 'Primer parcial')
            ->assertJsonPath('data.0.fecha', '2026-10-12')
            ->assertJsonPath('data.0.hora', '08:15')
            ->assertJsonPath('data.0.abierto', true)
            ->assertJsonPath('data.1.texto', '12 oct · Examen final · Cálculo I')
            ->assertJsonPath('data.1.tipo', 'FINAL')
            ->assertJsonPath('data.1.tipo_texto', 'Examen final')
            ->assertJsonPath('data.1.abierto', false);
    }

    public function test_the_next_day_is_listed_even_without_exams_today(): void
    {
        $docente = $this->docenteQueSigue();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$this->aula()], [
            'fecha' => now()->addDays(2)->toDateString(),
        ]);

        $this->actingAs($this->cuentaDe($docente))
            ->getJson('/api/en-curso')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.examen_id', $examen->id)
            ->assertJsonPath('data.0.abierto', false);
    }

    public function test_a_teacher_of_an_included_group_gets_the_exam_but_not_the_exams_of_others(): void
    {
        $registra = $this->docenteQueSigue();
        $sumado = $this->docenteQueSigue();
        $asignatura = $this->asignatura();
        $aula = $this->aula();
        $conjunto = $this->examen(
            $registra,
            [$this->grupo($registra, $asignatura), $this->grupo($sumado, $asignatura)],
            [$aula],
        );
        $this->examen($registra, [$this->grupo($registra)], [$aula]);

        $this->actingAs($this->cuentaDe($sumado))
            ->getJson('/api/en-curso')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.examen_id', $conjunto->id)
            ->assertJsonPath('data.0.aulas.0.aula_id', $aula->id);
    }

    public function test_an_assistant_gets_only_the_rooms_they_attend(): void
    {
        $docente = $this->docenteQueSigue();
        $propia = $this->aula('691A');
        $ajena = $this->aula('691B');
        $deApoyo = $this->aula('617');
        $hoy = $this->examen($docente, [$this->grupo($docente)], [$propia, $ajena]);
        $proximo = $this->examen($docente, [$this->grupo($docente)], [$ajena, $deApoyo], [
            'fecha' => now()->addDay()->toDateString(),
        ]);
        $this->examen($docente, [$this->grupo($docente)], [$ajena]);

        $auxiliar = $this->auxiliarQueSigue();
        $this->ponerACargo($hoy, $propia, $auxiliar);
        $this->ponerDeApoyo($proximo, $deApoyo, $auxiliar);

        $this->actingAs($auxiliar)
            ->getJson('/api/en-curso')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.examen_id', $hoy->id)
            ->assertJsonPath('data.0.aulas', [['aula_id' => $propia->id, 'nombre' => '691A']])
            ->assertJsonPath('data.1.examen_id', $proximo->id)
            ->assertJsonPath('data.1.aulas', [['aula_id' => $deApoyo->id, 'nombre' => '617']]);
    }

    public function test_an_account_without_exams_or_rooms_gets_an_empty_list(): void
    {
        $docente = $this->docenteQueSigue();
        $this->examen($docente, [$this->grupo($docente)], [$this->aula()]);

        $this->actingAs($this->auxiliarQueSigue())
            ->getJson('/api/en-curso')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }
}
