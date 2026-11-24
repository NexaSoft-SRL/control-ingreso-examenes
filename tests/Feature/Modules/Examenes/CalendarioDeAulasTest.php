<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Academico\Domain\Models\Aula;
use App\Modules\Academico\Domain\Models\Grupo;
use App\Modules\Academico\Domain\Models\Horario;
use App\Modules\Examenes\Domain\Enums\TipoExamen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La vista de calendario: lo que ocupa cada aula en una semana, con las
 * clases del horario y los examenes, solo para quien registra examenes.
 */
final class CalendarioDeAulasTest extends TestCase
{
    use ArmaExamenes;
    use RefreshDatabase;

    private function lunes(): string
    {
        return now()->startOfWeek()->toDateString();
    }

    private function domingo(): string
    {
        return now()->startOfWeek()->addDays(6)->toDateString();
    }

    private function clase(Grupo $grupo, Aula $aula, string $dia, string $desde, string $hasta, bool $auxiliatura = false): void
    {
        Horario::create([
            'grupo_id' => $grupo->id,
            'aula_id' => $aula->id,
            'dia' => $dia,
            'hora_inicio' => $desde,
            'hora_fin' => $hasta,
            'es_auxiliatura' => $auxiliatura,
        ]);
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    private function ruta(array $parametros): string
    {
        return '/api/calendario/aulas?'.http_build_query($parametros);
    }

    public function test_the_week_shows_the_classes_and_the_exams_of_the_room(): void
    {
        $docente = $this->docente('Carla Salazar');
        $otro = $this->docente('Juan Pérez');
        $aula = $this->aula('691A');
        $propio = $this->grupo($docente, $this->asignatura('Cálculo I'), 'G1');
        $ajeno = $this->grupo($otro, $this->asignatura('Física I'), 'G2');
        $this->clase($propio, $aula, 'LU', '08:15', '09:45');
        $this->clase($ajeno, $aula, 'MI', '10:00', '11:30', true);
        $mia = $this->examen($docente, [$propio], [$aula], [
            'fecha' => now()->startOfWeek()->addDay()->toDateString(),
            'hora_inicio' => '14:00:00',
            'duracion_minutos' => 90,
        ]);
        $this->examen($otro, [$ajeno], [$aula], [
            'fecha' => now()->startOfWeek()->addDays(3)->toDateString(),
            'hora_inicio' => '16:30:00',
            'duracion_minutos' => 60,
            'tipo' => TipoExamen::Final,
        ]);

        $this->actingAs($this->cuenta($docente))
            ->getJson($this->ruta(['desde' => $this->lunes(), 'hasta' => $this->domingo(), 'aulas' => [$aula->id]]))
            ->assertOk()
            ->assertJsonPath('desde', $this->lunes())
            ->assertJsonPath('aulas.'.$aula->id.'.clases', [
                [
                    'fecha' => $this->lunes(),
                    'inicio' => '08:15',
                    'fin' => '09:45',
                    'asignatura' => 'Cálculo I',
                    'grupo' => 'G1',
                    'docente' => 'Carla Salazar',
                    'auxiliatura' => false,
                ],
                [
                    'fecha' => now()->startOfWeek()->addDays(2)->toDateString(),
                    'inicio' => '10:00',
                    'fin' => '11:30',
                    'asignatura' => 'Física I',
                    'grupo' => 'G2',
                    'docente' => 'Juan Pérez',
                    'auxiliatura' => true,
                ],
            ])
            ->assertJsonPath('aulas.'.$aula->id.'.examenes', [
                [
                    'id' => $mia->id,
                    'fecha' => now()->startOfWeek()->addDay()->toDateString(),
                    'inicio' => '14:00',
                    'fin' => '15:30',
                    'asignatura' => 'Cálculo I',
                    'tipo' => 'Primer parcial',
                    'docente' => 'Carla Salazar',
                    'propio' => true,
                ],
                [
                    // De otro docente: se ve que esta ocupada, sin enlace.
                    'id' => null,
                    'fecha' => now()->startOfWeek()->addDays(3)->toDateString(),
                    'inicio' => '16:30',
                    'fin' => '17:30',
                    'asignatura' => 'Física I',
                    'tipo' => 'Examen final',
                    'docente' => 'Juan Pérez',
                    'propio' => false,
                ],
            ]);
    }

    public function test_a_class_repeats_every_week_of_its_period_and_never_on_sunday(): void
    {
        $docente = $this->docente();
        $aula = $this->aula();
        $grupo = $this->grupo($docente);
        $this->clase($grupo, $aula, 'LU', '08:15', '09:45');
        $this->clase($grupo, $aula, 'SA', '08:15', '09:45');

        $respuesta = $this->actingAs($this->cuenta($docente))
            ->getJson($this->ruta(['desde' => $this->lunes(), 'hasta' => now()->startOfWeek()->addDays(13)->toDateString(), 'aulas' => [$aula->id]]))
            ->assertOk();

        $fechas = array_column((array) $respuesta->json('aulas.'.$aula->id.'.clases'), 'fecha');
        self::assertSame([
            $this->lunes(),
            now()->startOfWeek()->addDays(5)->toDateString(),
            now()->startOfWeek()->addDays(7)->toDateString(),
            now()->startOfWeek()->addDays(12)->toDateString(),
        ], $fechas);
    }

    public function test_the_classes_stop_when_the_period_ends(): void
    {
        $docente = $this->docente();
        $aula = $this->aula();
        $grupo = $this->grupo($docente);
        foreach (['LU', 'MA', 'MI', 'JU', 'VI', 'SA'] as $dia) {
            $this->clase($grupo, $aula, $dia, '08:15', '09:45');
        }
        $fin = (string) $this->periodoVigente()->fecha_fin?->toDateString();
        $desde = now()->addDays(55)->toDateString();
        $hasta = now()->addDays(68)->toDateString();

        $respuesta = $this->actingAs($this->cuenta($docente))
            ->getJson($this->ruta(['desde' => $desde, 'hasta' => $hasta, 'aulas' => [$aula->id]]))
            ->assertOk();

        $fechas = array_column((array) $respuesta->json('aulas.'.$aula->id.'.clases'), 'fecha');
        self::assertNotEmpty($fechas);

        foreach ($fechas as $fecha) {
            self::assertLessThanOrEqual($fin, $fecha);
        }
    }

    public function test_the_rooms_of_a_building_come_together_and_an_empty_room_is_listed(): void
    {
        $docente = $this->docente();
        $edificio = $this->edificio();
        $uno = $this->aula('617', $edificio);
        $dos = $this->aula('618', $edificio);
        $this->aula('900', $this->edificio());
        $this->clase($this->grupo($docente), $uno, 'MA', '08:15', '09:45');

        $this->actingAs($this->cuenta($docente))
            ->getJson($this->ruta(['desde' => $this->lunes(), 'hasta' => $this->domingo(), 'edificio_id' => $edificio->id]))
            ->assertOk()
            ->assertJsonCount(2, 'aulas')
            ->assertJsonCount(1, 'aulas.'.$uno->id.'.clases')
            ->assertJsonPath('aulas.'.$dos->id, ['clases' => [], 'examenes' => []]);
    }

    public function test_the_range_and_the_rooms_are_validated(): void
    {
        $docente = $this->docente();
        $aula = $this->aula();
        $cuenta = $this->cuenta($docente);

        $this->actingAs($cuenta)
            ->getJson($this->ruta(['desde' => $this->lunes(), 'hasta' => $this->domingo()]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['aulas', 'edificio_id']);

        $this->actingAs($cuenta)
            ->getJson($this->ruta(['desde' => $this->domingo(), 'hasta' => $this->lunes(), 'aulas' => [$aula->id]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hasta']);

        $this->actingAs($cuenta)
            ->getJson($this->ruta(['desde' => $this->lunes(), 'hasta' => now()->startOfWeek()->addDays(14)->toDateString(), 'aulas' => [$aula->id]]))
            ->assertStatus(422)
            ->assertJsonPath('errors.hasta.0', 'Como máximo 14 días.');

        $this->actingAs($cuenta)
            ->getJson($this->ruta(['desde' => 'ayer', 'hasta' => 'hoy', 'aulas' => [$aula->id]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['desde', 'hasta']);
    }

    public function test_only_who_registers_exams_can_read_it(): void
    {
        $aula = $this->aula();
        $parametros = ['desde' => $this->lunes(), 'hasta' => $this->domingo(), 'aulas' => [$aula->id]];

        $this->getJson($this->ruta($parametros))->assertUnauthorized();
        $this->actingAs($this->auxiliarConRol())->getJson($this->ruta($parametros))->assertForbidden();
        $this->actingAs($this->usuarioConRol('Administrador'))->getJson($this->ruta($parametros))->assertForbidden();
    }

    public function test_the_view_only_reads(): void
    {
        $docente = $this->docente();
        $aula = $this->aula();

        $this->actingAs($this->cuenta($docente))
            ->postJson('/api/calendario/aulas', ['aulas' => [$aula->id]])
            ->assertStatus(405);
    }
}
