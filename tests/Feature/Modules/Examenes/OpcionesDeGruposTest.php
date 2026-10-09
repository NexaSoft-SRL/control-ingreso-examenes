<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use App\Modules\Academico\Domain\Models\Periodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Paso 2 del asistente (ruta 48): los grupos de la asignatura, los del
 * docente y los de los demas.
 */
final class OpcionesDeGruposTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    public function test_groups_of_the_subject_are_split_into_own_and_others(): void
    {
        $docente = $this->docente('Blanco Coca Leticia');
        $otro = $this->docente('Taborga Acha Fidel');
        $asignatura = $this->asignatura();
        $dos = $this->grupo($docente, $asignatura, '2');
        $diez = $this->grupo($docente, $asignatura, '10');
        $tres = $this->grupo(null, $asignatura, '3');
        $cinco = $this->grupo($otro, $asignatura, '5');
        $this->grupo($docente, $this->asignatura(), '9');
        $this->estudiantesInscritos($dos, 3);
        $this->estudiantesInscritos($tres, 1);

        $this->actingAs($this->cuenta($docente))
            ->getJson("/api/examenes/opciones/grupos?asignatura_id={$asignatura->id}")
            ->assertOk()
            ->assertExactJson([
                'propios' => [
                    ['id' => $dos->id, 'codigo' => '2', 'docente' => 'Blanco Coca Leticia', 'inscritos' => 3, 'periodo' => $this->periodoVigente()->codigo],
                    ['id' => $diez->id, 'codigo' => '10', 'docente' => 'Blanco Coca Leticia', 'inscritos' => 0, 'periodo' => $this->periodoVigente()->codigo],
                ],
                'otros' => [
                    ['id' => $tres->id, 'codigo' => '3', 'docente' => null, 'inscritos' => 1, 'periodo' => $this->periodoVigente()->codigo],
                    ['id' => $cinco->id, 'codigo' => '5', 'docente' => 'Taborga Acha Fidel', 'inscritos' => 0, 'periodo' => $this->periodoVigente()->codigo],
                ],
            ]);
    }

    public function test_groups_of_closed_periods_are_not_offered(): void
    {
        $docente = $this->docente();
        $asignatura = $this->asignatura();
        $this->periodoVigente();
        $cerrado = Periodo::create([
            'codigo' => '1/2020',
            'anio' => 2020,
            'numero' => 1,
            'tipo' => TipoPeriodo::Semestre1,
            'fecha_inicio' => '2020-02-01',
            'fecha_fin' => '2020-07-01',
        ]);
        $this->grupo($docente, $asignatura, '1', $cerrado);

        $this->actingAs($this->cuenta($docente))
            ->getJson("/api/examenes/opciones/grupos?asignatura_id={$asignatura->id}")
            ->assertOk()
            ->assertExactJson(['propios' => [], 'otros' => []]);
    }

    public function test_the_subject_is_required_and_must_exist(): void
    {
        $cuenta = $this->cuenta($this->docente());

        $this->actingAs($cuenta)
            ->getJson('/api/examenes/opciones/grupos')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asignatura_id' => 'Elige la asignatura.']);

        $this->actingAs($cuenta)
            ->getJson('/api/examenes/opciones/grupos?asignatura_id=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asignatura_id' => 'La asignatura no existe.']);
    }

    public function test_the_options_need_a_session_and_the_permission(): void
    {
        $this->getJson('/api/examenes/opciones/grupos?asignatura_id=1')->assertUnauthorized();

        $this->actingAs($this->usuarioConRol('Auxiliar'))
            ->getJson('/api/examenes/opciones/grupos?asignatura_id=1')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'examenes');
    }
}
