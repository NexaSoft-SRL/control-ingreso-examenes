<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Examenes\Domain\Models\Examen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El examen es de la asignatura y abarca grupos: se registra de una vez
 * con sus grupos, sus aulas y quien las atiende (rutas 43 a 47).
 */
final class RegistroExamenTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    public function test_an_exam_is_registered_in_one_request_with_groups_rooms_and_staff(): void
    {
        $docente = $this->docente('Blanco Coca Leticia');
        $asignatura = $this->asignatura('Introducción a la Programación', '2010010');
        $uno = $this->grupo($docente, $asignatura, '1');
        $dos = $this->grupo($docente, $asignatura, '2');
        $this->estudiantesInscritos($uno, 3);
        $this->estudiantesInscritos($dos, 2);
        $edificio = $this->edificio(null, 'Edificio Académico 2');
        $aulaA = $this->aula('624', $edificio, '1° Piso');
        $aulaB = $this->aula('AUDINF');
        $auxiliar = $this->auxiliarConRol();
        $apoyo = $this->auxiliarConRol();

        $respuesta = $this->actingAs($this->cuenta($docente))
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$uno, $dos], [], [
                'aulas' => [
                    ['aula_id' => $aulaA->id, 'encargado_id' => $auxiliar->id, 'apoyo' => [$apoyo->id]],
                    ['aula_id' => $aulaB->id],
                ],
            ]));

        $respuesta->assertCreated()
            ->assertJsonPath('message', 'Examen registrado.')
            ->assertJsonPath('data.asignatura.codigo', '2010010')
            ->assertJsonPath('data.tipo', 'PRIMER_PARCIAL')
            ->assertJsonPath('data.tipo_texto', 'Primer parcial')
            ->assertJsonPath('data.hora', '08:15')
            ->assertJsonPath('data.duracion', 90)
            ->assertJsonPath('data.grupos', ['1', '2'])
            ->assertJsonPath('data.inscritos', 5)
            ->assertJsonPath('data.aulas', ['624', 'AUDINF'])
            ->assertJsonPath('data.auxiliares', 2)
            ->assertJsonPath('data.propio', true)
            ->assertJsonPath('data.registrado_por', 'Blanco Coca Leticia')
            ->assertJsonPath('data.normas', 'Sin celular.')
            ->assertJsonPath('data.grupos_detalle.0.codigo', '1')
            ->assertJsonPath('data.grupos_detalle.0.inscritos', 3)
            ->assertJsonPath('data.grupos_detalle.0.propio', true)
            ->assertJsonPath('data.aulas_detalle.0.aula_id', $aulaA->id)
            ->assertJsonPath('data.aulas_detalle.0.ubicacion', 'Edificio Académico 2 · 1° Piso')
            ->assertJsonPath('data.aulas_detalle.0.edificio_id', $edificio->id)
            ->assertJsonPath('data.aulas_detalle.0.encargado.id', $auxiliar->id)
            ->assertJsonPath('data.aulas_detalle.0.encargado.es_docente', false)
            ->assertJsonPath('data.aulas_detalle.0.apoyo.0.id', $apoyo->id)
            ->assertJsonPath('data.aulas_detalle.0.usa_codigo', false)
            ->assertJsonPath('data.aulas_detalle.1.ubicacion', null)
            ->assertJsonPath('data.aulas_detalle.1.encargado.id', $docente->user_id)
            ->assertJsonPath('data.aulas_detalle.1.encargado.es_docente', true);

        $examenId = $respuesta->json('data.id');

        $this->assertDatabaseHas('examenes', [
            'id' => $examenId,
            'asignatura_id' => $asignatura->id,
            'periodo_id' => $uno->periodo_id,
            'creado_por' => $docente->user_id,
            'hora_inicio' => '08:15:00',
            'normas' => 'Sin celular.',
        ]);
        $this->assertDatabaseCount('examen_grupo', 2);
        $this->assertDatabaseHas('examen_aula', ['examen_id' => $examenId, 'aula_id' => $aulaA->id, 'encargado_id' => $auxiliar->id]);
        $this->assertDatabaseHas('examen_aula', ['examen_id' => $examenId, 'aula_id' => $aulaB->id, 'encargado_id' => $docente->user_id]);
        $this->assertDatabaseHas('examen_aula_apoyo', ['user_id' => $apoyo->id]);
    }

    public function test_an_exam_can_be_registered_without_rooms(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);

        $this->actingAs($this->cuenta($docente))
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], ['normas' => null]))
            ->assertCreated()
            ->assertJsonPath('data.aulas', [])
            ->assertJsonPath('data.normas', null)
            ->assertJsonPath('data.estado', 'Faltan aulas');
    }

    public function test_an_exam_cannot_be_registered_in_the_past_or_at_odd_hours(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);
        $cuenta = $this->cuenta($docente);

        $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], ['fecha' => now()->subDay()->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.fecha.0', 'La fecha ya pasó.');

        $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], ['hora_inicio' => '03:00']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.hora_inicio.0', 'Entre 06:00 y 22:00.');

        $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], ['hora_inicio' => '21:30', 'duracion_minutos' => 180]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.duracion_minutos.0', 'El examen terminaría después de medianoche.');
    }

    public function test_the_exam_falls_inside_the_calendar_window_of_its_type(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);
        $this->periodoVigente()->forceFill(['ventanas' => [
            'primeros_parciales' => [now()->toDateString(), now()->addDays(10)->toDateString()],
        ]])->save();

        $this->actingAs($this->cuenta($docente))
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], ['fecha' => now()->addDays(20)->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.fecha.0', fn (string $mensaje): bool => str_starts_with($mensaje, 'Fuera del calendario: primer parcial del '));

        $this->actingAs($this->cuenta($docente))
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo]))
            ->assertCreated();
    }

    public function test_groups_of_other_teachers_can_be_added_without_their_approval(): void
    {
        $docente = $this->docente();
        $otro = $this->docente('Taborga Acha Fidel');
        $asignatura = $this->asignatura();
        $propio = $this->grupo($docente, $asignatura, '1');
        $ajeno = $this->grupo($otro, $asignatura, '2');
        $sinDocente = $this->grupo(null, $asignatura, '3');

        $this->actingAs($this->cuenta($docente))
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$propio, $ajeno, $sinDocente]))
            ->assertCreated()
            ->assertJsonPath('data.grupos', ['1', '2', '3'])
            ->assertJsonPath('data.grupos_detalle.1.docente', 'Taborga Acha Fidel')
            ->assertJsonPath('data.grupos_detalle.1.propio', false)
            ->assertJsonPath('data.grupos_detalle.2.docente', null);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'examen.registrar',
            'usuario_id' => $docente->user_id,
        ]);
        $descripcion = DB::table('bitacora_operaciones')
            ->where('operacion', 'examen.registrar')
            ->value('descripcion');

        $this->assertIsString($descripcion);
        $this->assertStringContainsString('De otros docentes: 2, 3.', $descripcion);
    }

    public function test_the_subject_must_have_a_group_of_the_teacher_in_a_current_period(): void
    {
        $docente = $this->docente();
        $otro = $this->docente();
        $asignatura = $this->asignatura();
        $ajeno = $this->grupo($otro, $asignatura);

        $this->actingAs($this->cuenta($docente))
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$ajeno]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('asignatura_id');

        $this->assertSame(0, Examen::count());
    }

    public function test_a_group_of_another_subject_is_rejected(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $propio = $this->grupo($docente, $asignatura);
        $deOtra = $this->grupo($docente, $this->asignatura());

        $this->actingAs($this->cuenta($docente))
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$propio, $deOtra]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('grupos');

        $this->assertSame(0, Examen::count());
    }

    public function test_a_group_cannot_be_in_two_exams_of_the_same_type(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $uno = $this->grupo($docente, $asignatura, '1');
        $tres = $this->grupo($docente, $asignatura, '3');
        $cuenta = $this->cuenta($docente);

        $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$tres]))
            ->assertCreated();

        $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$uno, $tres], [], ['hora_inicio' => '14:00']))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El grupo 3 ya tiene un primer parcial.')
            ->assertJsonPath('errors.grupos.0', 'El grupo 3 ya tiene un primer parcial.');

        // Con otro tipo si puede.
        $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$uno, $tres], [], ['tipo' => 'FINAL']))
            ->assertCreated();

        $this->assertSame(2, Examen::count());
    }

    public function test_the_date_must_fall_inside_the_period_of_the_groups(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);

        $this->actingAs($this->cuenta($docente))
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], [
                'fecha' => now()->addDays(200)->toDateString(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha');
    }

    public function test_the_form_is_validated_with_messages_in_spanish(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);
        $aula = $this->aula();

        $this->actingAs($this->cuenta($docente))
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], [
                'tipo' => 'PRACTICO',
                'fecha' => '12/10/2026',
                'hora_inicio' => '8 y cuarto',
                'duracion_minutos' => 5,
                'normas' => str_repeat('a', 2001),
                'grupos' => [],
                'aulas' => [['aula_id' => $aula->id], ['aula_id' => $aula->id]],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tipo', 'fecha', 'hora_inicio', 'normas', 'grupos', 'aulas.0.aula_id',
            ])
            ->assertJsonValidationErrors(['aulas.0.aula_id' => 'Hay un aula repetida.'])
            ->assertJsonPath('errors.duracion_minutos.0', 'Entre 15 y 480');

        $this->actingAs($this->cuenta($docente))
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], ['duracion_minutos' => 481]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.duracion_minutos.0', 'Entre 15 y 480');
    }

    public function test_who_is_in_charge_must_be_a_teacher_or_an_assistant(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);
        $aula = $this->aula();
        $administrador = $this->usuarioConRol('Administrador');
        $otroDocente = $this->docente();
        $auxiliar = $this->auxiliarConRol();
        $cuenta = $this->cuenta($docente);

        $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], [
                'aulas' => [['aula_id' => $aula->id, 'encargado_id' => $administrador->id]],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('aulas.0.encargado_id');

        // De apoyo solo van auxiliares.
        $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], [
                'aulas' => [['aula_id' => $aula->id, 'apoyo' => [$otroDocente->user_id]]],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('aulas.0.apoyo');

        // Y no puede ser el mismo encargado.
        $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], [
                'aulas' => [['aula_id' => $aula->id, 'encargado_id' => $auxiliar->id, 'apoyo' => [$auxiliar->id]]],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('aulas.0.apoyo');

        $this->assertSame(0, Examen::count());

        // Otro docente si puede quedar a cargo.
        $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], [
                'aulas' => [['aula_id' => $aula->id, 'encargado_id' => $otroDocente->user_id]],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.aulas_detalle.0.encargado.es_docente', true)
            ->assertJsonPath('data.auxiliares', 0);
    }

    public function test_the_teacher_of_a_joined_group_can_be_left_in_charge_of_a_room(): void
    {
        $docente = $this->docente();
        $sumado = $this->docente('Taborga Acha Fidel');
        $cuenta = $this->cuenta($docente);
        $asignatura = $this->asignatura();
        $propio = $this->grupo($docente, $asignatura, '1');
        $ajeno = $this->grupo($sumado, $asignatura, '2');
        $aulaA = $this->aula();
        $aulaB = $this->aula();

        // El id sale de las opciones de grupos (ruta 48).
        $sumadoId = $this->actingAs($cuenta)
            ->getJson("/api/examenes/opciones/grupos?asignatura_id={$asignatura->id}")
            ->assertOk()
            ->json('otros.0.docente_usuario_id');

        $this->assertSame($sumado->user_id, $sumadoId);

        $examenId = $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$propio, $ajeno], [], [
                'aulas' => [
                    ['aula_id' => $aulaA->id],
                    ['aula_id' => $aulaB->id, 'encargado_id' => $sumadoId],
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.grupos_detalle.*.docente_usuario_id', [$docente->user_id, $sumado->user_id])
            ->assertJsonPath('data.aulas_detalle.1.encargado.id', $sumado->user_id)
            ->assertJsonPath('data.aulas_detalle.1.encargado.nombre', 'Taborga Acha Fidel')
            ->assertJsonPath('data.aulas_detalle.1.encargado.es_docente', true)
            ->assertJsonPath('data.auxiliares', 0)
            ->json('data.id');

        $this->assertIsInt($examenId);

        // Al modificar se le pasa la otra aula.
        $this->actingAs($cuenta)
            ->putJson("/api/examenes/{$examenId}", $this->cuerpo($asignatura, [$propio, $ajeno], [], [
                'aulas' => [
                    ['aula_id' => $aulaA->id, 'encargado_id' => $sumadoId],
                    ['aula_id' => $aulaB->id],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('data.aulas_detalle.0.encargado.id', $sumado->user_id)
            ->assertJsonPath('data.aulas_detalle.1.encargado.id', $docente->user_id);

        $this->assertDatabaseHas('examen_aula', ['examen_id' => $examenId, 'aula_id' => $aulaA->id, 'encargado_id' => $sumado->user_id]);
    }

    public function test_the_types_of_exam_are_the_five_of_the_university(): void
    {
        $this->actingAs($this->usuarioConPermisos(['examenes']))
            ->getJson('/api/examenes/tipos')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['valor' => 'PRIMER_PARCIAL', 'etiqueta' => 'Primer parcial'],
                ['valor' => 'SEGUNDO_PARCIAL', 'etiqueta' => 'Segundo parcial'],
                ['valor' => 'FINAL', 'etiqueta' => 'Examen final'],
                ['valor' => 'SEGUNDA_INSTANCIA', 'etiqueta' => 'Segunda instancia'],
                ['valor' => 'MESA', 'etiqueta' => 'Examen de mesa'],
            ]]);
    }

    public function test_the_exam_is_shown_to_the_teacher_of_an_included_group_as_not_own(): void
    {
        $registra = $this->docente('Blanco Coca Leticia');
        $sumado = $this->docente();
        $asignatura = $this->asignatura();
        $examen = $this->examen($registra, [
            $this->grupo($registra, $asignatura, '1'),
            $this->grupo($sumado, $asignatura, '2'),
        ], [], ['normas' => 'Hoja de fórmulas A4.']);

        $this->actingAs($this->cuenta($sumado))
            ->getJson("/api/examenes/{$examen->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $examen->id)
            ->assertJsonPath('data.propio', false)
            ->assertJsonPath('data.registrado_por', 'Blanco Coca Leticia')
            ->assertJsonPath('data.normas', 'Hoja de fórmulas A4.')
            ->assertJsonPath('data.grupos_detalle.0.propio', false)
            ->assertJsonPath('data.grupos_detalle.1.propio', true);

        $this->actingAs($this->cuenta($registra))
            ->getJson("/api/examenes/{$examen->id}")
            ->assertOk()
            ->assertJsonPath('data.propio', true);
    }

    public function test_an_exam_of_others_is_not_shown(): void
    {
        $registra = $this->docente();
        $ajeno = $this->docente();
        $examen = $this->examen($registra, [$this->grupo($registra)]);

        $this->actingAs($this->cuenta($ajeno))
            ->getJson("/api/examenes/{$examen->id}")
            ->assertForbidden()
            ->assertExactJson(['message' => 'Este examen no está a su cargo.', 'alcance' => true]);

        $this->actingAs($this->cuenta($ajeno))
            ->getJson('/api/examenes/999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Examen no encontrado.');
    }

    public function test_an_exam_without_entries_can_be_modified_entirely(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $uno = $this->grupo($docente, $asignatura, '1');
        $dos = $this->grupo($docente, $asignatura, '2');
        $aula = $this->aula('617');
        $examen = $this->examen($docente, [$uno], [$aula]);
        $fecha = now()->addDays(5)->toDateString();

        $this->actingAs($this->cuenta($docente))
            ->putJson("/api/examenes/{$examen->id}", $this->cuerpo($asignatura, [$uno, $dos], [$aula], [
                'tipo' => 'FINAL',
                'fecha' => $fecha,
                'hora_inicio' => '14:15',
                'duracion_minutos' => 120,
                'normas' => 'Solo lapicero.',
            ]))
            ->assertOk()
            ->assertJsonPath('message', 'Examen guardado.')
            ->assertJsonPath('data.tipo', 'FINAL')
            ->assertJsonPath('data.fecha', $fecha)
            ->assertJsonPath('data.hora', '14:15')
            ->assertJsonPath('data.duracion', 120)
            ->assertJsonPath('data.grupos', ['1', '2'])
            ->assertJsonPath('data.normas', 'Solo lapicero.');

        $this->assertDatabaseHas('examenes', ['id' => $examen->id, 'tipo' => 'FINAL', 'duracion_minutos' => 120]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'examen.modificar',
            'registro_id' => $examen->id,
            'usuario_id' => $docente->user_id,
        ]);
    }

    public function test_modifying_keeps_the_rule_of_one_exam_per_type_but_excludes_itself(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $uno = $this->grupo($docente, $asignatura, '1');
        $dos = $this->grupo($docente, $asignatura, '2');
        $examen = $this->examen($docente, [$uno]);
        $this->examen($docente, [$dos]);
        $cuenta = $this->cuenta($docente);

        // Guardarlo tal cual no choca consigo mismo.
        $this->actingAs($cuenta)
            ->putJson("/api/examenes/{$examen->id}", $this->cuerpo($asignatura, [$uno]))
            ->assertOk();

        $this->actingAs($cuenta)
            ->putJson("/api/examenes/{$examen->id}", $this->cuerpo($asignatura, [$uno, $dos]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.grupos.0', 'El grupo 2 ya tiene un primer parcial.');
    }

    public function test_a_board_exam_is_registered_and_not_repeated_for_a_group(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura, '4');
        $cuenta = $this->cuenta($docente);

        $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], ['tipo' => 'MESA']))
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'MESA')
            ->assertJsonPath('data.tipo_texto', 'Examen de mesa');

        $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], ['tipo' => 'MESA', 'hora_inicio' => '14:00']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.grupos.0', 'El grupo 4 ya tiene un examen de mesa.');

        $this->assertSame(1, Examen::count());
    }

    public function test_only_who_registered_the_exam_modifies_or_deletes_it(): void
    {
        $registra = $this->docente();
        $sumado = $this->docente();
        $asignatura = $this->asignatura();
        $propio = $this->grupo($registra, $asignatura);
        $ajeno = $this->grupo($sumado, $asignatura);
        $examen = $this->examen($registra, [$propio, $ajeno]);

        $this->actingAs($this->cuenta($sumado))
            ->putJson("/api/examenes/{$examen->id}", $this->cuerpo($asignatura, [$propio, $ajeno]))
            ->assertForbidden()
            ->assertExactJson(['message' => 'Este examen no está a su cargo.', 'alcance' => true]);

        $this->actingAs($this->cuenta($sumado))
            ->deleteJson("/api/examenes/{$examen->id}")
            ->assertForbidden()
            ->assertJsonPath('alcance', true);

        $this->assertDatabaseHas('examenes', ['id' => $examen->id]);
    }

    public function test_with_entries_only_rules_and_staff_can_change(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);
        $aula = $this->aula();
        $otraAula = $this->aula();
        $examen = $this->examen($docente, [$grupo], [$aula]);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $this->habilitar($examen, [$estudiante], $aula);
        $this->ingresar($examen, $estudiante, $aula);
        $auxiliar = $this->auxiliarConRol();
        $cuenta = $this->cuenta($docente);

        $igual = $this->cuerpo($asignatura, [$grupo], [$aula], [
            'tipo' => $examen->tipo->value,
            'fecha' => $examen->fecha->format('Y-m-d'),
            'hora_inicio' => substr($examen->hora_inicio, 0, 5),
            'duracion_minutos' => $examen->duracion_minutos,
        ]);

        foreach ([
            ['hora_inicio' => '23:00'],
            ['duracion_minutos' => 45],
            ['tipo' => 'FINAL'],
            ['fecha' => now()->addDay()->toDateString()],
            ['aulas' => [['aula_id' => $aula->id], ['aula_id' => $otraAula->id]]],
            ['aulas' => []],
            ['grupos' => [$grupo->id, $this->grupo($docente, $asignatura)->id]],
        ] as $cambio) {
            $this->actingAs($cuenta)
                ->putJson("/api/examenes/{$examen->id}", array_merge($igual, $cambio))
                ->assertConflict()
                ->assertJsonPath('codigo', 'EXAMEN_CON_INGRESOS');
        }

        $this->actingAs($cuenta)
            ->putJson("/api/examenes/{$examen->id}", array_merge($igual, [
                'normas' => 'Normas nuevas.',
                'aulas' => [['aula_id' => $aula->id, 'encargado_id' => $auxiliar->id]],
            ]))
            ->assertOk()
            ->assertJsonPath('data.normas', 'Normas nuevas.')
            ->assertJsonPath('data.aulas_detalle.0.encargado.id', $auxiliar->id);

        $this->assertDatabaseHas('examen_aula', ['examen_id' => $examen->id, 'encargado_id' => $auxiliar->id]);
        $this->assertDatabaseCount('ingresos', 1);
    }

    public function test_an_exam_can_be_deleted_and_takes_its_groups_and_rooms_with_it(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $aula = $this->aula();
        $examen = $this->examen($docente, [$grupo], [$aula]);
        $this->habilitar($examen, $this->estudiantesInscritos($grupo, 2), $aula);

        $this->actingAs($this->cuenta($docente))
            ->deleteJson("/api/examenes/{$examen->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('examenes', ['id' => $examen->id]);
        $this->assertDatabaseCount('examen_grupo', 0);
        $this->assertDatabaseCount('examen_aula', 0);
        $this->assertDatabaseCount('habilitaciones', 0);
        $this->assertDatabaseHas('aulas', ['id' => $aula->id]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'examen.eliminar',
            'registro_id' => $examen->id,
            'usuario_id' => $docente->user_id,
        ]);

        $this->actingAs($this->cuenta($docente))
            ->deleteJson("/api/examenes/{$examen->id}")
            ->assertNotFound();
    }

    public function test_an_exam_with_entries_cannot_be_deleted(): void
    {
        $docente = $this->docente();
        $grupo = $this->grupo($docente);
        $aula = $this->aula();
        $examen = $this->examen($docente, [$grupo], [$aula]);
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $this->habilitar($examen, [$estudiante], $aula);
        $this->ingresar($examen, $estudiante, $aula);

        $this->actingAs($this->cuenta($docente))
            ->deleteJson("/api/examenes/{$examen->id}")
            ->assertConflict()
            ->assertJsonPath('codigo', 'EXAMEN_CON_INGRESOS');

        $this->assertDatabaseHas('examenes', ['id' => $examen->id]);
    }

    public function test_every_write_is_recorded_in_the_audit_log(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);
        $aula = $this->aula();
        $auxiliar = $this->auxiliarConRol();
        $cuenta = $this->cuenta($docente);

        // Sin personal ajeno no hay asiento de asignacion.
        $examenId = $this->actingAs($cuenta)
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [$aula]))
            ->assertCreated()
            ->json('data.id');

        $this->assertIsInt($examenId);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'examen.registrar',
            'tabla_afectada' => 'examenes',
            'registro_id' => $examenId,
            'usuario_id' => $docente->user_id,
        ]);
        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'examen.asignar_auxiliar']);

        // Guardar sin tocar el personal tampoco lo asienta.
        $this->actingAs($cuenta)
            ->putJson("/api/examenes/{$examenId}", $this->cuerpo($asignatura, [$grupo], [$aula], ['normas' => 'Otras.']))
            ->assertOk();

        $this->assertDatabaseHas('bitacora_operaciones', ['operacion' => 'examen.modificar', 'registro_id' => $examenId]);
        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'examen.asignar_auxiliar']);

        $this->actingAs($cuenta)
            ->putJson("/api/examenes/{$examenId}", $this->cuerpo($asignatura, [$grupo], [], [
                'aulas' => [['aula_id' => $aula->id, 'apoyo' => [$auxiliar->id]]],
            ]))
            ->assertOk();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'examen.asignar_auxiliar',
            'registro_id' => $examenId,
            'usuario_id' => $docente->user_id,
        ]);
    }

    public function test_registering_with_assistants_records_the_assignment(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $grupo = $this->grupo($docente, $asignatura);
        $aula = $this->aula();
        $auxiliar = $this->auxiliarConRol();

        $this->actingAs($this->cuenta($docente))
            ->postJson('/api/examenes', $this->cuerpo($asignatura, [$grupo], [], [
                'aulas' => [['aula_id' => $aula->id, 'encargado_id' => $auxiliar->id]],
            ]))
            ->assertCreated();

        $this->assertDatabaseHas('bitacora_operaciones', ['operacion' => 'examen.asignar_auxiliar']);
    }

    public function test_a_role_without_the_permission_cannot_manage_exams(): void
    {
        // El administrador no tiene la pantalla de examenes.
        $cuenta = $this->usuarioConRol('Administrador');
        $docente = $this->docente();
        $examen = $this->examen($docente, [$this->grupo($docente)]);

        foreach ([
            ['GET', '/api/examenes'],
            ['GET', '/api/examenes/tipos'],
            ['GET', "/api/examenes/{$examen->id}"],
            ['POST', '/api/examenes'],
            ['PUT', "/api/examenes/{$examen->id}"],
            ['DELETE', "/api/examenes/{$examen->id}"],
        ] as [$metodo, $ruta]) {
            $this->actingAs($cuenta)
                ->json($metodo, $ruta)
                ->assertForbidden()
                ->assertJsonPath('permiso_requerido', 'examenes');
        }
    }

    public function test_a_guest_cannot_manage_exams(): void
    {
        $docente = $this->docente();
        $examen = $this->examen($docente, [$this->grupo($docente)]);

        foreach ([
            ['GET', '/api/examenes'],
            ['GET', '/api/examenes/tipos'],
            ['GET', "/api/examenes/{$examen->id}"],
            ['POST', '/api/examenes'],
            ['PUT', "/api/examenes/{$examen->id}"],
            ['DELETE', "/api/examenes/{$examen->id}"],
        ] as [$metodo, $ruta]) {
            $this->json($metodo, $ruta)->assertUnauthorized();
        }

        $this->assertDatabaseHas('examenes', ['id' => $examen->id]);
    }
}
