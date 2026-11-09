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
 * El plazo de fotos lo fija solo la administracion. Vencido, quien no subio
 * su foto queda inhabilitado en los examenes de hoy en adelante: en la
 * lista de habilitacion y en la puerta. Al subirla vuelve a «sin revisar».
 * El docente ya no fija un plazo para su grupo.
 */
final class PlazoFotoGeneralTest extends TestCase
{
    use DatosAcademicos;
    use DatosDeExamen;
    use RefreshDatabase;
    use UsuarioConPermisos;

    private User $administrador;

    private User $cuenta;

    private Docente $docente;

    private Grupo $grupo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-10 10:00:00');

        $this->administrador = $this->usuarioConPermisos(['padron_estudiantes']);
        $this->administrador->forceFill(['name' => 'Rojas Vargas Ana'])->save();
        $this->cuenta = $this->usuarioConPermisos(['mis_grupos', 'habilitacion', 'punto_control']);
        $this->docente = $this->docenteConCuenta($this->cuenta, 'Blanco Coca Leticia');
        $this->grupo = $this->grupo($this->docente, $this->asignatura('Introducción a la Programación'), '2');
    }

    /**
     * @return TestResponse<Response>
     */
    private function fijarPlazo(string $desde, string $hasta): TestResponse
    {
        return $this->actingAs($this->administrador)
            ->putJson('/api/estudiantes/plazo-fotos', ['desde' => $desde, 'hasta' => $hasta]);
    }

    private function conFoto(Estudiante $estudiante): void
    {
        DB::table('estudiantes')->where('id', $estudiante->id)->update(['foto' => 'estudiantes/foto.jpg', 'foto_subida_en' => now()]);
    }

    /**
     * @return array<string, array{estado: mixed, motivo: mixed, registrada_por: mixed}>
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
                $estados[$codigo] = [
                    'estado' => $fila['estado'] ?? null,
                    'motivo' => $fila['motivo'] ?? null,
                    'registrada_por' => $fila['registrada_por'] ?? null,
                ];
            }
        }

        return $estados;
    }

    public function test_the_teacher_no_longer_sets_a_deadline_for_the_group(): void
    {
        $this->actingAs($this->cuenta)
            ->getJson("/api/docente/grupos/{$this->grupo->id}/plazo-foto")
            ->assertNotFound();
        $this->actingAs($this->cuenta)
            ->putJson("/api/docente/grupos/{$this->grupo->id}/plazo-foto", ['hasta' => '2026-10-20'])
            ->assertNotFound();
    }

    public function test_after_the_general_deadline_who_has_no_photo_is_disabled_until_uploading_it(): void
    {
        [$puntual, $tarde] = $this->estudiantesInscritos($this->grupo, 2);
        $this->fijarPlazo('2026-10-01', '2026-10-11')->assertOk();
        $this->conFoto($puntual);

        // Vencido el plazo, el dia del examen.
        $this->travelTo('2026-10-12 08:20:00');
        $aula = $this->aula('691A');
        $examen = $this->examen($this->docente, [$this->grupo], [$aula]);
        $this->habilitar($examen, [$puntual, $tarde], $aula);

        $this->assertSame(
            [
                $puntual->codigo_universitario => ['estado' => 'habilitado', 'motivo' => null, 'registrada_por' => null],
                $tarde->codigo_universitario => ['estado' => 'no', 'motivo' => 'No subió su foto', 'registrada_por' => 'Rojas Vargas Ana'],
            ],
            array_intersect_key($this->lista($examen->id), [$puntual->codigo_universitario => 0, $tarde->codigo_universitario => 0]),
        );

        // Habilitarlo a mano no sirve: lo habilita subir la foto.
        $this->actingAs($this->cuenta)
            ->postJson("/api/examenes/{$examen->id}/habilitaciones", ['habilitado' => true, 'estudiantes' => [$tarde->id]])
            ->assertOk()
            ->assertJsonPath('afectados', 0);

        $this->ponerACargo($examen, $aula, $this->cuenta);
        $this->actingAs($this->cuenta)
            ->postJson("/api/puerta/aulas/{$aula->id}/verificaciones", ['identificador' => $tarde->codigo_universitario])
            ->assertOk()
            ->assertJsonPath('veredicto', 'no_habilitado')
            ->assertJsonPath('dato', 'No subió su foto');

        // La sube con un cambio habilitado: vuelve a «sin revisar».
        $this->conFoto($tarde);
        $this->assertSame('pendiente', $this->lista($examen->id)[$tarde->codigo_universitario]['estado']);
        $this->assertDatabaseHas('bitacora_operaciones', ['operacion' => 'habilitacion.sin_foto', 'registro_id' => $examen->id]);
    }

    public function test_the_open_deadline_does_not_disable_and_moving_it_lifts_the_disabling(): void
    {
        [$estudiante] = $this->estudiantesInscritos($this->grupo, 1);
        $aula = $this->aula('691A');
        $this->fijarPlazo('2026-10-01', '2026-10-10')->assertOk();

        // Abierto todavia: nadie queda inhabilitado.
        $abierto = $this->examen($this->docente, [$this->grupo], [$aula]);
        $this->assertSame('pendiente', $this->lista($abierto->id)[$estudiante->codigo_universitario]['estado']);

        $this->travelTo('2026-10-11 08:20:00');
        $ayer = $this->examen($this->docente, [$this->grupo], [$aula]);
        $this->habilitar($ayer, [$estudiante], $aula);

        $this->travelTo('2026-10-12 08:20:00');
        $hoy = $this->examen($this->docente, [$this->grupo], [$aula]);
        $this->assertSame('no', $this->lista($hoy->id)[$estudiante->codigo_universitario]['estado']);
        // Un examen pasado queda como estaba.
        $this->assertSame('habilitado', $this->lista($ayer->id)[$estudiante->codigo_universitario]['estado']);

        // La administracion amplia el plazo: vuelve a «sin revisar».
        $this->fijarPlazo('2026-10-01', '2026-10-20')->assertOk();
        $this->assertSame('pendiente', $this->lista($hoy->id)[$estudiante->codigo_universitario]['estado']);
    }

    public function test_without_a_general_deadline_nobody_is_disabled(): void
    {
        [$estudiante] = $this->estudiantesInscritos($this->grupo, 1);

        $this->travelTo('2026-10-12 08:20:00');
        $aula = $this->aula('691A');
        $examen = $this->examen($this->docente, [$this->grupo], [$aula]);
        $this->habilitar($examen, [$estudiante], $aula);

        $this->assertSame('habilitado', $this->lista($examen->id)[$estudiante->codigo_universitario]['estado']);
        $this->ponerACargo($examen, $aula, $this->cuenta);
        $this->actingAs($this->cuenta)
            ->postJson("/api/puerta/aulas/{$aula->id}/verificaciones", ['identificador' => $estudiante->codigo_universitario])
            ->assertOk()
            ->assertJsonPath('veredicto', 'admitido');
    }
}
