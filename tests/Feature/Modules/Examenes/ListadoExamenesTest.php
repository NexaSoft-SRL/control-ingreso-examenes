<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use App\Modules\Academico\Domain\Models\Periodo;
use App\Modules\Estudiantes\Domain\Models\Inscripcion;
use App\Modules\Examenes\Domain\Enums\TipoExamen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El listado del docente (ruta 42): sus examenes con las cifras, el avance
 * de los cuatro pasos y el paso que sigue. Los codigos QR todavia no se
 * emiten: ese paso queda siempre pendiente.
 */
final class ListadoExamenesTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    public function test_the_teacher_sees_the_exams_registered_and_those_that_include_a_group(): void
    {
        $docente = $this->docente('Blanco Coca Leticia');
        $otro = $this->docente('Taborga Acha Fidel');
        $ajeno = $this->docente();
        $asignatura = $this->asignatura('Cálculo I', '2008054');

        $propio = $this->examen($docente, [$this->grupo($docente, $asignatura, '2')], [], [
            'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '08:15:00',
        ]);
        $sumado = $this->examen($otro, [
            $this->grupo($otro, $asignatura, '5'),
            $this->grupo($docente, $asignatura, '10'),
        ], [], [
            'tipo' => TipoExamen::Final,
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => '10:00:00',
        ]);
        $this->examen($ajeno, [$this->grupo($ajeno, $asignatura, '7')], [], ['tipo' => TipoExamen::SegundoParcial]);

        $respuesta = $this->actingAs($this->cuenta($docente))
            ->getJson('/api/examenes')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            // Por fecha y hora.
            ->assertJsonPath('data.0.id', $sumado->id)
            ->assertJsonPath('data.0.propio', false)
            ->assertJsonPath('data.0.registrado_por', 'Taborga Acha Fidel')
            ->assertJsonPath('data.0.tipo_texto', 'Examen final')
            ->assertJsonPath('data.0.grupos', ['5', '10'])
            ->assertJsonPath('data.0.hora', '10:00')
            ->assertJsonPath('data.1.id', $propio->id)
            ->assertJsonPath('data.1.propio', true)
            ->assertJsonPath('data.1.registrado_por', 'Blanco Coca Leticia')
            ->assertJsonPath('data.1.asignatura', ['id' => $asignatura->id, 'codigo' => '2008054', 'nombre' => 'Cálculo I'])
            ->assertJsonPath('data.1.duracion', 90)
            ->assertJsonPath('meta.periodo', $this->periodoVigente()->codigo)
            ->assertJsonPath('meta.hoy', now()->toDateString());

        $this->assertIsString($respuesta->json('meta.hora_servidor'));
        $this->assertStringEndsWith('-04:00', (string) $respuesta->json('meta.hora_servidor'));
        $this->assertArrayNotHasKey('normas', (array) $respuesta->json('data.0'));
    }

    public function test_progress_goes_from_missing_rooms_to_missing_qr_codes_and_never_to_ready(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);
        $estudiantes = $this->estudiantesInscritos($grupo, 4);
        $examen = $this->examen($docente, [$grupo]);
        $cuenta = $this->cuenta($docente);

        // 1. Sin aulas.
        $this->actingAs($cuenta)->getJson('/api/examenes')
            ->assertOk()
            ->assertJsonPath('data.0.inscritos', 4)
            ->assertJsonPath('data.0.sin_revisar', 4)
            ->assertJsonPath('data.0.avance', [
                'grupos' => 'ok', 'aulas' => 'ahora', 'habilitacion' => 'falta', 'qr' => 'falta',
            ])
            ->assertJsonPath('data.0.estado', 'Faltan aulas')
            ->assertJsonPath('data.0.accion', ['clave' => 'elegir_aulas', 'paso' => 2]);

        // 2. Con aulas, sin habilitar.
        $aula = $this->aula();
        $this->actingAs($cuenta)
            ->putJson("/api/examenes/{$examen->id}", $this->cuerpo($asignatura, [$grupo], [$aula], [
                'fecha' => $examen->fecha->format('Y-m-d'),
            ]))
            ->assertOk();

        $this->actingAs($cuenta)->getJson('/api/examenes')
            ->assertJsonPath('data.0.aulas', [$aula->nombre])
            ->assertJsonPath('data.0.avance', [
                'grupos' => 'ok', 'aulas' => 'ok', 'habilitacion' => 'ahora', 'qr' => 'falta',
            ])
            ->assertJsonPath('data.0.estado', 'Falta habilitar')
            ->assertJsonPath('data.0.accion', ['clave' => 'habilitar', 'paso' => null]);

        // 3. Habilitados a medias: sigue faltando habilitar.
        $this->habilitar($examen, [$estudiantes[0], $estudiantes[1]], $aula);
        $this->inhabilitar($examen, [$estudiantes[2]]);

        $this->actingAs($cuenta)->getJson('/api/examenes')
            ->assertJsonPath('data.0.habilitados', 2)
            ->assertJsonPath('data.0.no_habilitados', 1)
            ->assertJsonPath('data.0.sin_revisar', 1)
            ->assertJsonPath('data.0.avance.habilitacion', 'ahora')
            ->assertJsonPath('data.0.estado', 'Falta habilitar');

        // 4. Todos revisados: el paso de los codigos queda pendiente y es
        // el estado maximo.
        $this->habilitar($examen, [$estudiantes[3]], $aula);

        $respuesta = $this->actingAs($cuenta)->getJson('/api/examenes')
            ->assertJsonPath('data.0.habilitados', 3)
            ->assertJsonPath('data.0.sin_revisar', 0)
            ->assertJsonPath('data.0.qr_emitidos', 0)
            ->assertJsonPath('data.0.avance', [
                'grupos' => 'ok', 'aulas' => 'ok', 'habilitacion' => 'ok', 'qr' => 'ahora',
            ])
            ->assertJsonPath('data.0.estado', 'Faltan códigos QR')
            ->assertJsonPath('data.0.accion', ['clave' => 'emitir_qr', 'paso' => null]);

        $this->assertArrayNotHasKey('auxiliares', (array) $respuesta->json('data.0'));
    }

    public function test_nobody_enabled_is_not_a_finished_enabling(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $examen = $this->examen($docente, [$grupo], [$this->aula()]);
        $this->inhabilitar($examen, $this->estudiantesInscritos($grupo, 2));

        $this->actingAs($this->cuenta($docente))->getJson('/api/examenes')
            ->assertJsonPath('data.0.sin_revisar', 0)
            ->assertJsonPath('data.0.habilitados', 0)
            ->assertJsonPath('data.0.avance.habilitacion', 'ahora')
            ->assertJsonPath('data.0.avance.qr', 'falta')
            ->assertJsonPath('data.0.estado', 'Falta habilitar');
    }

    public function test_a_student_in_two_groups_of_the_exam_counts_once(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $uno = $this->grupo($docente, $asignatura);
        $dos = $this->grupo($docente, $asignatura);
        [$repetido] = $this->estudiantesInscritos($uno, 2);
        $this->estudiantesInscritos($dos, 1);
        Inscripcion::create(['estudiante_id' => $repetido->id, 'grupo_id' => $dos->id, 'via' => 'ADMINISTRACION']);
        $this->examen($docente, [$uno, $dos]);

        $this->actingAs($this->cuenta($docente))->getJson('/api/examenes')
            ->assertJsonPath('data.0.inscritos', 3)
            ->assertJsonPath('data.0.sin_revisar', 3);
    }

    public function test_exams_of_the_teacher_at_the_same_time_sharing_a_room_go_together(): void
    {
        $docente = $this->docente();
        $otro = $this->docente();
        $compartida = $this->aula();
        $aparte = $this->aula();
        $hora = ['fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '08:15:00'];

        $uno = $this->examen($docente, [$this->grupo($docente)], [$compartida], $hora);
        $dos = $this->examen($docente, [$this->grupo($docente)], [$compartida, $aparte], $hora);
        // Misma hora pero otra aula: no va junto.
        $tres = $this->examen($docente, [$this->grupo($docente)], [$aparte], $hora);
        // Misma aula a otra hora: tampoco.
        $this->examen($docente, [$this->grupo($docente)], [$compartida], ['fecha' => $hora['fecha'], 'hora_inicio' => '11:00:00']);
        // De otro docente: no es «del mismo docente».
        $this->examen($otro, [$this->grupo($otro)], [$compartida], $hora);

        $respuesta = $this->actingAs($this->cuenta($docente))->getJson('/api/examenes')->assertOk();

        /** @var array<int, int> $juntos */
        $juntos = collect((array) $respuesta->json('data'))->pluck('junto_con', 'id')->all();

        $this->assertSame(1, $juntos[$uno->id]);
        $this->assertSame(2, $juntos[$dos->id]);
        $this->assertSame(1, $juntos[$tres->id]);
        $this->assertSame([0, 1, 1, 2], collect($juntos)->sort()->values()->all());
    }

    public function test_today_and_already_taken_follow_the_server_clock(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();

        $this->travelTo(now()->setTime(12, 0));

        $enCurso = $this->examen($docente, [$this->grupo($docente, $asignatura)]);
        $terminado = $this->examen($docente, [$this->grupo($docente, $asignatura)], [], [
            'tipo' => TipoExamen::SegundoParcial,
            'hora_inicio' => '08:00:00',
            'duracion_minutos' => 60,
        ]);
        $futuro = $this->examen($docente, [$this->grupo($docente, $asignatura)], [], [
            'tipo' => TipoExamen::Final,
            'fecha' => now()->addDays(4)->toDateString(),
        ]);

        $respuesta = $this->actingAs($this->cuenta($docente))->getJson('/api/examenes')->assertOk();
        $datos = collect((array) $respuesta->json('data'));
        $hoy = $datos->pluck('es_hoy', 'id')->all();
        $rendido = $datos->pluck('rendido', 'id')->all();

        $this->assertSame([true, false], [$hoy[$enCurso->id], $rendido[$enCurso->id]]);
        $this->assertSame([true, true], [$hoy[$terminado->id], $rendido[$terminado->id]]);
        $this->assertSame([false, false], [$hoy[$futuro->id], $rendido[$futuro->id]]);
    }

    public function test_by_default_only_current_periods_are_listed_and_a_period_can_be_asked(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $vigente = $this->examen($docente, [$this->grupo($docente, $asignatura)]);

        $cerrado = Periodo::create([
            'codigo' => '1/2020',
            'anio' => 2020,
            'numero' => 1,
            'tipo' => TipoPeriodo::Semestre1,
            'fecha_inicio' => '2020-02-01',
            'fecha_fin' => '2020-07-01',
        ]);
        $viejo = $this->examen($docente, [$this->grupo($docente, $asignatura, null, $cerrado)], [], [
            'fecha' => '2020-05-05',
        ]);
        $cuenta = $this->cuenta($docente);

        $this->actingAs($cuenta)->getJson('/api/examenes')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $vigente->id);

        $this->actingAs($cuenta)->getJson("/api/examenes?periodo={$cerrado->id}")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $viejo->id)
            ->assertJsonPath('data.0.rendido', true)
            ->assertJsonPath('meta.periodo', '1/2020');

        $this->actingAs($cuenta)->getJson('/api/examenes?periodo=1/2020')
            ->assertJsonPath('data.0.id', $viejo->id);

        $this->actingAs($cuenta)->getJson('/api/examenes?periodo=999999')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.periodo', null);
    }

    public function test_the_period_filter_is_validated(): void
    {
        $this->actingAs($this->cuenta($this->docente()))
            ->getJson('/api/examenes?periodo=segundo')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['periodo' => 'El período no es válido.']);
    }

    public function test_a_teacher_without_exams_gets_an_empty_list(): void
    {
        $this->periodoVigente();

        $this->actingAs($this->cuenta($this->docente()))
            ->getJson('/api/examenes')
            ->assertOk()
            ->assertExactJsonStructure(['data', 'meta' => ['periodo', 'hoy', 'hora_servidor']])
            ->assertJsonCount(0, 'data');
    }
}
