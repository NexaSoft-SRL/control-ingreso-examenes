<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Academico\Domain\Models\Asignatura;
use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * El equipo del examen: cada docente del examen suma y quita a sus
 * auxiliares; quien lo registro quita a cualquiera menos a quien esta a
 * cargo de un aula.
 */
final class EquipoDeExamenTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    private Docente $creador;

    private Docente $colaborador;

    private Asignatura $asignatura;

    private Grupo $propio;

    private Grupo $ajeno;

    private Aula $aulaA;

    private Aula $aulaB;

    private int $examenId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creador = $this->docente('Blanco Coca Leticia');
        $this->colaborador = $this->docente('Taborga Acha Fidel');
        $this->asignatura = $this->asignatura('Taller de Ingeniería de Software', '2010024');
        $this->propio = $this->grupo($this->creador, $this->asignatura, '1');
        $this->ajeno = $this->grupo($this->colaborador, $this->asignatura, '2');
        $this->estudiantesInscritos($this->propio, 2);
        $this->estudiantesInscritos($this->ajeno, 2);
        $this->aulaA = $this->aula('691A');
        $this->aulaB = $this->aula('691B');

        $id = $this->actingAs($this->cuenta($this->creador))
            ->postJson('/api/examenes', $this->cuerpo($this->asignatura, [$this->propio, $this->ajeno], [$this->aulaA, $this->aulaB]))
            ->assertCreated()
            ->json('data.id');
        $this->assertIsInt($id);
        $this->examenId = $id;
    }

    private function id(User $cuenta): int
    {
        $clave = $cuenta->getKey();

        if (! is_int($clave)) {
            $this->fail('El identificador de la cuenta debía ser entero.');
        }

        return $clave;
    }

    /**
     * El equipo que ve esa cuenta en el detalle, por identificador.
     *
     * @return array<int, array{es_docente: bool, es_auxiliar: bool, a_cargo: bool, agregado_por: int|null, agregado_por_nombre: string|null, puede_quitar: bool}>
     */
    private function equipoVistoPor(User $quien): array
    {
        $equipo = $this->actingAs($quien)
            ->getJson("/api/examenes/{$this->examenId}")
            ->assertOk()
            ->json('data.equipo');
        $this->assertIsArray($equipo);

        $porId = [];

        foreach ($equipo as $persona) {
            $this->assertIsArray($persona);
            $this->assertIsInt($persona['id']);
            $this->assertIsBool($persona['es_docente']);
            $this->assertIsBool($persona['es_auxiliar']);
            $this->assertIsBool($persona['a_cargo']);
            $this->assertIsBool($persona['puede_quitar']);
            $porId[$persona['id']] = [
                'es_docente' => $persona['es_docente'],
                'es_auxiliar' => $persona['es_auxiliar'],
                'a_cargo' => $persona['a_cargo'],
                'agregado_por' => is_int($persona['agregado_por']) ? $persona['agregado_por'] : null,
                'agregado_por_nombre' => is_string($persona['agregado_por_nombre']) ? $persona['agregado_por_nombre'] : null,
                'puede_quitar' => $persona['puede_quitar'],
            ];
        }

        return $porId;
    }

    private function aceptarInvitacion(): void
    {
        $invitacion = DB::table('invitaciones_examen')->where('examen_id', $this->examenId)->value('id');
        $this->assertIsInt($invitacion);

        $this->actingAs($this->cuenta($this->colaborador))
            ->postJson("/api/examenes/invitaciones/{$invitacion}/aceptar")
            ->assertOk();
    }

    /**
     * @return TestResponse<Response>
     */
    private function sumar(User $quien, User $auxiliar): TestResponse
    {
        return $this->actingAs($quien)
            ->postJson("/api/examenes/{$this->examenId}/equipo", ['usuario_id' => $this->id($auxiliar)]);
    }

    /**
     * @return TestResponse<Response>
     */
    private function quitar(User $quien, User $auxiliar): TestResponse
    {
        return $this->actingAs($quien)
            ->deleteJson("/api/examenes/{$this->examenId}/equipo/{$this->id($auxiliar)}");
    }

    private function filasDeApoyo(User $auxiliar): int
    {
        return DB::table('examen_aula_apoyo as apoyo')
            ->join('examen_aula as asignada', 'asignada.id', '=', 'apoyo.examen_aula_id')
            ->where('asignada.examen_id', $this->examenId)
            ->where('apoyo.user_id', $this->id($auxiliar))
            ->count();
    }

    public function test_the_creator_adds_an_assistant_to_every_room_and_it_is_logged(): void
    {
        $auxiliar = $this->auxiliarConRol();
        $creador = $this->cuenta($this->creador);

        $this->sumar($creador, $auxiliar)->assertOk()->assertJsonCount(2, 'data.equipo');

        $this->assertSame(2, $this->filasDeApoyo($auxiliar));
        $this->assertSame(
            [$this->id($creador)],
            DB::table('examen_aula_apoyo')->where('user_id', $this->id($auxiliar))->pluck('agregado_por')->unique()->values()->all(),
        );

        // El equipo trae a quien registró (a cargo de las aulas) y al auxiliar de apoyo.
        $suyo = $this->equipoVistoPor($creador)[$this->id($auxiliar)];
        $this->assertTrue($suyo['es_auxiliar']);
        $this->assertFalse($suyo['es_docente']);
        $this->assertFalse($suyo['a_cargo']);
        $this->assertSame($this->id($creador), $suyo['agregado_por']);
        $this->assertTrue($suyo['puede_quitar']);

        $descripcion = DB::table('bitacora_operaciones')->where('operacion', 'examen.equipo_sumar')->value('descripcion');
        $this->assertIsString($descripcion);
        $this->assertStringContainsString($auxiliar->name, $descripcion);
    }

    public function test_adding_twice_does_not_duplicate_anything(): void
    {
        $auxiliar = $this->auxiliarConRol();

        $this->sumar($this->cuenta($this->creador), $auxiliar)->assertOk();
        $this->sumar($this->cuenta($this->creador), $auxiliar)->assertOk();

        $this->assertSame(2, $this->filasDeApoyo($auxiliar));
        $this->assertSame(1, DB::table('bitacora_operaciones')->where('operacion', 'examen.equipo_sumar')->count());
    }

    public function test_a_collaborating_teacher_adds_their_own_assistant(): void
    {
        $this->aceptarInvitacion();
        $creador = $this->cuenta($this->creador);
        $colaborador = $this->cuenta($this->colaborador);
        $suyo = $this->auxiliarConRol();
        $delCreador = $this->auxiliarConRol();
        $this->sumar($creador, $delCreador)->assertOk();

        $this->sumar($colaborador, $suyo)->assertOk()->assertJsonCount(3, 'data.equipo');

        $this->assertSame(2, $this->filasDeApoyo($suyo));

        // Quien lo registró ve quién sumó a cada uno y quita a cualquiera.
        $comoCreador = $this->equipoVistoPor($creador);
        $this->assertSame($this->id($colaborador), $comoCreador[$this->id($suyo)]['agregado_por']);
        $this->assertSame('Taborga Acha Fidel', $comoCreador[$this->id($suyo)]['agregado_por_nombre']);
        $this->assertTrue($comoCreador[$this->id($suyo)]['puede_quitar']);
        $this->assertTrue($comoCreador[$this->id($delCreador)]['puede_quitar']);

        // El colaborador solo quita a los suyos.
        $comoColaborador = $this->equipoVistoPor($colaborador);
        $this->assertTrue($comoColaborador[$this->id($suyo)]['puede_quitar']);
        $this->assertFalse($comoColaborador[$this->id($delCreador)]['puede_quitar']);
    }

    public function test_the_apoyo_in_the_detail_says_who_added_it(): void
    {
        $auxiliar = $this->auxiliarConRol();
        $creador = $this->cuenta($this->creador);
        $this->sumar($creador, $auxiliar)->assertOk();

        $this->actingAs($creador)
            ->getJson("/api/examenes/{$this->examenId}")
            ->assertOk()
            ->assertJsonPath('data.aulas_detalle.0.apoyo.0.id', $auxiliar->getKey())
            ->assertJsonPath('data.aulas_detalle.0.apoyo.0.agregado_por', $this->id($creador));
    }

    public function test_only_the_teachers_of_the_exam_add_to_the_team(): void
    {
        $auxiliar = $this->auxiliarConRol();
        $ajeno = $this->docente('Rojas Vargas Ana');

        // Ni otro docente, ni quien fue invitado y aún no aceptó.
        $this->sumar($this->cuenta($ajeno), $auxiliar)->assertForbidden()->assertJsonPath('alcance', true);
        $this->sumar($this->cuenta($this->colaborador), $auxiliar)->assertForbidden()->assertJsonPath('alcance', true);

        $this->assertSame(0, $this->filasDeApoyo($auxiliar));
    }

    public function test_an_unknown_exam_is_not_found(): void
    {
        $creador = $this->cuenta($this->creador);

        $this->actingAs($creador)
            ->postJson('/api/examenes/999999/equipo', ['usuario_id' => $this->id($this->auxiliarConRol())])
            ->assertNotFound();
        $this->actingAs($creador)
            ->deleteJson('/api/examenes/999999/equipo/1')
            ->assertNotFound();
    }

    public function test_only_an_active_assistant_can_be_added_and_the_exam_needs_rooms(): void
    {
        $creador = $this->cuenta($this->creador);
        $docente = $this->cuenta($this->docente('Otra Docente'));
        $this->sumar($creador, $docente)->assertUnprocessable()->assertJsonValidationErrors('usuario_id');

        $inactivo = $this->auxiliarConRol();
        $inactivo->forceFill(['is_active' => false])->save();
        $this->sumar($creador, $inactivo)->assertUnprocessable()->assertJsonValidationErrors('usuario_id');

        $this->actingAs($creador)
            ->postJson("/api/examenes/{$this->examenId}/equipo", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('usuario_id');

        $sinAulas = $this->actingAs($creador)
            ->postJson('/api/examenes', $this->cuerpo($this->asignatura, [$this->propio], [], ['tipo' => 'SEGUNDO_PARCIAL']))
            ->assertCreated()
            ->json('data.id');
        $this->assertIsInt($sinAulas);
        $this->actingAs($creador)
            ->postJson("/api/examenes/{$sinAulas}/equipo", ['usuario_id' => $this->id($this->auxiliarConRol())])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('usuario_id');
    }

    public function test_the_creator_removes_anyone_and_it_is_logged(): void
    {
        $this->aceptarInvitacion();
        $suyo = $this->auxiliarConRol();
        $this->sumar($this->cuenta($this->colaborador), $suyo)->assertOk();

        $this->quitar($this->cuenta($this->creador), $suyo)->assertOk()->assertJsonCount(1, 'data.equipo');

        $this->assertSame(0, $this->filasDeApoyo($suyo));
        $this->assertDatabaseHas('bitacora_operaciones', ['operacion' => 'examen.equipo_quitar']);
    }

    public function test_a_collaborator_removes_only_their_own_assistant(): void
    {
        $this->aceptarInvitacion();
        $colaborador = $this->cuenta($this->colaborador);
        $suyo = $this->auxiliarConRol();
        $delCreador = $this->auxiliarConRol();
        $this->sumar($this->cuenta($this->creador), $delCreador)->assertOk();
        $this->sumar($colaborador, $suyo)->assertOk();

        $this->quitar($colaborador, $delCreador)->assertForbidden()->assertJsonPath('alcance', true);
        $this->assertSame(2, $this->filasDeApoyo($delCreador));

        $this->quitar($colaborador, $suyo)->assertOk();
        $this->assertSame(0, $this->filasDeApoyo($suyo));
    }

    public function test_someone_who_is_not_in_the_team_is_not_found(): void
    {
        $this->quitar($this->cuenta($this->creador), $this->auxiliarConRol())->assertNotFound();
    }

    public function test_someone_in_charge_of_a_room_cannot_be_removed_until_changed(): void
    {
        $auxiliar = $this->auxiliarConRol();
        $creador = $this->cuenta($this->creador);
        $this->actingAs($creador)
            ->putJson("/api/examenes/{$this->examenId}", $this->cuerpo(
                $this->asignatura,
                [$this->propio, $this->ajeno],
                [],
                ['aulas' => [
                    ['aula_id' => $this->aulaA->id, 'encargado_id' => $this->id($auxiliar), 'apoyo' => []],
                    ['aula_id' => $this->aulaB->id, 'apoyo' => []],
                ]],
            ))
            ->assertOk();

        $this->quitar($creador, $auxiliar)->assertConflict()->assertJsonPath('codigo', 'EQUIPO_A_CARGO');

        $suyo = $this->equipoVistoPor($creador)[$this->id($auxiliar)];
        $this->assertTrue($suyo['a_cargo']);
        $this->assertFalse($suyo['puede_quitar']);
    }

    public function test_saving_the_exam_keeps_who_added_each_person_and_marks_the_new_ones_with_the_creator(): void
    {
        $this->aceptarInvitacion();
        $creador = $this->cuenta($this->creador);
        $colaborador = $this->cuenta($this->colaborador);
        $suyo = $this->auxiliarConRol();
        $nuevo = $this->auxiliarConRol();
        $this->sumar($colaborador, $suyo)->assertOk();

        $this->actingAs($creador)
            ->putJson("/api/examenes/{$this->examenId}", $this->cuerpo(
                $this->asignatura,
                [$this->propio, $this->ajeno],
                [],
                ['aulas' => [
                    ['aula_id' => $this->aulaA->id, 'apoyo' => [$this->id($suyo), $this->id($nuevo)]],
                    ['aula_id' => $this->aulaB->id, 'apoyo' => [$this->id($suyo)]],
                ]],
            ))
            ->assertOk();

        $this->assertSame(
            [$this->id($colaborador)],
            DB::table('examen_aula_apoyo')->where('user_id', $this->id($suyo))->pluck('agregado_por')->unique()->values()->all(),
        );
        $this->assertSame(
            [$this->id($creador)],
            DB::table('examen_aula_apoyo')->where('user_id', $this->id($nuevo))->pluck('agregado_por')->all(),
        );
    }

    public function test_the_team_lists_each_person_once_with_the_roles_it_has(): void
    {
        $auxiliar = $this->auxiliarConRol();
        $creador = $this->cuenta($this->creador);
        $this->sumar($creador, $auxiliar)->assertOk();

        $equipo = $this->equipoVistoPor($creador);

        // Quien registró (a cargo por defecto de las dos aulas) y el auxiliar de apoyo.
        $this->assertCount(2, $equipo);
        $this->assertTrue($equipo[$this->id($creador)]['a_cargo']);
        $this->assertFalse($equipo[$this->id($creador)]['puede_quitar']);
        $this->assertFalse($equipo[$this->id($auxiliar)]['a_cargo']);
    }
}
