<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Academico\Domain\Models\Horario;
use App\Modules\Examenes\Domain\Enums\TipoExamen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las aulas del examen viajan dentro del examen. No tienen tope de
 * estudiantes y compartirlas con otro examen a la misma hora no se impide: la ruta 49
 * lo avisa.
 */
final class AmbientesDelExamenTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    private const FECHA_HORA = ['hora_inicio' => '08:15:00', 'duracion_minutos' => 90];

    /**
     * @param  array<string, mixed>  $parametros
     */
    private function opciones(array $parametros): string
    {
        return '/api/examenes/opciones/aulas?'.http_build_query($parametros);
    }

    public function test_an_exam_admits_several_rooms_without_any_limit_of_students(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);
        $this->estudiantesInscritos($grupo, 30);
        $aulas = [$this->aula('691A'), $this->aula('691B'), $this->aula('617')];

        $this->actingAs($this->cuenta($docente))
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], $aulas))
            ->assertCreated()
            ->assertJsonPath('data.aulas', ['617', '691A', '691B'])
            ->assertJsonPath('data.inscritos', 30);

        $this->assertDatabaseCount('examen_aula', 3);
    }

    public function test_a_room_shared_with_an_overlapping_exam_is_not_blocked(): void
    {
        $docente = $this->docente();
        $otro = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);
        $aula = $this->aula();
        $fecha = now()->addDays(3)->toDateString();
        $this->examen($otro, [$this->grupo($otro)], [$aula], ['fecha' => $fecha] + self::FECHA_HORA);

        $this->actingAs($this->cuenta($docente))
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [$aula], [
                'fecha' => $fecha,
                'hora_inicio' => '09:00',
            ]))
            ->assertCreated();

        $this->assertSame(2, DB::table('examen_aula')->where('aula_id', $aula->id)->count());
    }

    public function test_the_options_warn_about_rooms_shared_with_overlapping_exams(): void
    {
        $docente = $this->docente();
        $otro = $this->docente();
        $calculo = $this->asignatura('Cálculo I');
        $fisica = $this->asignatura('Física I');
        $compartida = $this->aula();
        $libre = $this->aula();
        $fecha = now()->addDays(3)->toDateString();

        // 08:15 a 09:45.
        $this->examen($otro, [$this->grupo($otro, $calculo)], [$compartida], ['fecha' => $fecha] + self::FECHA_HORA);
        // 09:00 a 10:00, en la misma aula.
        $this->examen($otro, [$this->grupo($otro, $fisica)], [$compartida], [
            'fecha' => $fecha,
            'hora_inicio' => '09:00:00',
            'duracion_minutos' => 60,
            'tipo' => TipoExamen::Final,
        ]);
        // Otro dia a la misma hora.
        $this->examen($otro, [$this->grupo($otro)], [$libre], ['fecha' => now()->addDays(4)->toDateString()] + self::FECHA_HORA);

        $this->actingAs($this->cuenta($docente))
            ->getJson($this->opciones(['fecha' => $fecha, 'hora_inicio' => '09:30', 'duracion_minutos' => 60]))
            ->assertOk()
            ->assertExactJson([
                'sugeridas' => [],
                'compartidas' => [
                    (string) $compartida->id => ['Cálculo I · Primer parcial', 'Física I · Examen final'],
                ],
            ]);
    }

    public function test_a_partial_overlap_warns_and_touching_ends_do_not(): void
    {
        $docente = $this->docente();
        $otro = $this->docente();
        $aula = $this->aula();
        $fecha = now()->addDays(3)->toDateString();
        // 08:15 a 09:45.
        $this->examen($otro, [$this->grupo($otro)], [$aula], ['fecha' => $fecha] + self::FECHA_HORA);
        $cuenta = $this->cuenta($docente);

        $casos = [
            // Empieza antes y termina dentro.
            ['07:00', 90, true],
            // Empieza dentro y termina despues.
            ['09:30', 120, true],
            // Lo contiene.
            ['07:00', 300, true],
            // Queda dentro.
            ['08:30', 30, true],
            // Termina justo cuando el otro empieza.
            ['06:45', 90, false],
            // Empieza justo cuando el otro termina.
            ['09:45', 60, false],
            ['14:00', 90, false],
        ];

        foreach ($casos as [$hora, $duracion, $seSolapa]) {
            $respuesta = $this->actingAs($cuenta)
                ->getJson($this->opciones(['fecha' => $fecha, 'hora_inicio' => $hora, 'duracion_minutos' => $duracion]))
                ->assertOk();

            $this->assertSame(
                $seSolapa,
                array_key_exists((string) $aula->id, (array) $respuesta->json('compartidas')),
                "Solape con {$hora} durante {$duracion} minutos.",
            );
        }
    }

    public function test_the_exam_being_edited_is_excluded_from_its_own_warning(): void
    {
        $docente = $this->docente();
        $aula = $this->aula();
        $fecha = now()->addDays(3)->toDateString();
        $examen = $this->examen($docente, [$this->grupo($docente)], [$aula], ['fecha' => $fecha] + self::FECHA_HORA);
        $cuenta = $this->cuenta($docente);
        $parametros = ['fecha' => $fecha, 'hora_inicio' => '08:15', 'duracion_minutos' => 90];

        $this->actingAs($cuenta)
            ->getJson($this->opciones($parametros))
            ->assertOk()
            ->assertJsonCount(1, 'compartidas');

        $respuesta = $this->actingAs($cuenta)
            ->getJson($this->opciones($parametros + ['examen_id' => $examen->id]))
            ->assertOk();

        // Sin avisos sigue siendo un objeto, no una lista.
        $this->assertStringContainsString('"compartidas":{}', (string) $respuesta->getContent());
    }

    public function test_located_rooms_of_the_groups_schedules_are_suggested(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $uno = $this->grupo($docente, $asignatura);
        $dos = $this->grupo($docente, $asignatura);
        $otroGrupo = $this->grupo($docente, $asignatura);
        $edificio = $this->edificio();
        $ubicadaA = $this->aula('691A', $edificio);
        $ubicadaB = $this->aula('691B', $edificio);
        $sinUbicar = $this->aula('AULVIR');
        $deOtroGrupo = $this->aula('617', $edificio);

        foreach ([[$uno, $ubicadaA, 'LU'], [$uno, $ubicadaA, 'MI'], [$dos, $ubicadaB, 'MA'], [$dos, $sinUbicar, 'JU'], [$otroGrupo, $deOtroGrupo, 'VI']] as [$grupo, $aula, $dia]) {
            Horario::create([
                'grupo_id' => $grupo->id,
                'aula_id' => $aula->id,
                'dia' => $dia,
                'hora_inicio' => '08:15',
                'hora_fin' => '09:45',
            ]);
        }

        $this->actingAs($this->cuenta($docente))
            ->getJson($this->opciones(['grupos' => [$uno->id, $dos->id]]))
            ->assertOk()
            ->assertJsonPath('sugeridas', [$ubicadaA->id, $ubicadaB->id])
            ->assertJsonCount(0, 'compartidas');
    }

    public function test_the_options_are_validated(): void
    {
        $cuenta = $this->cuenta($this->docente());

        $this->actingAs($cuenta)
            ->getJson($this->opciones(['fecha' => '12-10-2026', 'hora_inicio' => '8', 'duracion_minutos' => 5, 'grupos' => ['x']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha', 'hora_inicio', 'grupos.0'])
            ->assertJsonValidationErrors(['duracion_minutos' => 'Entre 15 y 480']);

        // La fecha, la hora y la duracion van juntas.
        $this->actingAs($cuenta)
            ->getJson($this->opciones(['fecha' => '2026-10-12']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hora_inicio', 'duracion_minutos']);
    }

    public function test_the_same_room_is_not_assigned_twice_to_an_exam(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);
        $aula = $this->aula();

        $this->actingAs($this->cuenta($docente))
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [$aula, $aula]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['aulas.0' => 'Hay un aula repetida.']);

        $this->actingAs($this->cuenta($docente))
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], ['aulas' => [999999]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['aulas.0' => 'El aula no existe.']);
    }

    public function test_removing_a_room_leaves_its_enabled_students_without_room(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);
        $seQueda = $this->aula();
        $seVa = $this->aula();
        $examen = $this->examen($docente, [$grupo], [$seQueda, $seVa]);
        [$uno, $dos, $tres] = $this->estudiantesInscritos($grupo, 3);
        $this->habilitar($examen, [$uno], $seQueda);
        $this->habilitar($examen, [$dos, $tres], $seVa);

        $this->actingAs($this->cuenta($docente))
            ->putJson("/api/examenes/{$examen->id}", $this->cuerpo($asignatura, [$grupo], [$seQueda], [
                'fecha' => $examen->fecha->format('Y-m-d'),
            ]))
            ->assertOk()
            ->assertJsonPath('data.aulas', [$seQueda->nombre])
            ->assertJsonPath('data.habilitados', 3);

        $this->assertDatabaseMissing('examen_aula', ['examen_id' => $examen->id, 'aula_id' => $seVa->id]);
        $this->assertDatabaseHas('habilitaciones', ['estudiante_id' => $uno->id, 'aula_id' => $seQueda->id, 'habilitado' => true]);
        $this->assertDatabaseHas('habilitaciones', ['estudiante_id' => $dos->id, 'aula_id' => null, 'habilitado' => true]);
        $this->assertDatabaseHas('habilitaciones', ['estudiante_id' => $tres->id, 'aula_id' => null, 'habilitado' => true]);
        $this->assertDatabaseHas('bitacora_operaciones', ['operacion' => 'examen.modificar', 'registro_id' => $examen->id]);
    }

    public function test_removing_a_group_drops_enablings_of_who_is_no_longer_enrolled(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $seQueda = $this->grupo($docente, $asignatura);
        $seVa = $this->grupo($docente, $asignatura);
        $aula = $this->aula();
        $examen = $this->examen($docente, [$seQueda, $seVa], [$aula]);
        [$delQueSeQueda] = $this->estudiantesInscritos($seQueda, 1);
        [$delQueSeVa, $enLosDos] = $this->estudiantesInscritos($seVa, 2);
        DB::table('inscripciones')->insert([
            'estudiante_id' => $enLosDos->id,
            'grupo_id' => $seQueda->id,
            'via' => 'ADMINISTRACION',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->habilitar($examen, [$delQueSeQueda, $delQueSeVa, $enLosDos], $aula);

        $this->actingAs($this->cuenta($docente))
            ->putJson("/api/examenes/{$examen->id}", $this->cuerpo($asignatura, [$seQueda], [$aula], [
                'fecha' => $examen->fecha->format('Y-m-d'),
            ]))
            ->assertOk()
            ->assertJsonPath('data.inscritos', 2)
            ->assertJsonPath('data.habilitados', 2)
            ->assertJsonPath('data.qr_emitidos', 0);

        $this->assertDatabaseMissing('habilitaciones', ['examen_id' => $examen->id, 'estudiante_id' => $delQueSeVa->id]);
        $this->assertDatabaseHas('habilitaciones', ['examen_id' => $examen->id, 'estudiante_id' => $enLosDos->id]);
        $this->assertDatabaseHas('habilitaciones', ['examen_id' => $examen->id, 'estudiante_id' => $delQueSeQueda->id]);
        // El padron no se toca.
        $this->assertDatabaseHas('inscripciones', ['estudiante_id' => $delQueSeVa->id, 'grupo_id' => $seVa->id]);
    }

    public function test_changing_date_and_time_keeps_the_enablings_and_their_rooms(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);
        $aula = $this->aula();
        $examen = $this->examen($docente, [$grupo], [$aula]);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $this->habilitar($examen, [$estudiante], $aula);

        $this->actingAs($this->cuenta($docente))
            ->putJson("/api/examenes/{$examen->id}", $this->cuerpo($asignatura, [$grupo], [$aula], [
                'fecha' => now()->addDays(6)->toDateString(),
                'hora_inicio' => '16:00',
            ]))
            ->assertOk();

        $this->assertDatabaseHas('habilitaciones', ['estudiante_id' => $estudiante->id, 'aula_id' => $aula->id]);
    }

    public function test_the_room_options_need_a_session_and_the_permission(): void
    {
        $this->getJson('/api/examenes/opciones/aulas')->assertUnauthorized();

        $this->actingAs($this->usuarioConRol('Auxiliar'))
            ->getJson('/api/examenes/opciones/aulas')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'examenes');
    }
}
