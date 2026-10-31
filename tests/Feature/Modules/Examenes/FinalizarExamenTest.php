<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Examenes\Domain\Models\Examen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * HU-28: el docente finaliza el examen. La duracion es la prevista, pero un
 * examen puede pasarse, asi que se cierra recien al finalizarlo (o al
 * terminar su dia, si nadie lo hace).
 */
final class FinalizarExamenTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    /**
     * @return TestResponse<Response>
     */
    private function finalizar(Examen $examen): TestResponse
    {
        return $this->postJson("/api/examenes/{$examen->id}/finalizar");
    }

    public function test_a_teacher_of_the_exam_finalizes_it_and_it_is_logged(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $docente = $this->docente();
        // Empezo a las 09:55 con 90 min: todavia en su hora prevista.
        $examen = $this->examen($docente, [$this->grupo($docente, $this->asignatura())]);

        $this->actingAs($this->cuenta($docente))
            ->finalizar($examen)
            ->assertOk()
            ->assertJsonPath('message', 'Examen finalizado.')
            ->assertJsonPath('finalizado_en', now()->format('Y-m-d H:i:s'));

        $examen->refresh();
        $this->assertSame(now()->format('Y-m-d H:i:s'), $examen->finalizado_en?->format('Y-m-d H:i:s'));
        $this->assertSame($docente->user_id, $examen->finalizado_por);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $docente->user_id,
            'operacion' => 'examen.finalizar',
            'registro_id' => $examen->id,
        ]);
    }

    public function test_the_teacher_of_an_included_group_also_finalizes_it(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $docente = $this->docente();
        $otro = $this->docente('Otro Docente');
        $asignatura = $this->asignatura();
        $examen = $this->examen($docente, [
            $this->grupo($docente, $asignatura),
            $this->grupo($otro, $asignatura, '2'),
        ]);

        $this->actingAs($this->cuenta($otro))->finalizar($examen)->assertOk();
    }

    public function test_an_exam_past_its_planned_end_is_still_open_until_finalized(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $docente = $this->docente();
        $examen = $this->examen($docente, [$this->grupo($docente, $this->asignatura())], [], [
            'hora_inicio' => '08:00:00',
            'duracion_minutos' => 90,
        ]);

        $this->actingAs($this->cuenta($docente))->finalizar($examen)->assertOk();
    }

    public function test_only_an_open_exam_is_finalized_and_only_once(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $docente = $this->docente();
        $cuenta = $this->cuenta($docente);
        $asignatura = $this->asignatura();
        $futuro = $this->examen($docente, [$this->grupo($docente, $asignatura)], [], [
            'fecha' => now()->addDay()->toDateString(),
        ]);
        $deAyer = $this->examen($docente, [$this->grupo($docente, $asignatura, '2')], [], [
            'fecha' => now()->subDay()->toDateString(),
        ]);
        $abierto = $this->examen($docente, [$this->grupo($docente, $asignatura, '3')]);

        foreach ([$futuro, $deAyer] as $cerrado) {
            $this->actingAs($cuenta)
                ->finalizar($cerrado)
                ->assertStatus(409)
                ->assertJsonPath('codigo', 'EXAMEN_NO_ABIERTO')
                ->assertJsonPath('message', 'El examen no está abierto: no se puede finalizar.');
        }

        $this->actingAs($cuenta)->finalizar($abierto)->assertOk();
        $this->actingAs($cuenta)
            ->finalizar($abierto)
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'EXAMEN_NO_ABIERTO');
        $this->assertSame(1, DB::table('bitacora_operaciones')->where('operacion', 'examen.finalizar')->count());
    }

    public function test_another_teacher_cannot_finalize_it(): void
    {
        $docente = $this->docente();
        $examen = $this->examen($docente, [$this->grupo($docente, $this->asignatura())]);

        $this->actingAs($this->cuenta($this->docente('Otro Docente')))
            ->finalizar($examen)
            ->assertForbidden()
            ->assertJsonPath('message', 'Este examen no está a su cargo.')
            ->assertJsonPath('alcance', true);

        $this->assertNull($examen->refresh()->finalizado_en);
    }

    public function test_finalizing_needs_session_permission_and_an_existing_exam(): void
    {
        $docente = $this->docente();
        $examen = $this->examen($docente, [$this->grupo($docente, $this->asignatura())]);

        $this->finalizar($examen)->assertUnauthorized();
        $this->actingAs($this->usuarioConRol('Auxiliar'))->finalizar($examen)->assertForbidden();
        $this->actingAs($this->cuenta($docente))
            ->postJson('/api/examenes/999999/finalizar')
            ->assertNotFound()
            ->assertJsonPath('message', 'Examen no encontrado.');

        $this->assertNull($examen->refresh()->finalizado_en);
    }

    public function test_once_finalized_the_exam_is_taken_and_no_longer_in_progress(): void
    {
        $docente = $this->docente();
        $cuenta = $this->cuenta($docente);
        $examen = $this->examen($docente, [$this->grupo($docente, $this->asignatura())], [$this->aula()]);

        $this->actingAs($cuenta)
            ->getJson("/api/en-curso/{$examen->id}")
            ->assertOk()
            ->assertJsonPath('data.examen.abierto', true)
            ->assertJsonPath('data.examen.excedido', false);

        $this->actingAs($cuenta)->finalizar($examen)->assertOk();

        $this->actingAs($cuenta)
            ->getJson("/api/en-curso/{$examen->id}")
            ->assertOk()
            ->assertJsonPath('data.examen.abierto', false)
            ->assertJsonPath('data.examen.excedido', false);

        $this->actingAs($cuenta)
            ->getJson('/api/examenes')
            ->assertOk()
            ->assertJsonPath('data.0.rendido', true)
            ->assertJsonPath('data.0.finalizado_en', $examen->refresh()->finalizado_en?->format('Y-m-d H:i:s'));
    }
}
