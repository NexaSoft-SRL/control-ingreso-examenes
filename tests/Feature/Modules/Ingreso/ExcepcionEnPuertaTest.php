<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Ruta 65: la situacion excepcional de la puerta queda como incidencia y
 * solo el cambio de aula admite.
 */
final class ExcepcionEnPuertaTest extends TestCase
{
    use EscenarioDePuerta;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->armarExamen();
    }

    /**
     * @param  array<string, mixed>  $cuerpo
     * @return TestResponse<Response>
     */
    private function excepcion(array $cuerpo): TestResponse
    {
        return $this->actingAs($this->cuentaDocente)
            ->postJson("/api/puerta/aulas/{$this->aula->id}/excepciones", $cuerpo);
    }

    public function test_a_room_change_admits_the_student_as_an_exception(): void
    {
        $otra = $this->aula('617');
        $this->sumarAula($otra);
        [$estudiante] = $this->habilitados(1, $otra);

        $respuesta = $this->excepcion([
            'tipo' => 'CAMBIO_AULA',
            'motivo' => 'Su aula está llena',
            'estudiante_id' => $estudiante->id,
            'examen_id' => $this->examen->id,
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Situación excepcional registrada: cambio de aula.')
            ->assertJsonPath('ingreso.hora', '08:20');

        $this->assertDatabaseHas('ingresos', [
            'id' => $respuesta->json('ingreso.id'),
            'examen_id' => $this->examen->id,
            'estudiante_id' => $estudiante->id,
            'aula_id' => $this->aula->id,
            'es_excepcion' => true,
            'registrado_por' => $this->cuentaDocente->id,
        ]);
        $this->assertDatabaseHas('incidencias', [
            'id' => $respuesta->json('incidencia_id'),
            'examen_id' => $this->examen->id,
            'aula_id' => $this->aula->id,
            'estudiante_id' => $estudiante->id,
            'ingreso_id' => $respuesta->json('ingreso.id'),
            'tipo' => 'CAMBIO_AULA',
            'origen' => 'PERSONA',
            'detalle' => 'Su aula está llena',
            'reportada_por' => $this->cuentaDocente->id,
        ]);
    }

    public function test_the_exception_is_written_to_the_audit_log(): void
    {
        [$estudiante] = $this->habilitados(1);

        $respuesta = $this->excepcion([
            'tipo' => 'CAMBIO_AULA',
            'motivo' => 'Su aula está llena',
            'estudiante_id' => $estudiante->id,
            'examen_id' => $this->examen->id,
        ])->assertCreated();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->cuentaDocente->id,
            'operacion' => 'incidencia.registrar',
            'tabla_afectada' => 'incidencias',
            'registro_id' => $respuesta->json('incidencia_id'),
        ]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'ingreso.registrar',
            'registro_id' => $respuesta->json('ingreso.id'),
        ]);
    }

    public function test_an_identification_problem_is_recorded_without_admitting(): void
    {
        [$estudiante] = $this->habilitados(1);

        $this->excepcion([
            'tipo' => 'PROBLEMA_IDENTIFICACION',
            'motivo' => 'No trae su carnet',
            'estudiante_id' => $estudiante->id,
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Situación excepcional registrada: problema de identificación.')
            ->assertJsonPath('ingreso', null);

        $this->assertDatabaseCount('ingresos', 0);
        $this->assertDatabaseHas('incidencias', [
            'examen_id' => $this->examen->id,
            'estudiante_id' => $estudiante->id,
            'tipo' => 'OTRA',
            'origen' => 'PERSONA',
            'ingreso_id' => null,
        ]);
    }

    public function test_a_not_enabled_situation_is_recorded_without_admitting(): void
    {
        $this->excepcion([
            'tipo' => 'NO_HABILITADO',
            'motivo' => 'Insiste en ingresar sin estar habilitado',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Situación excepcional registrada: estudiante no habilitado.')
            ->assertJsonPath('ingreso', null);

        $this->assertDatabaseCount('ingresos', 0);
        $this->assertDatabaseHas('incidencias', [
            'tipo' => 'INTENTO_NO_AUTORIZADO',
            'origen' => 'PERSONA',
            'estudiante_id' => null,
        ]);
    }

    public function test_the_reason_and_the_type_are_validated(): void
    {
        $this->excepcion(['tipo' => 'CAMBIO_AULA', 'motivo' => 'no'])
            ->assertStatus(422)
            ->assertJsonPath('errors.motivo.0', 'Mínimo 5 caracteres')
            ->assertJsonPath('errors.estudiante_id.0', 'Indique el estudiante.')
            ->assertJsonPath('errors.examen_id.0', 'Indique el examen.');

        $this->excepcion(['tipo' => 'CAMBIO_AMBIENTE', 'motivo' => 'Su aula está llena'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tipo']);

        $this->assertDatabaseCount('incidencias', 0);
    }

    public function test_a_room_change_does_not_admit_a_disabled_student(): void
    {
        [$estudiante] = $this->estudiantesInscritos($this->grupo, 1);
        $this->inhabilitar($this->examen, [$estudiante]);

        $this->excepcion([
            'tipo' => 'CAMBIO_AULA',
            'motivo' => 'Su aula está llena',
            'estudiante_id' => $estudiante->id,
            'examen_id' => $this->examen->id,
        ])
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'NO_HABILITADO');

        $this->assertDatabaseCount('ingresos', 0);
        $this->assertDatabaseCount('incidencias', 0);
    }

    public function test_a_room_change_does_not_admit_someone_who_already_entered_or_was_expelled(): void
    {
        [$ingresado, $expulsado] = $this->habilitados(2);
        $this->ingresar($this->examen, $ingresado, $this->aula);
        $ingreso = $this->ingresar($this->examen, $expulsado, $this->aula);

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/puerta/ingresos/{$ingreso->id}/expulsion", ['motivo' => 'Usó el celular'])
            ->assertCreated();

        $this->excepcion([
            'tipo' => 'CAMBIO_AULA',
            'motivo' => 'Su aula está llena',
            'estudiante_id' => $ingresado->id,
            'examen_id' => $this->examen->id,
        ])->assertStatus(409)->assertJsonPath('codigo', 'YA_INGRESO');

        $this->excepcion([
            'tipo' => 'CAMBIO_AULA',
            'motivo' => 'Su aula está llena',
            'estudiante_id' => $expulsado->id,
            'examen_id' => $this->examen->id,
        ])->assertStatus(409)->assertJsonPath('codigo', 'EXPULSADO');

        $this->assertDatabaseCount('ingresos', 2);
    }

    public function test_a_room_change_does_not_admit_a_student_outside_the_exam(): void
    {
        [$ajeno] = $this->estudiantesInscritos($this->grupo($this->docente), 1);

        $this->excepcion([
            'tipo' => 'CAMBIO_AULA',
            'motivo' => 'Su aula está llena',
            'estudiante_id' => $ajeno->id,
            'examen_id' => $this->examen->id,
        ])->assertStatus(409)->assertJsonPath('codigo', 'NO_INSCRITO');
    }

    public function test_the_exam_must_be_open_in_this_room(): void
    {
        $otroExamen = $this->examen($this->docente, [$this->grupo($this->docente)], [$this->aula('624')]);
        [$estudiante] = $this->habilitados(1);

        $this->excepcion([
            'tipo' => 'CAMBIO_AULA',
            'motivo' => 'Su aula está llena',
            'estudiante_id' => $estudiante->id,
            'examen_id' => $otroExamen->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.examen_id.0', 'El examen no está abierto en esta aula.');
    }

    public function test_a_closed_room_does_not_take_exceptions(): void
    {
        $this->examen->forceFill(['finalizado_en' => '2026-10-12 10:00:00'])->save();
        $this->fijarHora('2026-10-12 11:00:00');

        $this->excepcion(['tipo' => 'NO_HABILITADO', 'motivo' => 'Insiste en ingresar'])
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'AULA_SIN_EXAMEN');
    }

    public function test_exceptions_require_session_permission_and_scope(): void
    {
        $cuerpo = ['tipo' => 'NO_HABILITADO', 'motivo' => 'Insiste en ingresar'];
        $ruta = "/api/puerta/aulas/{$this->aula->id}/excepciones";

        $this->postJson($ruta, $cuerpo)->assertUnauthorized();

        $this->actingAs(UserFactory::new()->createOne())
            ->postJson($ruta, $cuerpo)
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'punto_control');

        $this->actingAs($this->cuentaAuxiliar())
            ->postJson($ruta, $cuerpo)
            ->assertForbidden()
            ->assertJsonPath('alcance', true);

        $this->assertDatabaseCount('incidencias', 0);
    }
}
