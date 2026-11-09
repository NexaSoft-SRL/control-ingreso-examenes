<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Ingreso\Domain\Models\Ingreso;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ruta 66: la expulsion es una incidencia sobre un ingreso, unica, con la
 * hora del servidor.
 */
final class ExpulsionTest extends TestCase
{
    use EscenarioDePuerta;
    use RefreshDatabase;

    private Ingreso $ingreso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->armarExamen();

        [$estudiante] = $this->habilitados(1);
        $this->ingreso = $this->ingresar($this->examen, $estudiante, $this->aula);
    }

    private function ruta(): string
    {
        return "/api/puerta/ingresos/{$this->ingreso->id}/expulsion";
    }

    public function test_an_expulsion_is_recorded_as_an_incident_on_the_entry(): void
    {
        $this->travel(25)->minutes();

        $respuesta = $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta(), ['motivo' => 'Usó el celular', 'hora' => '00:00'])
            ->assertCreated()
            ->assertJsonPath('message', 'Expulsión registrada.');

        $this->assertDatabaseHas('incidencias', [
            'id' => $respuesta->json('incidencia_id'),
            'examen_id' => $this->examen->id,
            'aula_id' => $this->aula->id,
            'estudiante_id' => $this->ingreso->estudiante_id,
            'ingreso_id' => $this->ingreso->id,
            'tipo' => 'EXPULSION',
            'origen' => 'PERSONA',
            'detalle' => 'Usó el celular',
            'ocurrida_en' => '2026-10-12 08:45:00',
            'reportada_por' => $this->cuentaDocente->id,
        ]);
    }

    public function test_the_entry_is_not_modified_by_the_expulsion(): void
    {
        $antes = $this->ingreso->fresh()?->getAttributes();

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta(), ['motivo' => 'Usó el celular'])
            ->assertCreated();

        $this->assertSame($antes, $this->ingreso->fresh()?->getAttributes());
        $this->assertDatabaseCount('ingresos', 1);
    }

    public function test_the_expulsion_is_written_to_the_audit_log(): void
    {
        $respuesta = $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta(), ['motivo' => 'Usó el celular'])
            ->assertCreated();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->cuentaDocente->id,
            'operacion' => 'incidencia.expulsion',
            'tabla_afectada' => 'incidencias',
            'registro_id' => $respuesta->json('incidencia_id'),
        ]);
    }

    public function test_an_entry_is_expelled_only_once(): void
    {
        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta(), ['motivo' => 'Usó el celular'])
            ->assertCreated();

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta(), ['motivo' => 'Otra vez el celular'])
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'YA_EXPULSADO');

        $this->assertDatabaseCount('incidencias', 1);
    }

    public function test_the_expelled_student_appears_as_such_at_the_door(): void
    {
        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta(), ['motivo' => 'Usó el celular'])
            ->assertCreated();

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/puerta/aulas/{$this->aula->id}")
            ->assertOk()
            ->assertJsonPath('ingresos.0.id', $this->ingreso->id)
            ->assertJsonPath('ingresos.0.expulsado', true)
            ->assertJsonPath('total_ingresos', 1);
    }

    public function test_the_reason_needs_five_to_five_hundred_characters(): void
    {
        foreach (['', 'no', str_repeat('a', 501)] as $motivo) {
            $this->actingAs($this->cuentaDocente)
                ->postJson($this->ruta(), ['motivo' => $motivo])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['motivo']);
        }

        $this->actingAs($this->cuentaDocente)
            ->postJson($this->ruta(), ['motivo' => 'no'])
            ->assertJsonPath('errors.motivo.0', 'Mínimo 5 caracteres');

        $this->assertDatabaseCount('incidencias', 0);
    }

    public function test_an_unknown_entry_is_not_found(): void
    {
        $this->actingAs($this->cuentaDocente)
            ->postJson('/api/puerta/ingresos/999999/expulsion', ['motivo' => 'Usó el celular'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Ingreso no encontrado.');
    }

    public function test_expulsions_require_session_and_permission(): void
    {
        $this->postJson($this->ruta(), ['motivo' => 'Usó el celular'])->assertUnauthorized();

        $this->actingAs(UserFactory::new()->createOne())
            ->postJson($this->ruta(), ['motivo' => 'Usó el celular'])
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'punto_control');
    }

    public function test_only_who_attends_the_room_of_the_entry_can_expel(): void
    {
        $otra = $this->aula('617');
        $this->sumarAula($otra);
        $deOtraAula = $this->cuentaAuxiliar();
        $this->ponerACargo($this->examen, $otra, $deOtraAula);
        $deEstaAula = $this->cuentaAuxiliar();
        $this->ponerDeApoyo($this->examen, $this->aula, $deEstaAula);

        $this->actingAs($deOtraAula)
            ->postJson($this->ruta(), ['motivo' => 'Usó el celular'])
            ->assertForbidden()
            ->assertJsonPath('alcance', true);

        $this->actingAs($this->docenteAjeno())
            ->postJson($this->ruta(), ['motivo' => 'Usó el celular'])
            ->assertForbidden()
            ->assertJsonPath('alcance', true);

        $this->assertDatabaseCount('incidencias', 0);

        $this->actingAs($deEstaAula)
            ->postJson($this->ruta(), ['motivo' => 'Usó el celular'])
            ->assertCreated();
    }
}
