<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ruta 64: verificar y registrar en la puerta de un aula, con cada
 * veredicto y por cada medio de identificacion.
 */
final class VerificacionEnPuertaTest extends TestCase
{
    use EscenarioDePuerta;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->armarExamen();
    }

    public function test_a_student_of_the_room_is_admitted_by_university_code(): void
    {
        [$estudiante] = $this->habilitados(1);

        $respuesta = $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
            ->assertOk()
            ->assertJsonPath('veredicto', 'admitido')
            ->assertJsonPath('estudiante.id', $estudiante->id)
            ->assertJsonPath('estudiante.codigo', $estudiante->codigo_universitario)
            ->assertJsonPath('estudiante.nombre', $estudiante->apellidos.' '.$estudiante->nombres)
            ->assertJsonPath('examen.id', $this->examen->id)
            ->assertJsonPath('examen.asignatura', 'Introducción a la Programación')
            ->assertJsonPath('examen.tipo', 'Primer parcial')
            ->assertJsonPath('dato', 'Registrado a las 08:20')
            ->assertJsonPath('ingreso.hora', '08:20');

        $this->assertDatabaseHas('ingresos', [
            'id' => $respuesta->json('ingreso.id'),
            'examen_id' => $this->examen->id,
            'estudiante_id' => $estudiante->id,
            'aula_id' => $this->aula->id,
            'medio' => 'CODIGO',
            'es_excepcion' => false,
            'registrado_por' => $this->cuentaDocente->id,
            'acceso_aula_id' => null,
        ]);
    }

    public function test_a_student_is_admitted_by_identity_document(): void
    {
        [$estudiante] = $this->habilitados(1);

        $this->verificar($this->cuentaDocente, (string) $estudiante->documento_identidad)
            ->assertOk()
            ->assertJsonPath('veredicto', 'admitido');

        $this->assertDatabaseHas('ingresos', [
            'estudiante_id' => $estudiante->id,
            'medio' => 'DOCUMENTO',
        ]);
    }

    public function test_a_student_is_admitted_by_qr_token(): void
    {
        [$estudiante] = $this->habilitados(1);
        $token = $this->emitirCodigo($estudiante);

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/puerta/aulas/{$this->aula->id}/verificaciones", ['token' => $token])
            ->assertOk()
            ->assertJsonPath('veredicto', 'admitido')
            ->assertJsonPath('estudiante.id', $estudiante->id);

        $this->assertDatabaseHas('ingresos', [
            'estudiante_id' => $estudiante->id,
            'medio' => 'QR',
        ]);
    }

    public function test_the_entry_is_written_to_the_audit_log(): void
    {
        [$estudiante] = $this->habilitados(1);

        $respuesta = $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)->assertOk();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->cuentaDocente->id,
            'operacion' => 'ingreso.registrar',
            'tabla_afectada' => 'ingresos',
            'registro_id' => $respuesta->json('ingreso.id'),
        ]);
    }

    public function test_a_disabled_student_is_rejected_with_the_reason_and_who_recorded_it(): void
    {
        [$estudiante] = $this->estudiantesInscritos($this->grupo, 1);
        $this->inhabilitar($this->examen, [$estudiante], 'Adeuda la matrícula del semestre');

        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
            ->assertOk()
            ->assertJsonPath('veredicto', 'no_habilitado')
            ->assertJsonPath('dato', 'Adeuda la matrícula del semestre')
            ->assertJsonPath('pie', 'Registrado por '.$this->cuentaDocente->name.' · 12 oct')
            ->assertJsonMissingPath('ingreso');

        $this->assertDatabaseCount('ingresos', 0);
        $this->assertDatabaseHas('incidencias', [
            'examen_id' => $this->examen->id,
            'aula_id' => $this->aula->id,
            'estudiante_id' => $estudiante->id,
            'tipo' => 'INTENTO_NO_AUTORIZADO',
            'origen' => 'SISTEMA',
            'causa' => 'NO_HABILITADO',
            'reportada_por' => $this->cuentaDocente->id,
        ]);
    }

    public function test_an_unreviewed_student_does_not_enter(): void
    {
        [$estudiante] = $this->estudiantesInscritos($this->grupo, 1);

        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
            ->assertOk()
            ->assertJsonPath('veredicto', 'no_habilitado')
            ->assertJsonPath('dato', 'Sin revisar')
            ->assertJsonPath('pie', null);

        $this->assertDatabaseCount('ingresos', 0);
        $this->assertDatabaseHas('incidencias', ['causa' => 'NO_HABILITADO', 'detalle' => 'Sin revisar']);
    }

    public function test_a_student_of_another_room_of_the_exam_enters_where_he_is(): void
    {
        $otra = $this->aula('617', $this->edificio(null, 'Edificio Central'), 'Planta baja');
        $this->sumarAula($otra);
        [$estudiante] = $this->habilitados(1, $otra);

        // El aula del reparto es una sugerencia: se admite en esta puerta
        // del mismo examen y el ingreso guarda el aula real.
        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
            ->assertOk()
            ->assertJsonPath('veredicto', 'admitido')
            ->assertJsonPath('dato', 'Registrado a las 08:20 · su aula sugerida era la 617')
            ->assertJsonPath('aula_sugerida.nombre', '617');

        $this->assertDatabaseHas('ingresos', [
            'estudiante_id' => $estudiante->id,
            'aula_id' => $this->aula->id,
            'es_excepcion' => false,
        ]);
    }

    public function test_an_enabled_student_without_room_enters_by_any_door_of_the_exam(): void
    {
        [$estudiante] = $this->estudiantesInscritos($this->grupo, 1);
        $this->habilitar($this->examen, [$estudiante]);

        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
            ->assertOk()
            ->assertJsonPath('veredicto', 'admitido')
            ->assertJsonPath('aula_sugerida', null);

        $this->assertDatabaseHas('ingresos', ['estudiante_id' => $estudiante->id, 'aula_id' => $this->aula->id]);
    }

    public function test_the_second_attempt_says_when_and_where_the_student_entered(): void
    {
        [$estudiante] = $this->habilitados(1);

        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
            ->assertJsonPath('veredicto', 'admitido');

        $this->travel(3)->minutes();

        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
            ->assertOk()
            ->assertJsonPath('veredicto', 'ya_ingreso')
            ->assertJsonPath('dato', 'Ingresó a las 08:20 en esta aula');

        $this->assertDatabaseCount('ingresos', 1);
    }

    public function test_the_second_attempt_at_another_door_names_the_room_of_the_entry(): void
    {
        $otra = $this->aula('617');
        $this->sumarAula($otra);
        [$estudiante] = $this->habilitados(1, $otra);
        $this->ingresar($this->examen, $estudiante, $otra);

        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
            ->assertOk()
            ->assertJsonPath('veredicto', 'ya_ingreso')
            ->assertJsonPath('dato', 'Ingresó a las 08:20 en el aula 617');
    }

    public function test_an_expelled_student_does_not_reenter(): void
    {
        [$estudiante] = $this->habilitados(1);
        $ingreso = $this->ingresar($this->examen, $estudiante, $this->aula);

        $this->travel(20)->minutes();

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/puerta/ingresos/{$ingreso->id}/expulsion", ['motivo' => 'Usó el celular'])
            ->assertCreated();

        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
            ->assertOk()
            ->assertJsonPath('veredicto', 'expulsado')
            ->assertJsonPath('dato', 'Expulsado a las 08:40');

        $this->assertDatabaseCount('ingresos', 1);
    }

    public function test_a_student_without_exam_in_the_room_does_not_correspond(): void
    {
        $otroGrupo = $this->grupo($this->docente);
        [$ajeno] = $this->estudiantesInscritos($otroGrupo, 1);

        $this->verificar($this->cuentaDocente, $ajeno->codigo_universitario)
            ->assertOk()
            ->assertJsonPath('veredicto', 'no_corresponde')
            ->assertJsonPath('estudiante.id', $ajeno->id)
            ->assertJsonPath('examen', null)
            ->assertJsonPath('dato', 'No inscrito en los exámenes del aula 691A')
            ->assertJsonPath('pie', 'Intento registrado')
            ->assertJsonPath('examen_correspondiente', null);

        $this->assertDatabaseHas('incidencias', [
            'examen_id' => null,
            'aula_id' => $this->aula->id,
            'estudiante_id' => $ajeno->id,
            'origen' => 'SISTEMA',
            'causa' => 'NO_INSCRITO',
            'examen_correspondiente_id' => null,
        ]);
    }

    public function test_the_attempt_records_the_exam_that_did_correspond(): void
    {
        $otroGrupo = $this->grupo($this->docente, $this->asignatura('Cálculo I'));
        $otraAula = $this->aula('624');
        $otroExamen = $this->examen($this->docente, [$otroGrupo], [$otraAula]);
        [$ajeno] = $this->habilitados(1, $otraAula, $otroExamen, $otroGrupo);

        $this->verificar($this->cuentaDocente, $ajeno->codigo_universitario)
            ->assertOk()
            ->assertJsonPath('veredicto', 'no_corresponde')
            ->assertJsonPath('examen_correspondiente.texto', 'Primer parcial · Cálculo I')
            ->assertJsonPath('examen_correspondiente.aula', '624');

        $this->assertDatabaseHas('incidencias', [
            'estudiante_id' => $ajeno->id,
            'causa' => 'NO_INSCRITO',
            'examen_correspondiente_id' => $otroExamen->id,
        ]);
    }

    public function test_an_unknown_token_is_recorded_as_invalid_code(): void
    {
        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/puerta/aulas/{$this->aula->id}/verificaciones", ['token' => str_repeat('x', 48)])
            ->assertOk()
            ->assertJsonPath('veredicto', 'no_corresponde')
            ->assertJsonPath('estudiante', null)
            ->assertJsonPath('pie', 'Intento registrado');

        $this->assertDatabaseHas('incidencias', [
            'aula_id' => $this->aula->id,
            'estudiante_id' => null,
            'origen' => 'SISTEMA',
            'causa' => 'CODIGO_INVALIDO',
        ]);
    }

    public function test_a_token_of_an_exam_outside_the_room_does_not_correspond(): void
    {
        $otroGrupo = $this->grupo($this->docente, $this->asignatura('Cálculo I'));
        $otraAula = $this->aula('624');
        $otroExamen = $this->examen($this->docente, [$otroGrupo], [$otraAula]);
        [$ajeno] = $this->habilitados(1, $otraAula, $otroExamen, $otroGrupo);
        $token = $this->emitirCodigo($ajeno, $otroExamen);

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/puerta/aulas/{$this->aula->id}/verificaciones", ['token' => $token])
            ->assertOk()
            ->assertJsonPath('veredicto', 'no_corresponde')
            ->assertJsonPath('estudiante.id', $ajeno->id)
            ->assertJsonPath('examen_correspondiente.aula', '624');

        $this->assertDatabaseHas('incidencias', [
            'causa' => 'NO_INSCRITO',
            'examen_correspondiente_id' => $otroExamen->id,
        ]);
        $this->assertDatabaseCount('ingresos', 0);
    }

    public function test_an_identifier_outside_the_register_is_a_field_error(): void
    {
        $this->verificar($this->cuentaDocente, '99999999')
            ->assertStatus(422)
            ->assertJsonPath('errors.identificador.0', 'Sin registro');

        $this->assertDatabaseCount('incidencias', 0);
    }

    public function test_the_identifier_must_have_five_to_ten_digits(): void
    {
        foreach (['1234', '12345678901', 'ABC12345', '5003748-1ABC'] as $malo) {
            $this->verificar($this->cuentaDocente, $malo)
                ->assertStatus(422)
                ->assertJsonPath('errors.identificador.0', 'Código o C.I. no válido');
        }

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/puerta/aulas/{$this->aula->id}/verificaciones", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['token', 'identificador']);
    }

    public function test_the_door_opens_sixty_minutes_before_the_start(): void
    {
        [$estudiante] = $this->habilitados(1);

        // El examen empieza 08:15: a las 07:14 aun no abre; a las 07:15 si.
        $this->fijarHora('2026-10-12 07:14:59');
        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'AULA_SIN_EXAMEN');

        $this->fijarHora('2026-10-12 07:15:00');
        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
            ->assertOk()
            ->assertJsonPath('veredicto', 'admitido')
            ->assertJsonPath('ingreso.hora', '07:15');
    }

    public function test_the_door_stays_open_past_the_planned_end_until_the_teacher_finalizes(): void
    {
        [$primero, $segundo, $tercero] = $this->habilitados(3);

        // 08:15 + 90 min = 09:45: el examen se paso y nadie lo finalizo.
        $this->fijarHora('2026-10-12 09:45:00');
        $this->verificar($this->cuentaDocente, $primero->codigo_universitario)
            ->assertOk()
            ->assertJsonPath('veredicto', 'admitido');

        $this->fijarHora('2026-10-12 10:05:00');
        $this->verificar($this->cuentaDocente, $segundo->codigo_universitario)
            ->assertOk()
            ->assertJsonPath('veredicto', 'admitido');

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/examenes/{$this->examen->id}/finalizar")
            ->assertOk()
            ->assertJsonPath('finalizado_en', '2026-10-12 10:05:00');

        $this->fijarHora('2026-10-12 10:05:01');
        $this->verificar($this->cuentaDocente, $tercero->codigo_universitario)
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'AULA_SIN_EXAMEN');

        $this->assertDatabaseCount('ingresos', 2);
    }

    public function test_the_entry_time_is_the_servers_not_the_clients(): void
    {
        [$estudiante] = $this->habilitados(1);

        $this->actingAs($this->cuentaDocente)
            ->postJson("/api/puerta/aulas/{$this->aula->id}/verificaciones", [
                'identificador' => $estudiante->codigo_universitario,
                'registrado_en' => '2020-01-01 00:00:00',
                'hora' => '00:00:00',
            ])
            ->assertOk()
            ->assertJsonPath('ingreso.hora', '08:20');

        $this->assertDatabaseHas('ingresos', [
            'estudiante_id' => $estudiante->id,
            'registrado_en' => '2026-10-12 08:20:00',
        ]);
    }

    public function test_a_guest_cannot_verify(): void
    {
        $this->postJson("/api/puerta/aulas/{$this->aula->id}/verificaciones", ['identificador' => '202100001'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'No hay una sesión activa.');
    }

    public function test_an_account_without_the_permission_cannot_verify(): void
    {
        $this->verificar(UserFactory::new()->createOne(), '202100001')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'punto_control');
    }

    public function test_a_teacher_of_another_exam_cannot_use_this_door(): void
    {
        [$estudiante] = $this->habilitados(1);

        $this->verificar($this->docenteAjeno(), $estudiante->codigo_universitario)
            ->assertForbidden()
            ->assertJsonPath('alcance', true);

        $this->assertDatabaseCount('ingresos', 0);
    }

    public function test_an_assistant_verifies_only_at_the_room_it_attends(): void
    {
        $otra = $this->aula('617');
        $this->sumarAula($otra);
        $auxiliar = $this->cuentaAuxiliar();
        $this->ponerDeApoyo($this->examen, $this->aula, $auxiliar);
        [$aqui] = $this->habilitados(1);
        [$alla] = $this->habilitados(1, $otra);

        $this->verificar($auxiliar, $aqui->codigo_universitario)
            ->assertOk()
            ->assertJsonPath('veredicto', 'admitido');

        $this->verificar($auxiliar, $alla->codigo_universitario, $otra)
            ->assertForbidden()
            ->assertJsonPath('alcance', true);

        $this->assertDatabaseHas('ingresos', ['estudiante_id' => $aqui->id, 'registrado_por' => $auxiliar->id]);
        $this->assertDatabaseMissing('ingresos', ['estudiante_id' => $alla->id]);
    }

    public function test_an_unknown_room_is_not_found(): void
    {
        $this->actingAs($this->cuentaDocente)->postJson('/api/puerta/aulas/999999/verificaciones', ['identificador' => '202100001'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Aula no encontrada.');
    }

    public function test_an_identity_document_with_its_complement_is_found_however_it_is_typed(): void
    {
        [$estudiante] = $this->habilitados(1);
        DB::table('estudiantes')->where('id', $estudiante->id)->update(['documento_identidad' => '5003748-1A']);

        $this->verificar($this->cuentaDocente, '5003748 1a')
            ->assertOk()
            ->assertJsonPath('veredicto', 'admitido')
            ->assertJsonPath('estudiante.id', $estudiante->id);
    }

    public function test_an_entry_well_after_the_start_is_marked_late(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->travel(30)->minutes();

        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
            ->assertJsonPath('veredicto', 'admitido')
            ->assertJsonPath('tarde', 35)
            ->assertJsonPath('pie', 'Tarde · 35 min después del inicio');
    }

    public function test_reading_a_repeated_entry_again_does_not_pile_up_incidents(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario);
        $this->travel(5)->minutes();

        for ($i = 0; $i < 3; $i++) {
            $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
                ->assertJsonPath('veredicto', 'ya_ingreso');
        }

        $this->assertSame(1, DB::table('incidencias')->where('tipo', 'INGRESO_REPETIDO')->count());
    }

    public function test_reading_a_rejected_student_again_does_not_pile_up_attempts(): void
    {
        [$estudiante] = $this->estudiantesInscritos($this->grupo, 1);
        [$ajeno] = $this->estudiantesInscritos($this->grupo($this->docente), 1);

        for ($i = 0; $i < 3; $i++) {
            $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario)
                ->assertJsonPath('veredicto', 'no_habilitado');
            $this->verificar($this->cuentaDocente, $ajeno->codigo_universitario)
                ->assertJsonPath('veredicto', 'no_corresponde');
        }
        $this->assertSame(2, DB::table('incidencias')->count());

        $this->travel(11)->minutes();
        $this->verificar($this->cuentaDocente, $estudiante->codigo_universitario);
        $this->assertSame(3, DB::table('incidencias')->count());
    }
}
