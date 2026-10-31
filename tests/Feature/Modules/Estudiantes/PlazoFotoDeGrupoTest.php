<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Estudiantes;

use App\Modules\Academico\Domain\Models\Docente;
use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Estudiantes\Domain\Models\Estudiante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\DatosAcademicos;
use Tests\Support\DatosDeExamen;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * El docente fija hasta que dia sube su foto su grupo (en el mismo plazo
 * de grupo que fija la administracion). Vencido, quien no la subio queda
 * inhabilitado en los examenes del grupo: en la lista de habilitacion y en
 * la puerta. Al subirla vuelve a «sin revisar».
 */
final class PlazoFotoDeGrupoTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private User $cuenta;

    private Docente $docente;

    private Grupo $grupo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-10 10:00:00');

        $this->cuenta = $this->usuarioConPermisos(['mis_grupos', 'habilitacion', 'punto_control', 'reportes_examenes']);
        $this->docente = $this->docenteConCuenta($this->cuenta, 'Blanco Coca Leticia');
        $this->grupo = $this->grupo($this->docente, $this->asignatura('Introducción a la Programación'), '2');
    }

    /**
     * @return TestResponse<Response>
     */
    private function fijar(?string $hasta, ?User $cuenta = null): TestResponse
    {
        return $this->actingAs($cuenta ?? $this->cuenta)
            ->putJson("/api/docente/grupos/{$this->grupo->id}/plazo-foto", ['hasta' => $hasta]);
    }

    private function conFoto(Estudiante $estudiante): void
    {
        DB::table('estudiantes')->where('id', $estudiante->id)->update(['foto' => 'estudiantes/foto.jpg', 'foto_subida_en' => now()]);
    }

    /**
     * @return array<string, array{estado: mixed, motivo: mixed}>
     */
    private function lista(int $examenId): array
    {
        $filas = (array) $this->actingAs($this->cuenta)
            ->getJson("/api/examenes/{$examenId}/habilitaciones")
            ->assertOk()
            ->json('data');
        $estados = [];

        foreach ($filas as $fila) {
            if (is_array($fila)) {
                $codigo = $fila['codigo'] ?? null;
                $this->assertIsString($codigo);
                $estados[$codigo] = ['estado' => $fila['estado'] ?? null, 'motivo' => $fila['motivo'] ?? null];
            }
        }

        return $estados;
    }

    public function test_the_teacher_sets_moves_and_removes_the_deadline_of_the_group(): void
    {
        $materia = DB::table('asignaturas')->where('id', $this->grupo->asignatura_id)->value('codigo');
        $this->assertIsString($materia);
        [$conFoto] = $this->estudiantesInscritos($this->grupo, 3);
        $this->conFoto($conFoto);

        $this->actingAs($this->cuenta)
            ->getJson("/api/docente/grupos/{$this->grupo->id}/plazo-foto")
            ->assertOk()
            ->assertExactJson(['data' => ['hasta' => null, 'fijado_por' => null, 'inscritos' => 3, 'sin_foto' => 2, 'vencido' => false]]);

        $this->fijar('2026-10-20')
            ->assertOk()
            ->assertJsonPath('data.hasta', '2026-10-20')
            ->assertJsonPath('data.fijado_por', $this->cuenta->nombre);
        // Queda en el plazo del grupo, abierto desde hoy.
        $this->assertDatabaseHas('plazos_foto_grupo', [
            'grupo_id' => $this->grupo->id,
            'desde' => '2026-10-10',
            'hasta' => '2026-10-20',
            'fijado_por' => $this->cuenta->id,
        ]);
        $this->fijar('2026-10-25')->assertOk();
        $this->fijar(null)->assertOk()->assertJsonPath('data.hasta', null);
        $this->assertDatabaseMissing('plazos_foto_grupo', ['grupo_id' => $this->grupo->id]);

        $this->assertSame(
            [
                'Plazo de fotos del grupo '.$materia.' 2: — → 20 oct 2026.',
                'Plazo de fotos del grupo '.$materia.' 2: 20 oct 2026 → 25 oct 2026.',
                'Plazo de fotos del grupo '.$materia.' 2: 25 oct 2026 → —.',
            ],
            DB::table('bitacora_operaciones')->where('operacion', 'grupo.plazo_foto')->orderBy('id')->pluck('descripcion')->all(),
        );
    }

    public function test_the_teacher_moves_a_deadline_set_by_the_administration_keeping_its_start(): void
    {
        DB::table('plazos_foto_grupo')->insert([
            'grupo_id' => $this->grupo->id,
            'desde' => '2026-10-01',
            'hasta' => '2026-10-15',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->cuenta)
            ->getJson("/api/docente/grupos/{$this->grupo->id}/plazo-foto")
            ->assertJsonPath('data.hasta', '2026-10-15');
        $this->fijar('2026-10-30')->assertOk()->assertJsonPath('data.hasta', '2026-10-30');

        $this->assertDatabaseHas('plazos_foto_grupo', [
            'grupo_id' => $this->grupo->id,
            'desde' => '2026-10-01',
            'hasta' => '2026-10-30',
        ]);
    }

    public function test_the_deadline_is_from_today_up_to_a_semester_and_only_for_the_teacher_of_the_group(): void
    {
        $this->fijar('2026-10-09')->assertUnprocessable()->assertJsonPath('errors.hasta.0', 'No puede ser antes de hoy');
        $this->fijar('2027-04-09')->assertUnprocessable()->assertJsonPath('errors.hasta.0', 'Máximo 180 días');
        $this->actingAs($this->cuenta)
            ->putJson("/api/docente/grupos/{$this->grupo->id}/plazo-foto", [])
            ->assertUnprocessable();

        $ajena = $this->usuarioConPermisos(['mis_grupos']);
        $this->docenteConCuenta($ajena);
        $this->fijar('2026-10-20', $ajena)->assertForbidden()->assertJsonPath('alcance', true);
        $this->actingAs($ajena)
            ->getJson("/api/docente/grupos/{$this->grupo->id}/plazo-foto")
            ->assertForbidden();
        $this->actingAs($this->cuenta)->putJson('/api/docente/grupos/999999/plazo-foto', ['hasta' => '2026-10-20'])->assertNotFound();
    }

    public function test_after_the_deadline_who_has_no_photo_is_disabled_until_uploading_it(): void
    {
        [$puntual, $tarde] = $this->estudiantesInscritos($this->grupo, 2);
        $this->fijar('2026-10-11')->assertOk();
        $this->conFoto($puntual);

        // Vencido el plazo, el dia del examen.
        $this->travelTo('2026-10-12 08:20:00');
        $this->flushSession();
        $aula = $this->aula('691A');
        $examen = $this->examen($this->docente, [$this->grupo], [$aula]);
        $this->habilitar($examen, [$puntual, $tarde], $aula);

        $this->assertSame(
            [
                $puntual->codigo_universitario => ['estado' => 'habilitado', 'motivo' => null],
                $tarde->codigo_universitario => ['estado' => 'no', 'motivo' => 'No subió su foto'],
            ],
            array_intersect_key($this->lista($examen->id), [$puntual->codigo_universitario => 0, $tarde->codigo_universitario => 0]),
        );
        // Como autor, quien fijo el plazo.
        $this->assertDatabaseHas('habilitaciones', [
            'examen_id' => $examen->id,
            'estudiante_id' => $tarde->id,
            'por_foto' => true,
            'registrada_por' => $this->cuenta->id,
        ]);

        // Habilitarlo a mano no sirve: lo habilita subir la foto.
        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/habilitaciones", ['habilitado' => true, 'estudiantes' => [$tarde->id]])
            ->assertOk()
            ->assertJsonPath('afectados', 0);

        // La sube con un cambio habilitado: vuelve a «sin revisar».
        $this->conFoto($tarde);
        $this->assertSame(['estado' => 'pendiente', 'motivo' => null], $this->lista($examen->id)[$tarde->codigo_universitario]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'habilitacion.sin_foto',
            'usuario_id' => null,
            'registro_id' => $examen->id,
        ]);

        // Y la bitacora del examen lo muestra.
        $sucesos = array_column((array) $this->actingAs($this->cuenta)
            ->getJson("/api/reportes/examenes/{$examen->id}/bitacora")
            ->json('data'), 'suceso');
        $this->assertContains('No habilitados por no subir su foto', $sucesos);
    }

    public function test_the_door_rejects_who_has_no_photo_after_the_deadline_until_uploading_it(): void
    {
        // La puerta busca el examen abierto con la hora real.
        $this->travelBack();
        [$estudiante] = $this->estudiantesInscritos($this->grupo, 1);
        $aula = $this->aula('691A');
        $examen = $this->examen($this->docente, [$this->grupo], [$aula]);
        $this->habilitar($examen, [$estudiante], $aula);
        DB::table('plazos_foto_grupo')->insert([
            'grupo_id' => $this->grupo->id,
            'desde' => now()->subDays(10)->toDateString(),
            'hasta' => now()->subDay()->toDateString(),
            'fijado_por' => $this->cuenta->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $identificar = fn () => $this->actingAs($this->cuenta)->postJson(
            "/api/aulas/{$aula->id}/punto-control/ingresos",
            ['tipo' => 'codigo', 'identificador' => $estudiante->codigo_universitario],
        );

        // Aunque la lista no se haya vuelto a abrir desde que vencio.
        $identificar()
            ->assertConflict()
            ->assertJsonPath('data.veredicto', 'NO_HABILITADO')
            ->assertJsonPath('data.dato', 'No subió su foto');

        // Con la foto, la puerta deja de rechazarlo por eso.
        $this->conFoto($estudiante);
        $identificar()->assertCreated()->assertJsonPath('data.veredicto', 'ADMITIDO');
    }

    public function test_moving_the_deadline_lifts_the_disabling_and_a_past_exam_is_left_as_it_was(): void
    {
        [$estudiante] = $this->estudiantesInscritos($this->grupo, 1);
        $this->fijar('2026-10-10')->assertOk();
        $aula = $this->aula('691A');

        $this->travelTo('2026-10-11 08:20:00');
        $this->flushSession();
        $ayer = $this->examen($this->docente, [$this->grupo], [$aula]);
        $this->habilitar($ayer, [$estudiante], $aula);

        $this->travelTo('2026-10-12 08:20:00');
        $this->flushSession();
        $hoy = $this->examen($this->docente, [$this->grupo], [$aula]);
        $this->assertSame('no', $this->lista($hoy->id)[$estudiante->codigo_universitario]['estado']);
        $this->assertSame('habilitado', $this->lista($ayer->id)[$estudiante->codigo_universitario]['estado']);

        $this->fijar('2026-10-15')->assertOk();
        $this->assertSame('pendiente', $this->lista($hoy->id)[$estudiante->codigo_universitario]['estado']);
    }

    public function test_a_group_without_deadline_or_another_group_does_not_disable(): void
    {
        $otro = $this->grupo($this->docente, $this->asignatura('Física'), '1');
        [$estudiante] = $this->estudiantesInscritos($this->grupo, 1);
        DB::table('inscripciones')->insert([
            'estudiante_id' => $estudiante->id,
            'grupo_id' => $otro->id,
            'via' => 'DOCENTE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->fijar('2026-10-10')->assertOk();

        $this->travelTo('2026-10-12 08:20:00');
        $this->flushSession();
        $aula = $this->aula('691A');
        $deOtro = $this->examen($this->docente, [$otro], [$aula]);

        $this->assertSame('pendiente', $this->lista($deOtro->id)[$estudiante->codigo_universitario]['estado']);
    }
}
