<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Academico\Domain\Models\Asignatura;
use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Examenes\Domain\Models\Examen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Colaboracion entre docentes: el grupo de otro docente no entra al examen
 * hasta que el acepta; puede rechazar, y quien se sumo puede salir.
 */
final class InvitacionesDeExamenTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    private Docente $creador;

    private Docente $invitado;

    private Asignatura $asignatura;

    private Grupo $propio;

    private Grupo $ajeno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creador = $this->docente('Blanco Coca Leticia');
        $this->invitado = $this->docente('Taborga Acha Fidel');
        $this->asignatura = $this->asignatura('Taller de Ingeniería de Software', '2010024');
        $this->propio = $this->grupo($this->creador, $this->asignatura, '1');
        $this->ajeno = $this->grupo($this->invitado, $this->asignatura, '2');
        $this->estudiantesInscritos($this->propio, 3);
        $this->estudiantesInscritos($this->ajeno, 4);
    }

    /**
     * Registra el examen del creador invitando al grupo del otro docente.
     *
     * @param  array<string, mixed>  $cambios
     */
    private function registrar(array $cambios = []): int
    {
        $id = $this->actingAs($this->cuenta($this->creador))
            ->postJson('/api/examenes', $this->cuerpo($this->asignatura, [$this->propio, $this->ajeno], [], $cambios))
            ->assertCreated()
            ->json('data.id');

        $this->assertIsInt($id);

        return $id;
    }

    private function invitacionId(int $examenId): int
    {
        $id = DB::table('invitaciones_examen')->where('examen_id', $examenId)->value('id');

        $this->assertIsInt($id);

        return $id;
    }

    public function test_the_invitation_reaches_the_teacher_but_the_exam_does_not_count_their_students_yet(): void
    {
        $examenId = $this->registrar(['tipo' => 'PRIMER_PARCIAL', 'duracion_minutos' => 120]);
        $aula = $this->aula('691B');
        $this->actingAs($this->cuenta($this->creador))
            ->putJson("/api/examenes/{$examenId}", $this->cuerpo($this->asignatura, [$this->propio, $this->ajeno], [$aula], ['duracion_minutos' => 120]))
            ->assertOk();

        $invitado = $this->cuenta($this->invitado);

        $this->actingAs($invitado)
            ->getJson('/api/examenes/invitaciones')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $this->invitacionId($examenId))
            ->assertJsonPath('data.0.examen.id', $examenId)
            ->assertJsonPath('data.0.examen.tipo', 'PRIMER_PARCIAL')
            ->assertJsonPath('data.0.examen.tipo_texto', 'Primer parcial')
            ->assertJsonPath('data.0.examen.asignatura.codigo', '2010024')
            ->assertJsonPath('data.0.examen.asignatura.nombre', 'Taller de Ingeniería de Software')
            ->assertJsonPath('data.0.examen.hora', '08:15')
            ->assertJsonPath('data.0.examen.duracion', 120)
            ->assertJsonPath('data.0.examen.aulas', ['691B'])
            ->assertJsonPath('data.0.grupo.id', $this->ajeno->id)
            ->assertJsonPath('data.0.grupo.codigo', '2')
            ->assertJsonPath('data.0.invitado_por', 'Blanco Coca Leticia');

        // Hasta que acepte, el examen no es suyo: ni en su lista ni en su alcance.
        $this->actingAs($invitado)->getJson('/api/examenes')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($invitado)->getJson("/api/examenes/{$examenId}")->assertForbidden();

        $this->actingAs($this->cuenta($this->creador))
            ->getJson("/api/examenes/{$examenId}")
            ->assertOk()
            ->assertJsonPath('data.inscritos', 3)
            ->assertJsonPath('data.invitados_pendientes', 1)
            ->assertJsonPath('data.invitaciones.0.estado', 'pendiente');
    }

    public function test_accepting_adds_the_group_and_the_exam_shows_up_for_the_teacher(): void
    {
        $examenId = $this->registrar();
        $invitado = $this->cuenta($this->invitado);

        $this->actingAs($invitado)
            ->postJson('/api/examenes/invitaciones/'.$this->invitacionId($examenId).'/aceptar')
            ->assertOk()
            ->assertJsonPath('message', 'Invitación aceptada.');

        $this->assertDatabaseHas('examen_grupo', ['examen_id' => $examenId, 'grupo_id' => $this->ajeno->id]);
        $this->assertDatabaseCount('invitaciones_examen', 0);

        $this->actingAs($invitado)->getJson('/api/examenes/invitaciones')->assertJsonPath('meta.total', 0);
        $this->actingAs($invitado)
            ->getJson('/api/examenes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $examenId)
            ->assertJsonPath('data.0.propio', false)
            ->assertJsonPath('data.0.inscritos', 7);
        $this->actingAs($this->cuenta($this->creador))
            ->getJson("/api/examenes/{$examenId}")
            ->assertJsonPath('data.invitados_pendientes', 0)
            ->assertJsonPath('data.invitaciones', [])
            ->assertJsonPath('data.grupos', ['1', '2']);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'examen.aceptar_invitacion',
            'usuario_id' => $this->invitado->user_id,
            'registro_id' => $examenId,
        ]);
    }

    public function test_rejecting_leaves_it_rejected_for_the_creator_and_it_cannot_be_answered_again(): void
    {
        $examenId = $this->registrar();
        $invitacion = $this->invitacionId($examenId);
        $invitado = $this->cuenta($this->invitado);

        $this->actingAs($invitado)
            ->postJson("/api/examenes/invitaciones/{$invitacion}/rechazar")
            ->assertOk()
            ->assertJsonPath('message', 'Invitación rechazada.');

        $this->assertDatabaseHas('invitaciones_examen', ['id' => $invitacion, 'estado' => 'rechazada']);
        $this->assertDatabaseMissing('examen_grupo', ['grupo_id' => $this->ajeno->id]);
        $this->actingAs($invitado)->getJson('/api/examenes/invitaciones')->assertJsonPath('meta.total', 0);
        $this->actingAs($this->cuenta($this->creador))
            ->getJson("/api/examenes/{$examenId}")
            ->assertJsonPath('data.invitados_pendientes', 0)
            ->assertJsonPath('data.invitaciones.0.estado', 'rechazada');

        $this->actingAs($invitado)
            ->postJson("/api/examenes/invitaciones/{$invitacion}/aceptar")
            ->assertConflict()
            ->assertJsonPath('codigo', 'INVITACION_RESPONDIDA');
        $this->actingAs($invitado)
            ->postJson("/api/examenes/invitaciones/{$invitacion}/rechazar")
            ->assertConflict();
        $this->assertDatabaseHas('bitacora_operaciones', ['operacion' => 'examen.rechazar_invitacion']);
    }

    public function test_only_the_teacher_of_the_group_answers_and_a_missing_invitation_is_not_found(): void
    {
        $examenId = $this->registrar();
        $invitacion = $this->invitacionId($examenId);
        $tercero = $this->cuenta($this->docente());

        foreach (['aceptar', 'rechazar'] as $accion) {
            $this->actingAs($tercero)->postJson("/api/examenes/invitaciones/{$invitacion}/{$accion}")->assertForbidden();
            $this->actingAs($this->cuenta($this->creador))
                ->postJson("/api/examenes/invitaciones/{$invitacion}/{$accion}")
                ->assertForbidden();
            $this->actingAs($this->cuenta($this->invitado))
                ->postJson("/api/examenes/invitaciones/999999/{$accion}")
                ->assertNotFound();
        }

        $this->assertDatabaseHas('invitaciones_examen', ['id' => $invitacion, 'estado' => 'pendiente']);
        $this->actingAs($tercero)->getJson('/api/examenes/invitaciones')->assertJsonPath('meta.total', 0);
    }

    public function test_accepting_is_refused_if_the_group_already_has_an_exam_of_that_type(): void
    {
        $examenId = $this->registrar();
        $this->examen($this->invitado, [$this->ajeno], [], [
            'fecha' => $this->diaHabil(10),
            'hora_inicio' => '10:00:00',
        ]);

        $this->actingAs($this->cuenta($this->invitado))
            ->postJson('/api/examenes/invitaciones/'.$this->invitacionId($examenId).'/aceptar')
            ->assertUnprocessable()
            ->assertJsonPath('errors.grupos.0', 'El grupo 2 ya tiene un primer parcial.');

        $this->assertDatabaseMissing('examen_grupo', ['examen_id' => $examenId, 'grupo_id' => $this->ajeno->id]);
        $this->assertDatabaseCount('invitaciones_examen', 1);
    }

    public function test_accepting_is_refused_once_the_exam_has_entries(): void
    {
        $examenId = $this->registrar();
        $aula = $this->aula();
        $examen = Examen::findOrFail($examenId);
        [$estudiante] = $this->estudiantesInscritos($this->propio, 1);
        $this->ingresar($examen, $estudiante, $aula);

        $this->actingAs($this->cuenta($this->invitado))
            ->postJson('/api/examenes/invitaciones/'.$this->invitacionId($examenId).'/aceptar')
            ->assertConflict()
            ->assertJsonPath('codigo', 'EXAMEN_CON_INGRESOS');
    }

    public function test_a_collaborator_leaves_the_exam_and_the_creator_does_not(): void
    {
        $examenId = $this->registrar();
        $this->actingAs($this->cuenta($this->invitado))
            ->postJson('/api/examenes/invitaciones/'.$this->invitacionId($examenId).'/aceptar')
            ->assertOk();

        $this->actingAs($this->cuenta($this->creador))->postJson("/api/examenes/{$examenId}/salir")->assertForbidden();
        $this->actingAs($this->cuenta($this->docente()))->postJson("/api/examenes/{$examenId}/salir")->assertForbidden();
        $this->actingAs($this->cuenta($this->creador))->postJson('/api/examenes/999999/salir')->assertNotFound();

        $this->actingAs($this->cuenta($this->invitado))
            ->postJson("/api/examenes/{$examenId}/salir")
            ->assertOk()
            ->assertJsonPath('message', 'Salió del examen.');

        $this->assertDatabaseMissing('examen_grupo', ['examen_id' => $examenId, 'grupo_id' => $this->ajeno->id]);
        $this->assertDatabaseHas('examen_grupo', ['examen_id' => $examenId, 'grupo_id' => $this->propio->id]);
        $this->actingAs($this->cuenta($this->invitado))->getJson('/api/examenes')->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'examen.salir',
            'usuario_id' => $this->invitado->user_id,
        ]);
    }

    public function test_a_collaborator_cannot_leave_once_the_exam_has_entries(): void
    {
        $examenId = $this->registrar();
        $this->actingAs($this->cuenta($this->invitado))
            ->postJson('/api/examenes/invitaciones/'.$this->invitacionId($examenId).'/aceptar')
            ->assertOk();
        [$estudiante] = $this->estudiantesInscritos($this->propio, 1);
        $this->ingresar(Examen::findOrFail($examenId), $estudiante, $this->aula());

        $this->actingAs($this->cuenta($this->invitado))
            ->postJson("/api/examenes/{$examenId}/salir")
            ->assertConflict()
            ->assertJsonPath('codigo', 'EXAMEN_CON_INGRESOS');
        $this->assertDatabaseHas('examen_grupo', ['examen_id' => $examenId, 'grupo_id' => $this->ajeno->id]);
    }

    public function test_updating_syncs_the_invitations_without_duplicating_them(): void
    {
        $examenId = $this->registrar();
        $cuenta = $this->cuenta($this->creador);
        $otro = $this->docente('Rojas Vargas Ana');
        $tercero = $this->grupo($otro, $this->asignatura, '3');

        // Guardar de nuevo con los mismos grupos no repite la invitacion.
        $this->actingAs($cuenta)
            ->putJson("/api/examenes/{$examenId}", $this->cuerpo($this->asignatura, [$this->propio, $this->ajeno]))
            ->assertOk()
            ->assertJsonPath('data.invitados_pendientes', 1);
        $this->assertDatabaseCount('invitaciones_examen', 1);

        // Sumar otro grupo ajeno invita; quitar el primero retira su invitacion.
        $this->actingAs($cuenta)
            ->putJson("/api/examenes/{$examenId}", $this->cuerpo($this->asignatura, [$this->propio, $tercero]))
            ->assertOk()
            ->assertJsonPath('data.invitaciones.0.grupo_id', $tercero->id);

        $this->assertDatabaseMissing('invitaciones_examen', ['grupo_id' => $this->ajeno->id]);
        $this->assertDatabaseHas('invitaciones_examen', ['grupo_id' => $tercero->id, 'estado' => 'pendiente']);
        $this->assertDatabaseHas('bitacora_operaciones', ['operacion' => 'examen.retirar_invitacion']);

        // Una rechazada se conserva mientras siga en la lista.
        $this->actingAs($this->cuenta($otro))
            ->postJson('/api/examenes/invitaciones/'.$this->invitacionId($examenId).'/rechazar')
            ->assertOk();
        $this->actingAs($cuenta)
            ->putJson("/api/examenes/{$examenId}", $this->cuerpo($this->asignatura, [$this->propio, $tercero]))
            ->assertOk()
            ->assertJsonPath('data.invitaciones.0.estado', 'rechazada');
    }

    public function test_an_accepted_group_stays_when_the_exam_is_saved_and_the_creator_can_remove_it(): void
    {
        $examenId = $this->registrar();
        $cuenta = $this->cuenta($this->creador);
        $this->actingAs($this->cuenta($this->invitado))
            ->postJson('/api/examenes/invitaciones/'.$this->invitacionId($examenId).'/aceptar')
            ->assertOk();

        $this->actingAs($cuenta)
            ->putJson("/api/examenes/{$examenId}", $this->cuerpo($this->asignatura, [$this->propio, $this->ajeno]))
            ->assertOk()
            ->assertJsonPath('data.grupos', ['1', '2'])
            ->assertJsonPath('data.invitaciones', []);
        $this->assertDatabaseCount('invitaciones_examen', 0);

        $this->actingAs($cuenta)
            ->putJson("/api/examenes/{$examenId}", $this->cuerpo($this->asignatura, [$this->propio]))
            ->assertOk()
            ->assertJsonPath('data.grupos', ['1']);
        $this->assertDatabaseMissing('examen_grupo', ['grupo_id' => $this->ajeno->id]);
        $this->assertDatabaseCount('invitaciones_examen', 0);
    }

    public function test_saving_an_exam_with_entries_keeps_a_pending_invitation(): void
    {
        $examenId = $this->registrar();
        $examen = Examen::findOrFail($examenId);
        $aula = $this->aula();
        [$estudiante] = $this->estudiantesInscritos($this->propio, 1);
        $this->ingresar($examen, $estudiante, $aula);

        $this->actingAs($this->cuenta($this->creador))
            ->putJson("/api/examenes/{$examenId}", $this->cuerpo($this->asignatura, [$this->propio, $this->ajeno], [$aula], [
                'fecha' => $examen->fecha->format('Y-m-d'),
                'hora_inicio' => substr($examen->hora_inicio, 0, 5),
                'normas' => 'Normas nuevas.',
            ]))
            ->assertOk()
            ->assertJsonPath('data.normas', 'Normas nuevas.')
            ->assertJsonPath('data.invitados_pendientes', 1);
        $this->assertDatabaseCount('invitaciones_examen', 1);
    }

    public function test_the_list_counts_the_pending_invitations_of_each_exam(): void
    {
        $this->registrar();

        $this->actingAs($this->cuenta($this->creador))
            ->getJson('/api/examenes')
            ->assertOk()
            ->assertJsonPath('data.0.invitados_pendientes', 1);
    }

    public function test_the_routes_need_the_exams_permission(): void
    {
        $examenId = $this->registrar();
        $sinPermiso = $this->auxiliarConRol();

        $this->actingAs($sinPermiso)->getJson('/api/examenes/invitaciones')->assertForbidden();
        $this->actingAs($sinPermiso)
            ->postJson('/api/examenes/invitaciones/'.$this->invitacionId($examenId).'/aceptar')
            ->assertForbidden();
        $this->actingAs($sinPermiso)->postJson("/api/examenes/{$examenId}/salir")->assertForbidden();
    }
}
