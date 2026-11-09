<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Examenes\Domain\Models\AccesoAula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Ruta 7 y su cierre: quien controla sin cuenta entra con el codigo de
 * seis digitos de un aula y solo alcanza a la puerta de esa aula.
 */
final class AccesoConCodigoDeAulaTest extends TestCase
{
    use EscenarioDePuerta;
    use RefreshDatabase;

    private Aula $otra;

    protected function setUp(): void
    {
        parent::setUp();
        $this->armarExamen();
        $this->ponerCodigoDeAula('482913');

        $this->otra = $this->aula('617');
        $this->sumarAula($this->otra);
    }

    /**
     * @return TestResponse<Response>
     */
    private function entrar(string $codigo = '482913', string $nombre = 'Vargas Arce Sergio'): TestResponse
    {
        return $this->postJson('/api/auth/codigo-aula', ['codigo' => $codigo, 'nombre' => $nombre]);
    }

    /**
     * Pide el acceso y el docente lo autoriza en persona.
     */
    private function entrarAutorizado(): int
    {
        $this->entrar()->assertOk();
        $accesoId = $this->ultimoAcceso();
        $this->comoDocente()
            ->postJson("/api/examenes/{$this->examen->id}/accesos/{$accesoId}/autorizar")
            ->assertOk();
        $this->comoInvitado();

        return $accesoId;
    }

    private function ultimoAcceso(): int
    {
        return AccesoAula::query()->orderByDesc('id')->firstOrFail()->id;
    }

    private function comoDocente(): self
    {
        return $this->actingAs($this->cuentaDocente);
    }

    // La sesion del codigo sigue; solo se olvida la cuenta del docente.
    private function comoInvitado(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_the_room_code_opens_an_access_until_the_end_of_the_exam(): void
    {
        $this->entrar()
            ->assertOk()
            ->assertExactJson(['acceso' => [
                'aula_id' => $this->aula->id,
                'aula' => '691A',
                'examen_id' => $this->examen->id,
                'nombre' => 'Vargas Arce Sergio',
                'hasta' => $this->iso('2026-10-12 09:45:00'),
                'estado' => 'pendiente',
            ]]);

        $this->assertDatabaseHas('accesos_aula', [
            'nombre' => 'Vargas Arce Sergio',
            'ip' => '127.0.0.1',
            'creado_en' => '2026-10-12 08:20:00',
        ]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => null,
            'operacion' => 'acceso_aula.solicitar',
            'tabla_afectada' => 'accesos_aula',
        ]);
    }

    public function test_the_access_waits_until_the_teacher_authorizes_it_in_person(): void
    {
        $this->entrar()->assertOk();
        $accesoId = $this->ultimoAcceso();

        // Sin autorizar, la puerta no abre; quien espera ve su estado.
        $this->getJson("/api/puerta/aulas/{$this->aula->id}")->assertUnauthorized();
        $this->getJson('/api/auth/codigo-aula')->assertOk()->assertJsonPath('acceso.estado', 'pendiente');

        // El docente lo ve en su panel y lo autoriza en otra aula del examen.
        $this->comoDocente()->getJson("/api/examenes/{$this->examen->id}/control")
            ->assertOk()
            ->assertJsonPath('meta.pendientes', 1)
            ->assertJsonPath('data.0.nombre', '617')
            ->assertJsonPath('data.1.accesos.0.nombre', 'Vargas Arce Sergio')
            ->assertJsonPath('data.1.accesos.0.estado', 'pendiente');
        $this->postJson("/api/examenes/{$this->examen->id}/accesos/{$accesoId}/autorizar", ['aula_id' => $this->otra->id])
            ->assertOk()
            ->assertJsonPath('estado', 'autorizado');
        $this->comoInvitado();

        $this->getJson('/api/auth/codigo-aula')
            ->assertOk()
            ->assertJsonPath('acceso.estado', 'autorizado')
            ->assertJsonPath('acceso.aula', '617');
        $this->getJson("/api/puerta/aulas/{$this->otra->id}")->assertOk();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->cuentaDocente->id,
            'operacion' => 'acceso_aula.autorizar',
            'descripcion' => 'Vargas Arce Sergio fue autorizado a controlar el aula 617.',
        ]);
    }

    public function test_the_teacher_moves_rejects_and_cuts_accesses(): void
    {
        $accesoId = $this->entrarAutorizado();
        $examen = $this->examen->id;

        $this->comoDocente()->postJson("/api/examenes/{$examen}/accesos/{$accesoId}/trasladar", ['aula_id' => $this->otra->id])
            ->assertOk()
            ->assertJsonPath('message', 'Acceso movido de aula.');
        $this->comoInvitado();
        $this->getJson("/api/puerta/aulas/{$this->otra->id}")->assertOk();
        $this->getJson("/api/puerta/aulas/{$this->aula->id}")->assertForbidden();

        $this->comoDocente()->postJson("/api/examenes/{$examen}/accesos/{$accesoId}/cortar")->assertOk();
        $this->comoInvitado();
        $this->getJson("/api/puerta/aulas/{$this->otra->id}")->assertUnauthorized();
        $this->getJson('/api/auth/codigo-aula')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'El docente cortó el acceso.');

        $this->entrar('482913', 'Mamani Luis')->assertOk();
        $otroId = $this->ultimoAcceso();
        $this->comoDocente()->postJson("/api/examenes/{$examen}/accesos/{$otroId}/rechazar")->assertOk();
        $this->comoInvitado();
        $this->getJson('/api/auth/codigo-aula')->assertJsonPath('estado', 'rechazado');

        $this->assertDatabaseHas('bitacora_operaciones', ['operacion' => 'acceso_aula.trasladar']);
        $this->assertDatabaseHas('bitacora_operaciones', ['operacion' => 'acceso_aula.cortar']);
        $this->assertDatabaseHas('bitacora_operaciones', ['operacion' => 'acceso_aula.rechazar']);
    }

    public function test_only_a_teacher_of_the_exam_resolves_its_accesses(): void
    {
        $this->entrar()->assertOk();
        $accesoId = $this->ultimoAcceso();
        $ajeno = $this->usuarioConPermisos(['examenes']);

        $this->actingAs($ajeno)
            ->postJson("/api/examenes/{$this->examen->id}/accesos/{$accesoId}/autorizar")
            ->assertForbidden();
        $this->assertDatabaseHas('accesos_aula', ['id' => $accesoId, 'estado' => 'PENDIENTE']);
    }

    public function test_an_unknown_code_is_rejected_without_saying_why(): void
    {
        $this->entrar('000000')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Código no válido.')
            ->assertJsonPath('errors.codigo.0', 'Código no válido.');

        $this->assertDatabaseCount('accesos_aula', 0);
    }

    public function test_the_code_and_the_name_are_validated(): void
    {
        $this->entrar('4829', 'Ve')
            ->assertStatus(422)
            ->assertJsonPath('errors.codigo.0', 'Seis dígitos')
            ->assertJsonPath('errors.nombre.0', 'Mínimo 3 letras, sin signos seguidos');
    }

    public function test_the_code_is_valid_only_inside_the_window_of_its_exam(): void
    {
        // Fuera de hora, el mensaje dice cuando abre o que ya termino.
        $this->fijarHora('2026-10-12 07:14:00');
        $this->entrar()->assertStatus(422)
            ->assertJsonPath('errors.codigo.0', 'La puerta abre el 12 oct a las 07:15.');

        // Pasada la duracion prevista sigue abierto hasta que lo finalizan.
        $this->fijarHora('2026-10-12 09:46:00');
        $this->entrar()->assertOk();
        $this->deleteJson('/api/auth/codigo-aula')->assertNoContent();
        $this->examen->forceFill(['finalizado_en' => '2026-10-12 09:50:00'])->save();
        $this->fijarHora('2026-10-12 09:51:00');
        $this->entrar()->assertStatus(422)
            ->assertJsonPath('errors.codigo.0', 'El examen ya terminó: cerró el 12 oct a las 09:50.');

        $this->fijarHora('2026-10-11 08:20:00');
        $this->entrar()->assertStatus(422)
            ->assertJsonPath('errors.codigo.0', 'La puerta abre el 12 oct a las 07:15.');

        // Un codigo que no es de ningun aula no da pistas.
        $this->fijarHora('2026-10-12 08:20:00');
        $this->entrar('000000')->assertStatus(422)->assertJsonPath('errors.codigo.0', 'Código no válido.');

        // Solo el acceso de las 09:46, antes de finalizar.
        $this->assertDatabaseCount('accesos_aula', 1);
    }

    public function test_after_five_wrong_codes_the_address_waits_five_minutes(): void
    {
        foreach (['000001', '000002', '000003', '000004', '000005'] as $codigo) {
            $this->entrar($codigo)->assertStatus(422);
        }

        $this->entrar()
            ->assertStatus(429)
            ->assertJsonPath('message', 'Demasiados intentos.')
            ->assertJsonStructure(['reintentar_en']);

        $this->travel(6)->minutes();

        $this->entrar()->assertOk();
    }

    public function test_right_codes_do_not_count_so_many_assistants_share_a_network(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->entrar()->assertOk();
        }
    }

    public function test_the_access_lists_only_its_room_with_its_name_and_end(): void
    {
        $this->entrarAutorizado();

        $this->getJson('/api/puerta/aulas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.aula_id', $this->aula->id)
            ->assertJsonPath('data.0.abierta', true)
            ->assertJsonPath('data.0.examenes.0.id', $this->examen->id)
            ->assertJsonPath('acceso.aula_id', $this->aula->id)
            ->assertJsonPath('acceso.aula', '691A')
            ->assertJsonPath('acceso.examen_id', $this->examen->id)
            ->assertJsonPath('acceso.nombre', 'Vargas Arce Sergio')
            ->assertJsonPath('acceso.hasta', $this->iso('2026-10-12 09:45:00'))
            ->assertJsonPath('acceso.estado', 'autorizado');
    }

    public function test_the_access_verifies_at_its_room_and_the_entry_keeps_the_access_not_a_user(): void
    {
        [$estudiante] = $this->habilitados(1);
        $accesoId = $this->entrarAutorizado();

        $this->getJson("/api/puerta/aulas/{$this->aula->id}")->assertOk()->assertJsonPath('abierta', true);

        $this->postJson("/api/puerta/aulas/{$this->aula->id}/verificaciones", [
            'identificador' => $estudiante->codigo_universitario,
        ])->assertOk()->assertJsonPath('veredicto', 'admitido');

        $this->assertDatabaseHas('ingresos', [
            'estudiante_id' => $estudiante->id,
            'registrado_por' => null,
            'acceso_aula_id' => $accesoId,
        ]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => null,
            'operacion' => 'ingreso.registrar',
        ]);
    }

    public function test_the_access_does_not_leave_its_room(): void
    {
        [$estudiante] = $this->habilitados(1, $this->otra);
        $this->entrarAutorizado();

        $this->getJson("/api/puerta/aulas/{$this->otra->id}")
            ->assertForbidden()
            ->assertJsonPath('alcance', true);

        $this->postJson("/api/puerta/aulas/{$this->otra->id}/verificaciones", [
            'identificador' => $estudiante->codigo_universitario,
        ])->assertForbidden();

        $this->postJson("/api/puerta/aulas/{$this->otra->id}/excepciones", [
            'tipo' => 'NO_HABILITADO',
            'motivo' => 'Insiste en ingresar',
        ])->assertForbidden();

        $this->assertDatabaseCount('ingresos', 0);
        $this->assertDatabaseCount('incidencias', 0);
    }

    public function test_the_access_reports_incidents_but_does_not_expel(): void
    {
        [$aqui] = $this->habilitados(1);
        [$alla] = $this->habilitados(1, $this->otra);
        $ingresoAqui = $this->ingresar($this->examen, $aqui, $this->aula);
        $this->ingresar($this->examen, $alla, $this->otra);
        $accesoId = $this->entrarAutorizado();

        $this->postJson("/api/examenes/{$this->examen->id}/incidencias", [
            'aula_id' => $this->aula->id,
            'tipo' => 'OTRA',
            'detalle' => 'Ruido en el pasillo',
        ])
            ->assertCreated()
            ->assertJsonPath('data.por', 'Vargas Arce Sergio')
            ->assertJsonPath('data.rol', 'Código de aula');

        $this->postJson("/api/examenes/{$this->examen->id}/incidencias", [
            'aula_id' => $this->otra->id,
            'tipo' => 'OTRA',
            'detalle' => 'Ruido en el pasillo',
        ])->assertForbidden()->assertJsonPath('alcance', true);

        // Expulsar o registrar una situacion excepcional cambia la situacion
        // de un estudiante: eso lo hace una cuenta.
        $this->postJson("/api/puerta/ingresos/{$ingresoAqui->id}/expulsion", ['motivo' => 'Usó el celular'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Con código de aula solo se verifica y se reportan incidencias.');
        $this->postJson("/api/puerta/aulas/{$this->aula->id}/excepciones", ['tipo' => 'NO_HABILITADO', 'motivo' => 'Insiste en ingresar'])
            ->assertForbidden();

        $this->assertDatabaseHas('incidencias', ['tipo' => 'OTRA', 'acceso_aula_id' => $accesoId]);
        $this->assertDatabaseMissing('incidencias', ['tipo' => 'EXPULSION']);
    }

    public function test_the_access_cannot_use_routes_that_need_an_account(): void
    {
        $this->entrar()->assertOk();

        $this->getJson("/api/examenes/{$this->examen->id}/incidencias")->assertUnauthorized();
        $this->getJson("/api/examenes/{$this->examen->id}/codigos-qr")->assertUnauthorized();
        $this->postJson("/api/examenes/{$this->examen->id}/codigos-qr")->assertUnauthorized();
        $this->getJson('/api/auth/sesion')->assertUnauthorized();
    }

    public function test_regenerating_the_code_closes_the_accesses_opened_with_the_old_one(): void
    {
        $this->entrarAutorizado();
        $this->getJson('/api/puerta/aulas')->assertOk();

        $this->travel(2)->minutes();
        $this->comoDocente()
            ->postJson("/api/examenes/{$this->examen->id}/aulas/{$this->aula->id}/codigo")
            ->assertSuccessful();
        $this->comoInvitado();

        $this->getJson('/api/puerta/aulas')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'No hay una sesión activa.');

        // La fila del acceso sigue ahi: los ingresos la referencian.
        $this->assertDatabaseCount('accesos_aula', 1);
    }

    public function test_the_access_ends_when_the_exam_is_finalized(): void
    {
        $this->entrarAutorizado();

        // Pasada la hora prevista sigue: el examen no se finalizo.
        $this->fijarHora('2026-10-12 09:45:01');
        $this->getJson("/api/puerta/aulas/{$this->aula->id}")->assertOk();

        $this->examen->forceFill(['finalizado_en' => '2026-10-12 09:50:00'])->save();
        $this->fijarHora('2026-10-12 09:50:01');

        $this->getJson("/api/puerta/aulas/{$this->aula->id}")->assertUnauthorized();
    }

    public function test_the_access_can_be_closed(): void
    {
        $this->entrarAutorizado();
        $this->getJson('/api/puerta/aulas')->assertOk();

        $this->deleteJson('/api/auth/codigo-aula')->assertNoContent();

        $this->getJson('/api/puerta/aulas')->assertUnauthorized();
        $this->deleteJson('/api/auth/codigo-aula')->assertNoContent();
    }
}
