<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Habilitacion;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use App\Modules\Examenes\Domain\Models\Examen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * Ruta 56: «Repartir». Los habilitados sin aula van al aula con menos
 * asignados; las aulas no tienen capacidad y no hay ajuste individual.
 */
final class DistribucionExamenTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private User $cuenta;

    private Docente $docente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cuenta = $this->usuarioConPermisos(['habilitacion']);
        $this->docente = $this->docenteConCuenta($this->cuenta);
    }

    public function test_the_enabled_students_without_room_are_spread_evenly(): void
    {
        $grupo = $this->grupo($this->docente);
        $aulas = [$this->aula('691A'), $this->aula('691B'), $this->aula('617')];
        $estudiantes = $this->estudiantesInscritos($grupo, 7);
        $examen = $this->examen($this->docente, [$grupo], $aulas);
        $this->habilitar($examen, $estudiantes);

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/reparto")
            ->assertOk()
            ->assertExactJson([
                'message' => '7 estudiantes en 3 aulas.',
                'repartidos' => 7,
                'por_aula' => [
                    ['aula_id' => $aulas[0]->id, 'nombre' => '691A', 'asignados' => 3],
                    ['aula_id' => $aulas[1]->id, 'nombre' => '691B', 'asignados' => 2],
                    ['aula_id' => $aulas[2]->id, 'nombre' => '617', 'asignados' => 2],
                ],
            ]);

        $this->assertSame(0, $this->sinAula($examen));
    }

    public function test_each_student_goes_to_the_room_with_the_lowest_load(): void
    {
        $grupo = $this->grupo($this->docente);
        $llena = $this->aula();
        $vacia = $this->aula();
        $estudiantes = $this->estudiantesInscritos($grupo, 7);
        $examen = $this->examen($this->docente, [$grupo], [$llena, $vacia]);
        $this->habilitar($examen, array_slice($estudiantes, 0, 4), $llena);
        $this->habilitar($examen, array_slice($estudiantes, 4));

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/reparto")
            ->assertOk()
            ->assertJsonPath('message', '3 estudiantes en 1 aula.')
            ->assertJsonPath('repartidos', 3)
            ->assertJsonPath('por_aula.0.asignados', 4)
            ->assertJsonPath('por_aula.1.asignados', 3);

        foreach (array_slice($estudiantes, 4) as $estudiante) {
            $this->assertSame($vacia->id, $this->aulaDe($examen, $estudiante));
        }
    }

    public function test_a_tie_goes_to_the_first_room_of_the_exam(): void
    {
        $grupo = $this->grupo($this->docente);
        // El orden es el de la asignacion al examen, no el del nombre.
        $primera = $this->aula('Z-900');
        $segunda = $this->aula('A-100');
        [$estudiante] = $this->estudiantesInscritos($grupo, 1);
        $examen = $this->examen($this->docente, [$grupo], [$primera, $segunda]);
        $this->habilitar($examen, [$estudiante]);

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/reparto")
            ->assertOk()
            ->assertJsonPath('message', '1 estudiante en 1 aula.');

        $this->assertSame($primera->id, $this->aulaDe($examen, $estudiante));
    }

    public function test_the_students_are_placed_by_surnames_and_names(): void
    {
        $grupo = $this->grupo($this->docente);
        $primera = $this->aula();
        $segunda = $this->aula();
        [$a, $b, $c] = $this->estudiantesInscritos($grupo, 3);
        $a->update(['apellidos' => 'Zurita', 'nombres' => 'Ana']);
        $b->update(['apellidos' => 'Aguilar', 'nombres' => 'Zoe']);
        $c->update(['apellidos' => 'Aguilar', 'nombres' => 'Beto']);
        $examen = $this->examen($this->docente, [$grupo], [$primera, $segunda]);
        $this->habilitar($examen, [$a, $b, $c]);

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/reparto")
            ->assertOk();

        // Aguilar Beto, Aguilar Zoe, Zurita Ana -> primera, segunda, primera.
        $this->assertSame($primera->id, $this->aulaDe($examen, $c));
        $this->assertSame($segunda->id, $this->aulaDe($examen, $b));
        $this->assertSame($primera->id, $this->aulaDe($examen, $a));
    }

    public function test_who_already_has_a_room_is_not_moved(): void
    {
        $grupo = $this->grupo($this->docente);
        $cargada = $this->aula();
        $otra = $this->aula();
        $estudiantes = $this->estudiantesInscritos($grupo, 6);
        $examen = $this->examen($this->docente, [$grupo], [$cargada, $otra]);
        $ubicados = array_slice($estudiantes, 0, 5);
        $this->habilitar($examen, $ubicados, $cargada);
        $this->habilitar($examen, [$estudiantes[5]]);

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/reparto")
            ->assertOk()
            ->assertJsonPath('repartidos', 1);

        foreach ($ubicados as $estudiante) {
            $this->assertSame($cargada->id, $this->aulaDe($examen, $estudiante));
        }

        $this->assertSame($otra->id, $this->aulaDe($examen, $estudiantes[5]));
    }

    public function test_only_the_enabled_students_get_a_room(): void
    {
        $grupo = $this->grupo($this->docente);
        $aula = $this->aula();
        [$habilitado, $inhabilitado, $sinRevisar] = $this->estudiantesInscritos($grupo, 3);
        $examen = $this->examen($this->docente, [$grupo], [$aula]);
        $this->habilitar($examen, [$habilitado]);
        $this->inhabilitar($examen, [$inhabilitado]);

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/reparto")
            ->assertOk()
            ->assertJsonPath('repartidos', 1);

        $this->assertSame($aula->id, $this->aulaDe($examen, $habilitado));
        $this->assertNull($this->aulaDe($examen, $inhabilitado));
        $this->assertDatabaseMissing('habilitaciones', ['estudiante_id' => $sinRevisar->id]);
    }

    public function test_a_room_has_no_cap(): void
    {
        $grupo = $this->grupo($this->docente);
        $aula = $this->aula();
        $estudiantes = $this->estudiantesInscritos($grupo, 60);
        $examen = $this->examen($this->docente, [$grupo], [$aula]);
        $this->habilitar($examen, $estudiantes);

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/reparto")
            ->assertOk()
            ->assertJsonPath('message', '60 estudiantes en 1 aula.')
            ->assertJsonPath('por_aula.0.asignados', 60);
    }

    public function test_an_exam_without_rooms_cannot_be_distributed(): void
    {
        $grupo = $this->grupo($this->docente);
        $estudiantes = $this->estudiantesInscritos($grupo, 2);
        $examen = $this->examen($this->docente, [$grupo]);
        $this->habilitar($examen, $estudiantes);

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/reparto")
            ->assertConflict()
            ->assertJsonPath('codigo', 'SIN_AULAS');

        $this->assertSame(2, $this->sinAula($examen));
        $this->assertDatabaseCount('bitacora_operaciones', 0);
    }

    public function test_with_nobody_to_place_it_says_so_and_changes_nothing(): void
    {
        $grupo = $this->grupo($this->docente);
        $aula = $this->aula('691A');
        $estudiantes = $this->estudiantesInscritos($grupo, 2);
        $examen = $this->examen($this->docente, [$grupo], [$aula]);
        $this->habilitar($examen, $estudiantes, $aula);

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/reparto")
            ->assertOk()
            ->assertExactJson([
                'message' => 'Sin estudiantes por repartir.',
                'repartidos' => 0,
                'por_aula' => [
                    ['aula_id' => $aula->id, 'nombre' => '691A', 'asignados' => 2],
                ],
            ]);

        $this->assertDatabaseCount('bitacora_operaciones', 0);
    }

    public function test_a_student_changes_room_by_disabling_enabling_and_distributing_again(): void
    {
        $grupo = $this->grupo($this->docente);
        $cargada = $this->aula();
        $libre = $this->aula();
        $estudiantes = $this->estudiantesInscritos($grupo, 3);
        $examen = $this->examen($this->docente, [$grupo], [$cargada, $libre]);
        $this->habilitar($examen, $estudiantes, $cargada);
        $ruta = "/api/examenes/{$examen->id}/habilitaciones";

        $this->actingAs($this->cuenta)
            ->postJson($ruta, ['habilitado' => false, 'motivo' => 'Cambio de aula', 'estudiantes' => [$estudiantes[0]->id]])
            ->assertOk();
        $this->actingAs($this->cuenta)
            ->postJson($ruta, ['habilitado' => true, 'estudiantes' => [$estudiantes[0]->id]])
            ->assertOk()
            ->assertJsonPath('cifras.sin_aula', 1);
        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/reparto")
            ->assertOk()
            ->assertJsonPath('repartidos', 1);

        $this->assertSame($libre->id, $this->aulaDe($examen, $estudiantes[0]));
    }

    public function test_the_distribution_is_recorded_in_the_audit_log(): void
    {
        $grupo = $this->grupo($this->docente);
        $aulas = [$this->aula(), $this->aula()];
        $estudiantes = $this->estudiantesInscritos($grupo, 5);
        $examen = $this->examen($this->docente, [$grupo], $aulas);
        $this->habilitar($examen, $estudiantes);

        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/reparto")
            ->assertOk();

        $this->assertDatabaseCount('bitacora_operaciones', 1);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->cuenta->id,
            'operacion' => 'habilitacion.repartir',
            'tabla_afectada' => 'habilitaciones',
            'registro_id' => $examen->id,
            'descripcion' => "5 estudiante(s) repartido(s) en 2 aula(s) del examen {$examen->id}.",
        ]);
    }

    public function test_the_exam_must_exist(): void
    {
        $this->actingAs($this->cuenta)
            ->postJson('/api/examenes/999999/reparto')
            ->assertNotFound()
            ->assertJsonPath('message', 'Examen no encontrado.');
    }

    public function test_a_guest_cannot_distribute(): void
    {
        $examen = $this->examen($this->docente, [$this->grupo($this->docente)], [$this->aula()]);

        $this->postJson("/api/examenes/{$examen->id}/reparto")->assertUnauthorized();
    }

    public function test_a_role_without_the_permission_cannot_distribute(): void
    {
        [$examen] = $this->examenPorRepartir();

        $this->actingAs($this->usuarioConRol('Auxiliar'))
            ->postJson("/api/examenes/{$examen->id}/reparto")
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'habilitacion');

        $this->assertSame(2, $this->sinAula($examen));
    }

    public function test_a_teacher_that_is_not_of_the_exam_cannot_distribute(): void
    {
        [$examen] = $this->examenPorRepartir();

        $otra = $this->usuarioConPermisos(['habilitacion']);
        $this->docenteConCuenta($otra);

        $this->actingAs($otra)
            ->postJson("/api/examenes/{$examen->id}/reparto")
            ->assertForbidden()
            ->assertExactJson(['message' => 'Este examen no es tuyo.', 'alcance' => true]);

        $this->assertSame(2, $this->sinAula($examen));
    }

    public function test_there_is_no_route_to_move_a_single_student(): void
    {
        [$examen, $estudiante, $aula] = $this->examenPorRepartir();

        $this->actingAs($this->cuenta)
            ->putJson("/api/examenes/{$examen->id}/habilitaciones/{$estudiante->id}/aula", ['aula_id' => $aula->id])
            ->assertNotFound();
        $this->actingAs($this->cuenta)
            ->putJson("/api/examenes/{$examen->id}/distribucion/{$estudiante->id}", ['aula_id' => $aula->id])
            ->assertNotFound();

        $this->assertNull($this->aulaDe($examen, $estudiante));
    }

    /**
     * @return array{0: Examen, 1: Estudiante, 2: Aula}
     */
    private function examenPorRepartir(): array
    {
        $grupo = $this->grupo($this->docente);
        $aula = $this->aula();
        $estudiantes = $this->estudiantesInscritos($grupo, 2);
        $examen = $this->examen($this->docente, [$grupo], [$aula]);
        $this->habilitar($examen, $estudiantes);

        return [$examen, $estudiantes[0], $aula];
    }

    private function aulaDe(Examen $examen, Estudiante $estudiante): ?int
    {
        $aulaId = DB::table('habilitaciones')
            ->where('examen_id', $examen->id)
            ->where('estudiante_id', $estudiante->id)
            ->value('aula_id');

        return is_int($aulaId) ? $aulaId : null;
    }

    private function sinAula(Examen $examen): int
    {
        return DB::table('habilitaciones')
            ->where('examen_id', $examen->id)
            ->where('habilitado', true)
            ->whereNull('aula_id')
            ->count();
    }
}
