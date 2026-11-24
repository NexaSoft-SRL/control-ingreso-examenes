<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * El docente suspende el examen abierto: la puerta deja de admitir hasta
 * que lo reanuda, En curso muestra el motivo, y un puesto sin internet que
 * admitio durante la suspension queda como irregularidad.
 */
final class SuspensionDeExamenTest extends TestCase
{
    use EscenarioDePuerta;
    use RefreshDatabase;

    private const DISPOSITIVO = 'puesto-0001-abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        $this->armarExamen();
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function suspender(?int $examenId = null, string $motivo = 'Corte de luz')
    {
        return $this->actingAs($this->cuentaDocente)->postJson('/api/examenes/'.($examenId ?? $this->examen->id).'/suspender', [
            'motivo' => $motivo,
            'descripcion' => 'Se reanuda al volver la luz.',
        ]);
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function reanudar()
    {
        return $this->actingAs($this->cuentaDocente)->postJson("/api/examenes/{$this->examen->id}/reanudar");
    }

    public function test_the_teacher_suspends_and_resumes_the_open_exam_and_both_are_logged(): void
    {
        $this->suspender()->assertOk()->assertJsonPath('suspendido_en', '2026-10-12 08:20:00');

        $this->assertDatabaseHas('suspensiones_examen', [
            'examen_id' => $this->examen->id,
            'motivo' => 'Corte de luz',
            'reanudado_en' => null,
        ]);
        $this->assertDatabaseHas('bitacora_operaciones', ['operacion' => 'examen.suspender', 'registro_id' => $this->examen->id]);

        $this->fijarHora('2026-10-12 08:35:00');
        $this->reanudar()->assertOk()->assertJsonPath('reanudado_en', '2026-10-12 08:35:00');

        $this->assertDatabaseHas('suspensiones_examen', ['examen_id' => $this->examen->id, 'reanudado_en' => '2026-10-12 08:35:00']);
        $this->assertDatabaseHas('bitacora_operaciones', ['operacion' => 'examen.reanudar', 'registro_id' => $this->examen->id]);
    }

    public function test_an_exam_that_is_not_open_cannot_be_suspended(): void
    {
        $manana = $this->examen($this->docente, [$this->grupo($this->docente, $this->asignatura('Cálculo I'))], [$this->aula], [
            'fecha' => '2026-10-13',
        ]);

        $this->suspender($manana->id)->assertStatus(409)->assertJsonPath('codigo', 'EXAMEN_NO_ABIERTO');

        $this->actingAs($this->cuentaDocente)->postJson("/api/examenes/{$this->examen->id}/finalizar")->assertOk();
        $this->suspender()->assertStatus(409)->assertJsonPath('codigo', 'EXAMEN_NO_ABIERTO');

        $this->assertDatabaseCount('suspensiones_examen', 0);
    }

    public function test_it_is_not_suspended_twice_nor_resumed_without_a_suspension(): void
    {
        $this->reanudar()->assertStatus(409)->assertJsonPath('codigo', 'EXAMEN_NO_SUSPENDIDO');

        $this->suspender()->assertOk();
        $this->suspender()->assertStatus(409)->assertJsonPath('codigo', 'EXAMEN_SUSPENDIDO');

        $this->assertDatabaseCount('suspensiones_examen', 1);
    }

    public function test_another_teacher_cannot_suspend_it(): void
    {
        $this->actingAs($this->docenteAjeno())
            ->postJson("/api/examenes/{$this->examen->id}/suspender", ['motivo' => 'Corte de luz'])
            ->assertForbidden();

        $this->assertDatabaseCount('suspensiones_examen', 0);
    }

    public function test_the_door_does_not_admit_while_suspended_and_admits_again_after_resuming(): void
    {
        [$primero, $segundo] = $this->habilitados(2);
        $this->suspender()->assertOk();

        $this->verificar($this->cuentaDocente, $primero->codigo_universitario)
            ->assertOk()
            ->assertJsonPath('veredicto', 'examen_suspendido')
            ->assertJsonPath('dato', 'El docente suspendió el examen.');
        $this->assertDatabaseMissing('ingresos', ['estudiante_id' => $primero->id]);

        $this->reanudar()->assertOk();

        $this->verificar($this->cuentaDocente, $segundo->codigo_universitario)
            ->assertOk()
            ->assertJsonPath('veredicto', 'admitido');
    }

    public function test_in_progress_shows_the_reason_of_the_suspension(): void
    {
        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/examenes/{$this->examen->id}/en-curso")
            ->assertOk()
            ->assertJsonPath('suspendido', false)
            ->assertJsonPath('motivo_suspension', null);

        $this->suspender()->assertOk();

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/examenes/{$this->examen->id}/en-curso")
            ->assertOk()
            ->assertJsonPath('suspendido', true)
            ->assertJsonPath('motivo_suspension', 'Corte de luz')
            ->assertJsonPath('descripcion_suspension', 'Se reanuda al volver la luz.');
    }

    public function test_a_suspended_exam_without_entries_can_be_deleted(): void
    {
        $this->suspender()->assertOk();

        $this->actingAs($this->cuentaDocente)
            ->deleteJson("/api/examenes/{$this->examen->id}")
            ->assertSuccessful();

        $this->assertDatabaseMissing('examenes', ['id' => $this->examen->id]);
        $this->assertDatabaseCount('suspensiones_examen', 0);
    }

    public function test_an_offline_station_that_admitted_during_the_suspension_is_flagged(): void
    {
        [$durante, $despues] = $this->habilitados(2);
        DB::table('sesiones_sin_conexion')->insert([
            'dispositivo' => self::DISPOSITIVO,
            'aula_id' => $this->aula->id,
            'user_id' => $this->cuentaDocente->id,
            'instantanea_en' => '2026-10-12 08:00:00',
            'examenes' => json_encode([$this->examen->id]),
            'estado' => 'DESCARGADA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->suspender()->assertOk();
        $this->fijarHora('2026-10-12 08:35:00');
        $this->reanudar()->assertOk();
        $this->fijarHora('2026-10-12 08:40:00');

        $evento = fn (string $uid, int $estudianteId, string $hora): array => [
            'uid' => $uid,
            'hora' => $this->iso($hora),
            'medio' => 'CODIGO',
            'resultado' => 'ADMITIDO',
            'examen_id' => $this->examen->id,
            'estudiante_id' => $estudianteId,
        ];

        $this->actingAs($this->cuentaDocente)->postJson("/api/puerta/aulas/{$this->aula->id}/sincronizacion", [
            'dispositivo' => self::DISPOSITIVO,
            'instantanea_en' => $this->iso('2026-10-12 08:00:00'),
            'eventos' => [
                $evento('evento-0001-a', $durante->id, '2026-10-12 08:25:00'),
                $evento('evento-0002-a', $despues->id, '2026-10-12 08:36:00'),
            ],
        ])->assertOk()->assertJsonPath('ingresos', 2)->assertJsonPath('irregularidades', 1);

        // Entro de verdad: el ingreso se asienta y el docente lo revisa.
        $this->assertDatabaseHas('irregularidades_sin_conexion', [
            'tipo' => 'ADMITIDO_EN_SUSPENSION',
            'gravedad' => 'ALTA',
            'estudiante_id' => $durante->id,
        ]);
        $this->assertDatabaseMissing('irregularidades_sin_conexion', ['estudiante_id' => $despues->id]);
    }
}
