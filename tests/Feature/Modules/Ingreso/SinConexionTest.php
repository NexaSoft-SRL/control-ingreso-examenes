<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * El modo sin conexion de la puerta: la instantanea que se descarga, lo que
 * el puesto envia al volver la conexion y las irregularidades que el
 * servidor detecta para que el docente las revise.
 */
final class SinConexionTest extends TestCase
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
    private function descargar(string $dispositivo = self::DISPOSITIVO)
    {
        return $this->actingAs($this->cuentaDocente)
            ->getJson("/api/puerta/aulas/{$this->aula->id}/instantanea?dispositivo={$dispositivo}");
    }

    /**
     * @param  list<array<string, mixed>>  $eventos
     * @return TestResponse<JsonResponse>
     */
    private function sincronizar(array $eventos, ?string $instantanea = null, string $dispositivo = self::DISPOSITIVO)
    {
        return $this->actingAs($this->cuentaDocente)->postJson("/api/puerta/aulas/{$this->aula->id}/sincronizacion", [
            'dispositivo' => $dispositivo,
            'instantanea_en' => $instantanea ?? $this->iso('2026-10-12 08:00:00'),
            'eventos' => $eventos,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function evento(string $uid, int $estudianteId, string $resultado = 'ADMITIDO', string $hora = '2026-10-12 08:19:00', ?int $examenId = null): array
    {
        return [
            'uid' => $uid,
            'hora' => $this->iso($hora),
            'medio' => 'CODIGO',
            'resultado' => $resultado,
            'examen_id' => $examenId ?? $this->examen->id,
            'estudiante_id' => $estudianteId,
        ];
    }

    private function prepararSesion(string $dispositivo = self::DISPOSITIVO, string $instantanea = '2026-10-12 08:00:00'): void
    {
        DB::table('sesiones_sin_conexion')->insert([
            'dispositivo' => $dispositivo,
            'aula_id' => $this->aula->id,
            'user_id' => $this->cuentaDocente->id,
            'instantanea_en' => $instantanea,
            'examenes' => json_encode([$this->examen->id]),
            'estado' => 'DESCARGADA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ----- La instantanea -------------------------------------------------

    public function test_the_snapshot_has_the_enrolled_students_their_condition_and_only_the_hash_of_each_qr(): void
    {
        [$habilitado] = $this->habilitados(1);
        [$sin] = $this->estudiantesInscritos($this->grupo, 1);
        $habilitado->update(['documento_identidad' => '5003748']);
        $token = $this->emitirCodigo($habilitado);

        $respuesta = $this->descargar()
            ->assertOk()
            ->assertJsonPath('aula.id', $this->aula->id)
            ->assertJsonPath('examenes.0.id', $this->examen->id)
            ->assertJsonPath('dispositivo', self::DISPOSITIVO);

        $contenido = (string) $respuesta->getContent();
        // Ni el QR en claro, ni el carnet, ni la foto salen de aqui.
        $this->assertStringNotContainsString($token, $contenido);
        $this->assertStringNotContainsString('5003748', $contenido);
        $this->assertStringNotContainsString('foto', $contenido);
        $respuesta
            ->assertJsonPath('claves.'.hash('sha256', $token).'.examen_id', $this->examen->id)
            ->assertJsonPath('claves.'.hash('sha256', $token).'.estudiante_id', $habilitado->id);

        $estudiantes = $respuesta->json('estudiantes');
        self::assertIsArray($estudiantes);
        $condiciones = [];

        foreach ($estudiantes as $fila) {
            self::assertIsArray($fila);
            $examenes = $fila['examenes'];
            self::assertIsArray($examenes);
            $primero = $examenes[0];
            self::assertIsArray($primero);
            self::assertIsInt($fila['id']);
            $condiciones[$fila['id']] = $primero['condicion'];
        }

        self::assertIsArray($condiciones[$habilitado->id]);
        $this->assertTrue($condiciones[$habilitado->id]['habilitado']);
        $this->assertNull($condiciones[$sin->id]);

        $this->assertDatabaseHas('sesiones_sin_conexion', [
            'dispositivo' => self::DISPOSITIVO,
            'aula_id' => $this->aula->id,
            'estado' => 'DESCARGADA',
        ]);
    }

    public function test_the_snapshot_is_only_for_who_controls_the_room(): void
    {
        $this->getJson("/api/puerta/aulas/{$this->aula->id}/instantanea?dispositivo=".self::DISPOSITIVO)->assertUnauthorized();

        $this->actingAs($this->docenteAjeno())
            ->getJson("/api/puerta/aulas/{$this->aula->id}/instantanea?dispositivo=".self::DISPOSITIVO)
            ->assertForbidden();

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/puerta/aulas/{$this->aula->id}/instantanea?dispositivo=corto")
            ->assertStatus(422);
    }

    public function test_the_snapshot_of_the_same_device_and_moment_opens_one_session(): void
    {
        $this->descargar();
        $this->descargar();
        $this->descargar('otro-puesto-0002-xyz');

        $this->assertSame(2, DB::table('sesiones_sin_conexion')->count());
    }

    // ----- La sincronizacion ------------------------------------------------

    public function test_an_entry_decided_without_connection_becomes_an_entry_with_its_local_time(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->descargar();
        $instantanea = DB::table('sesiones_sin_conexion')->value('instantanea_en');
        self::assertIsString($instantanea);

        $this->sincronizar([$this->evento('evento-0001-a', $estudiante->id)], $this->iso($instantanea))
            ->assertOk()
            ->assertJsonPath('ingresos', 1)
            ->assertJsonPath('irregularidades', 0);

        $this->assertDatabaseHas('ingresos', [
            'examen_id' => $this->examen->id,
            'estudiante_id' => $estudiante->id,
            'aula_id' => $this->aula->id,
            'registrado_en' => '2026-10-12 08:19:00',
        ]);
        $this->assertDatabaseHas('eventos_sin_conexion', ['uid' => 'evento-0001-a', 'resultado' => 'ADMITIDO']);
        $this->assertDatabaseHas('sesiones_sin_conexion', ['estado' => 'SINCRONIZADA', 'eventos' => 1]);
    }

    public function test_sending_the_same_batch_twice_applies_each_event_once(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->prepararSesion();
        $lote = [$this->evento('evento-0001-a', $estudiante->id)];

        $this->sincronizar($lote)->assertOk()->assertJsonPath('aplicados', 1)->assertJsonPath('repetidos', 0);
        $this->sincronizar($lote)->assertOk()->assertJsonPath('aplicados', 0)->assertJsonPath('repetidos', 1);

        $this->assertDatabaseCount('ingresos', 1);
        $this->assertDatabaseCount('irregularidades_sin_conexion', 0);
        $this->assertDatabaseCount('eventos_sin_conexion', 1);
    }

    public function test_two_stations_admitting_the_same_student_leave_one_entry_and_a_double_entry_to_review(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->prepararSesion();
        $this->prepararSesion('puesto-0002-ghijkl');

        // El primero por hora cuenta, llegue primero o despues al servidor.
        $this->sincronizar([$this->evento('segundo-0001-b', $estudiante->id, 'ADMITIDO', '2026-10-12 08:19:30')], dispositivo: 'puesto-0002-ghijkl')->assertOk();
        $this->sincronizar([$this->evento('primero-0001-a', $estudiante->id, 'ADMITIDO', '2026-10-12 08:18:00')])->assertOk();

        $this->assertDatabaseCount('ingresos', 1);
        $this->assertDatabaseHas('irregularidades_sin_conexion', [
            'examen_id' => $this->examen->id,
            'estudiante_id' => $estudiante->id,
            'tipo' => 'INGRESO_DOBLE',
            'gravedad' => 'ALTA',
            'estado' => 'PENDIENTE',
        ]);
    }

    public function test_events_of_one_batch_are_applied_in_the_order_they_happened(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->prepararSesion();

        $this->sincronizar([
            $this->evento('tarde-0001-bbb', $estudiante->id, 'ADMITIDO', '2026-10-12 08:19:30'),
            $this->evento('temprano-001-aa', $estudiante->id, 'ADMITIDO', '2026-10-12 08:17:00'),
        ])->assertOk()->assertJsonPath('ingresos', 1)->assertJsonPath('irregularidades', 1);

        $this->assertDatabaseHas('ingresos', ['estudiante_id' => $estudiante->id, 'registrado_en' => '2026-10-12 08:17:00']);
    }

    public function test_a_student_disabled_while_the_station_was_offline_who_entered_is_flagged(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->prepararSesion();
        $this->inhabilitar($this->examen, [$estudiante], 'Adeuda la matrícula');

        $this->sincronizar([$this->evento('evento-0001-a', $estudiante->id)])->assertOk()->assertJsonPath('irregularidades', 1);

        // Entro de verdad: el ingreso se asienta y el docente lo revisa.
        $this->assertDatabaseHas('ingresos', ['estudiante_id' => $estudiante->id]);
        $this->assertDatabaseHas('irregularidades_sin_conexion', ['tipo' => 'ADMITIDO_NO_HABILITADO', 'gravedad' => 'ALTA']);
    }

    public function test_a_student_not_enrolled_who_was_admitted_is_flagged(): void
    {
        $this->prepararSesion();
        $otraGrupo = $this->grupo($this->docente, $this->asignatura('Cálculo I'), '1');
        [$ajeno] = $this->estudiantesInscritos($otraGrupo, 1);

        $this->sincronizar([$this->evento('evento-0001-a', $ajeno->id)])->assertOk();

        $this->assertDatabaseHas('irregularidades_sin_conexion', ['tipo' => 'NO_INSCRITO_ADMITIDO', 'estudiante_id' => $ajeno->id]);
    }

    public function test_a_student_turned_away_who_is_enabled_today_is_flagged_and_the_attempt_is_recorded(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->prepararSesion();

        $this->sincronizar([$this->evento('evento-0001-a', $estudiante->id, 'NO_HABILITADO')])
            ->assertOk()
            ->assertJsonPath('incidencias', 1)
            ->assertJsonPath('irregularidades', 1);

        $this->assertDatabaseHas('irregularidades_sin_conexion', ['tipo' => 'RECHAZADO_HABILITADO', 'gravedad' => 'MEDIA']);
        $this->assertDatabaseHas('incidencias', ['tipo' => 'INTENTO_NO_AUTORIZADO', 'causa' => 'NO_HABILITADO', 'estudiante_id' => $estudiante->id]);
        $this->assertDatabaseCount('ingresos', 0);
    }

    public function test_a_student_rightly_turned_away_leaves_only_the_incident(): void
    {
        [$estudiante] = $this->estudiantesInscritos($this->grupo, 1);
        $this->inhabilitar($this->examen, [$estudiante], 'Debe la práctica 3');
        $this->prepararSesion();

        $this->sincronizar([$this->evento('evento-0001-a', $estudiante->id, 'NO_HABILITADO')])
            ->assertOk()
            ->assertJsonPath('incidencias', 1)
            ->assertJsonPath('irregularidades', 0);
    }

    public function test_a_qr_issued_after_preparing_the_station_is_resolved_by_the_server(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->prepararSesion();
        $token = $this->emitirCodigo($estudiante);

        $this->sincronizar([[
            'uid' => 'evento-0001-a',
            'hora' => $this->iso('2026-10-12 08:19:00'),
            'medio' => 'QR',
            'resultado' => 'CODIGO_INVALIDO',
            'token' => $token,
        ]])->assertOk()->assertJsonPath('irregularidades', 1);

        $this->assertDatabaseHas('irregularidades_sin_conexion', ['tipo' => 'RECHAZADO_HABILITADO', 'estudiante_id' => $estudiante->id]);
    }

    public function test_an_invalid_qr_is_only_an_incident(): void
    {
        $this->prepararSesion();

        $this->sincronizar([[
            'uid' => 'evento-0001-a',
            'hora' => $this->iso('2026-10-12 08:19:00'),
            'medio' => 'QR',
            'resultado' => 'CODIGO_INVALIDO',
            'token' => str_repeat('x', 48),
            'examen_id' => $this->examen->id,
        ]])->assertOk()->assertJsonPath('incidencias', 1)->assertJsonPath('irregularidades', 0);

        $this->assertDatabaseHas('incidencias', ['tipo' => 'INTENTO_NO_AUTORIZADO', 'causa' => 'CODIGO_INVALIDO']);
    }

    public function test_a_repeated_reading_at_the_same_station_is_a_repeated_entry_incident(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->prepararSesion();

        $this->sincronizar([
            $this->evento('evento-0001-a', $estudiante->id, 'ADMITIDO', '2026-10-12 08:15:00'),
            $this->evento('evento-0002-b', $estudiante->id, 'YA_INGRESO', '2026-10-12 08:19:00'),
        ])->assertOk()->assertJsonPath('ingresos', 1)->assertJsonPath('incidencias', 1);

        $this->assertDatabaseHas('incidencias', ['tipo' => 'INGRESO_REPETIDO', 'estudiante_id' => $estudiante->id]);
    }

    public function test_a_time_in_the_future_or_far_from_the_exam_is_doubtful_and_the_entry_takes_the_reception_time(): void
    {
        [$uno, $dos] = $this->habilitados(2);
        $this->prepararSesion();

        $this->sincronizar([
            $this->evento('futuro-0001-aa', $uno->id, 'ADMITIDO', '2026-10-12 15:00:00'),
            $this->evento('lejano-0001-bb', $dos->id, 'ADMITIDO', '2026-10-11 02:00:00'),
        ])->assertOk()->assertJsonPath('ingresos', 2)->assertJsonPath('irregularidades', 2);

        $this->assertDatabaseHas('irregularidades_sin_conexion', ['tipo' => 'HORA_DUDOSA', 'estudiante_id' => $uno->id]);
        $this->assertDatabaseHas('irregularidades_sin_conexion', ['tipo' => 'HORA_DUDOSA', 'estudiante_id' => $dos->id]);
        // Se asientan a la hora de recepcion (08:20), no a la imposible.
        $this->assertDatabaseHas('ingresos', ['estudiante_id' => $uno->id, 'registrado_en' => '2026-10-12 08:20:00']);
    }

    public function test_an_event_of_an_exam_outside_the_room_is_discarded_and_recorded(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->prepararSesion();

        $this->sincronizar([$this->evento('evento-0001-a', $estudiante->id, 'ADMITIDO', '2026-10-12 08:19:00', 999999)])
            ->assertOk()
            ->assertJsonPath('ingresos', 0)
            ->assertJsonPath('irregularidades', 1);

        $this->assertDatabaseHas('irregularidades_sin_conexion', ['tipo' => 'EVENTO_INVALIDO']);
        $this->assertDatabaseCount('ingresos', 0);
    }

    public function test_a_snapshot_that_was_not_downloaded_at_this_station_is_rejected(): void
    {
        [$estudiante] = $this->habilitados(1);

        $this->sincronizar([$this->evento('evento-0001-a', $estudiante->id)])->assertStatus(422);
        $this->assertDatabaseCount('ingresos', 0);
    }

    public function test_the_sync_is_closed_to_who_does_not_control_the_room(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->prepararSesion();

        $this->postJson("/api/puerta/aulas/{$this->aula->id}/sincronizacion", ['dispositivo' => self::DISPOSITIVO, 'instantanea_en' => $this->iso('2026-10-12 08:00:00'), 'eventos' => []])
            ->assertUnauthorized();

        $this->actingAs($this->docenteAjeno())
            ->postJson("/api/puerta/aulas/{$this->aula->id}/sincronizacion", ['dispositivo' => self::DISPOSITIVO, 'instantanea_en' => $this->iso('2026-10-12 08:00:00'), 'eventos' => [$this->evento('evento-0001-a', $estudiante->id)]])
            ->assertForbidden();
    }

    public function test_an_empty_sync_closes_the_session(): void
    {
        $this->prepararSesion();

        $this->sincronizar([])->assertOk()->assertJsonPath('recibidos', 0);

        $this->assertDatabaseHas('sesiones_sin_conexion', ['estado' => 'SINCRONIZADA', 'eventos' => 0]);
    }

    // ----- La revision del docente -------------------------------------------

    public function test_the_teacher_reviews_the_irregularities_and_the_stations_of_the_exam(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->prepararSesion();
        $this->inhabilitar($this->examen, [$estudiante], 'Adeuda la matrícula');
        $this->sincronizar([$this->evento('evento-0001-a', $estudiante->id)])->assertOk();

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/examenes/{$this->examen->id}/sin-conexion")
            ->assertOk()
            ->assertJsonPath('usado', true)
            ->assertJsonPath('resumen.pendientes', 1)
            ->assertJsonPath('resumen.altas', 1)
            ->assertJsonPath('irregularidades.0.tipo', 'ADMITIDO_NO_HABILITADO')
            ->assertJsonPath('irregularidades.0.tipo_texto', 'Admitido sin estar habilitado')
            ->assertJsonPath('sesiones.0.estado', 'SINCRONIZADA')
            ->assertJsonPath('sesiones.0.eventos', 1);
    }

    public function test_a_station_that_prepared_and_never_synced_is_flagged_once_the_exam_is_over(): void
    {
        $this->prepararSesion();

        // Durante el examen todavia puede sincronizar.
        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/examenes/{$this->examen->id}/sin-conexion")
            ->assertOk()
            ->assertJsonPath('resumen.sin_sincronizar', 0);

        $this->travelTo(Carbon::parse('2026-10-12 13:00:00'));

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/examenes/{$this->examen->id}/sin-conexion")
            ->assertOk()
            ->assertJsonPath('resumen.sin_sincronizar', 1)
            ->assertJsonPath('sin_sincronizar.0.estado', 'DESCARGADA');
    }

    public function test_an_irregularity_is_resolved_with_a_note_and_stays_in_the_audit_log(): void
    {
        [$estudiante] = $this->habilitados(1);
        $this->prepararSesion();
        $this->inhabilitar($this->examen, [$estudiante], 'Adeuda la matrícula');
        $this->sincronizar([$this->evento('evento-0001-a', $estudiante->id)])->assertOk();
        $id = DB::table('irregularidades_sin_conexion')->value('id');
        self::assertIsInt($id);
        $url = "/api/sin-conexion/irregularidades/{$id}/resolucion";

        $this->actingAs($this->cuentaDocente)->postJson($url, ['estado' => 'CONFIRMADA', 'nota' => 'abc'])
            ->assertStatus(422)->assertJsonValidationErrors(['nota']);
        $this->actingAs($this->cuentaDocente)->postJson($url, ['estado' => 'OTRA', 'nota' => 'Regularizó la matrícula ese día'])
            ->assertStatus(422)->assertJsonValidationErrors(['estado']);

        $this->actingAs($this->cuentaDocente)->postJson($url, ['estado' => 'CONFIRMADA', 'nota' => 'Regularizó la matrícula ese día'])->assertOk();

        $this->assertDatabaseHas('irregularidades_sin_conexion', ['id' => $id, 'estado' => 'CONFIRMADA', 'nota' => 'Regularizó la matrícula ese día', 'resuelta_por' => $this->cuentaDocente->id]);
        $this->assertDatabaseHas('bitacora_operaciones', ['operacion' => 'sin_conexion.resolver', 'registro_id' => $id]);

        // Una resuelta no se resuelve dos veces.
        $this->actingAs($this->cuentaDocente)->postJson($url, ['estado' => 'DESCARTADA', 'nota' => 'Otra nota distinta'])->assertStatus(422);

        $this->actingAs($this->cuentaDocente)
            ->getJson("/api/examenes/{$this->examen->id}/sin-conexion")
            ->assertJsonPath('resumen.pendientes', 0)
            ->assertJsonPath('resumen.resueltas', 1);
    }

    public function test_only_the_teacher_of_the_exam_reviews_and_resolves(): void
    {
        $this->getJson("/api/examenes/{$this->examen->id}/sin-conexion")->assertUnauthorized();

        [$estudiante] = $this->habilitados(1);
        $this->prepararSesion();
        $this->inhabilitar($this->examen, [$estudiante], 'Adeuda la matrícula');
        $this->sincronizar([$this->evento('evento-0001-a', $estudiante->id)])->assertOk();
        $id = DB::table('irregularidades_sin_conexion')->value('id');
        self::assertIsInt($id);

        $ajeno = $this->usuarioConPermisos(['seguimiento_vivo', 'punto_control']);
        $this->actingAs($ajeno)->getJson("/api/examenes/{$this->examen->id}/sin-conexion")->assertForbidden();
        $this->actingAs($ajeno)->postJson("/api/sin-conexion/irregularidades/{$id}/resolucion", ['estado' => 'CONFIRMADA', 'nota' => 'Intento ajeno'])->assertForbidden();
        $this->actingAs($this->cuentaDocente)->postJson('/api/sin-conexion/irregularidades/999999/resolucion', ['estado' => 'CONFIRMADA', 'nota' => 'No existe'])->assertNotFound();
    }
}
