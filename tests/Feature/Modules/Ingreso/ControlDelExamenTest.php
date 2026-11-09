<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Examenes\Domain\Models\ExamenAula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El panel en vivo del docente: quien controla cada aula y los cambios de
 * aula con el examen en marcha.
 */
final class ControlDelExamenTest extends TestCase
{
    use EscenarioDePuerta;
    use RefreshDatabase;

    private Aula $otra;

    protected function setUp(): void
    {
        parent::setUp();
        $this->armarExamen();
        $this->otra = $this->aula('617');
        $this->sumarAula($this->otra);
    }

    public function test_the_panel_shows_who_is_at_each_door_now(): void
    {
        $auxiliar = $this->auxiliar($this->usuarioConPermisos(['punto_control']));
        $this->ponerApoyo($auxiliar->id, $this->aula);

        $this->actingAs($auxiliar)->getJson("/api/puerta/aulas/{$this->aula->id}")->assertOk();

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/examenes/{$this->examen->id}/control")
            ->assertOk()
            ->assertJsonPath('data.1.nombre', '691A')
            ->assertJsonPath('data.1.personal.1.usuario_id', $auxiliar->id)
            ->assertJsonPath('data.1.personal.1.rol', 'apoyo')
            ->assertJsonPath('data.1.personal.1.visto_en', fn (?string $visto): bool => $visto !== null)
            ->assertJsonPath('data.1.personal.0.visto_en', null);
    }

    public function test_a_person_who_attends_moves_to_another_room(): void
    {
        $auxiliar = $this->auxiliar();
        $this->ponerApoyo($auxiliar->id, $this->aula);

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/examenes/{$this->examen->id}/movimientos", [
                'usuario_id' => $auxiliar->id,
                'de_aula_id' => $this->aula->id,
                'a_aula_id' => $this->otra->id,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Cambio de aula registrado.');

        $destino = ExamenAula::where('examen_id', $this->examen->id)->where('aula_id', $this->otra->id)->value('id');
        $this->assertDatabaseHas('examen_aula_apoyo', ['examen_aula_id' => $destino, 'user_id' => $auxiliar->id]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'examen.mover_personal',
            'descripcion' => "{$auxiliar->name} pasó del aula 691A al aula 617.",
        ]);

        // Ya no esta en la de origen.
        $this->postJson("/api/examenes/{$this->examen->id}/movimientos", [
            'usuario_id' => $auxiliar->id,
            'de_aula_id' => $this->aula->id,
            'a_aula_id' => $this->otra->id,
        ])->assertUnprocessable()->assertJsonPath('errors.usuario_id.0', 'No atiende esa aula.');
    }

    public function test_the_students_of_a_room_that_cannot_be_used_move_to_another(): void
    {
        [$entro, $espera, $otro] = $this->habilitados(3);
        $this->ingresar($this->examen, $entro, $this->aula);

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/examenes/{$this->examen->id}/movimientos", [
                'de_aula_id' => $this->aula->id,
                'a_aula_id' => $this->otra->id,
            ])
            ->assertOk()
            ->assertJsonPath('movidos', 2)
            ->assertJsonPath('message', '2 estudiantes cambiaron de aula.');

        // Quien ya entro queda donde entro.
        $this->assertDatabaseHas('habilitaciones', ['estudiante_id' => $entro->id, 'aula_id' => $this->aula->id]);
        $this->assertDatabaseHas('habilitaciones', ['estudiante_id' => $espera->id, 'aula_id' => $this->otra->id]);
        $this->assertDatabaseHas('habilitaciones', ['estudiante_id' => $otro->id, 'aula_id' => $this->otra->id]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'habilitacion.mover_aula',
            'descripcion' => '2 estudiantes pasaron del aula 691A al aula 617.',
            // Sobre el examen: asi lo encuentra su bitacora.
            'tabla_afectada' => 'examenes',
            'registro_id' => $this->examen->id,
        ]);
    }

    public function test_only_the_teacher_of_the_exam_moves_and_only_between_its_rooms(): void
    {
        $ajena = $this->aula('699');

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/examenes/{$this->examen->id}/movimientos", ['de_aula_id' => $this->aula->id, 'a_aula_id' => $ajena->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.a_aula_id.0', 'El aula no es de este examen.');

        $this->actingAs($this->usuarioConPermisos(['examenes']))
            ->postJson("/api/examenes/{$this->examen->id}/movimientos", ['de_aula_id' => $this->aula->id, 'a_aula_id' => $this->otra->id])
            ->assertForbidden();
        $this->actingAs($this->usuarioConPermisos(['examenes']))
            ->getJson("/api/examenes/{$this->examen->id}/control")
            ->assertForbidden();
    }

    private function ponerApoyo(int $usuarioId, Aula $aula): void
    {
        DB::table('examen_aula_apoyo')->insert([
            'examen_aula_id' => ExamenAula::where('examen_id', $this->examen->id)->where('aula_id', $aula->id)->value('id'),
            'user_id' => $usuarioId,
        ]);
    }
}
