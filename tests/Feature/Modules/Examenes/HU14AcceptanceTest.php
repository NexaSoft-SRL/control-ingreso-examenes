<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Administracion\Domain\Models\Ambiente;
use App\Modules\Examenes\Domain\Models\Asignatura;
use App\Modules\Examenes\Domain\Models\Docente;
use Database\Factories\StudentFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class HU14AcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_at_least_one_student(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();

        $this->actingAs($usuario)
            ->postJson("/api/examenes/{$examen}/asignaciones", [
                'ambiente_id' => 1,
                'estudiante_ids' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estudiante_ids');
    }

    public function test_requires_ambiente(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();
        $estudiante = StudentFactory::new()->create(['activo' => true]);

        DB::table('habilitaciones_examen')->insert([
            'examen_id' => $examen,
            'estudiante_id' => $estudiante->getKey(),
            'estado' => 'HABILITADO',
            'usuario_id' => $usuario->getKey(),
        ]);

        $this->actingAs($usuario)
            ->postJson("/api/examenes/{$examen}/asignaciones", [
                'estudiante_ids' => [$estudiante->getKey()],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ambiente_id');
    }

    public function test_rejects_non_habilitated_student(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();
        $ambiente = Ambiente::query()->create([
            'nombre' => 'Sala X',
            'ubicacion' => 'Edificio',
            'capacidad' => 10,
            'estado' => 'DISPONIBLE',
        ]);
        $estudiante = StudentFactory::new()->create(['activo' => true]);

        DB::table('habilitaciones_examen')->insert([
            'examen_id' => $examen,
            'estudiante_id' => $estudiante->getKey(),
            'estado' => 'NO_HABILITADO',
            'usuario_id' => $usuario->getKey(),
        ]);

        $this->actingAs($usuario)
            ->postJson("/api/examenes/{$examen}/asignaciones", [
                'ambiente_id' => $ambiente->getKey(),
                'estudiante_ids' => [$estudiante->getKey()],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Solo pueden asignarse estudiantes habilitados y activos.');
    }

    public function test_assign_persists_and_detail_shows_student(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();

        $amb1 = Ambiente::query()->create([
            'nombre' => 'A-1',
            'ubicacion' => 'E1',
            'capacidad' => 30,
            'estado' => 'DISPONIBLE',
        ]);

        $amb2 = Ambiente::query()->create([
            'nombre' => 'A-2',
            'ubicacion' => 'E2',
            'capacidad' => 30,
            'estado' => 'DISPONIBLE',
        ]);

        $estudiante = StudentFactory::new()->create(['activo' => true]);

        DB::table('habilitaciones_examen')->insert([
            'examen_id' => $examen,
            'estudiante_id' => $estudiante->getKey(),
            'estado' => 'HABILITADO',
            'usuario_id' => $usuario->getKey(),
        ]);

        $this->actingAs($usuario)
            ->postJson("/api/examenes/{$examen}/asignaciones", [
                'ambiente_id' => $amb2->getKey(),
                'estudiante_ids' => [$estudiante->getKey()],
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $amb2->getKey());

        $this->assertDatabaseHas('asignaciones_ambiente', [
            'examen_id' => $examen,
            'ambiente_id' => $amb2->getKey(),
            'estudiante_id' => $estudiante->getKey(),
        ]);

        $resp = $this->actingAs($usuario)->getJson("/api/examenes/{$examen}/asignaciones");
        $resp->assertOk();
        $data = $resp->json('data');
        $this->assertIsArray($data);

        // The ambiente detail should include the assigned student.
        $ambientes = $data['ambientes'] ?? null;
        $this->assertIsArray($ambientes);

        $ambienteEncontrado = null;
        foreach ($ambientes as $ambiente) {
            if (is_array($ambiente) && ($ambiente['id'] ?? null) === $amb2->getKey()) {
                $ambienteEncontrado = $ambiente;
                break;
            }
        }

        $this->assertIsArray($ambienteEncontrado, 'Assigned ambiente not present in list');
        $estudiantesAsignados = $ambienteEncontrado['estudiantes'] ?? null;
        $this->assertIsArray($estudiantesAsignados);
        $this->assertCount(1, $estudiantesAsignados);

        $primerEstudiante = $estudiantesAsignados[0] ?? null;
        $this->assertIsArray($primerEstudiante);
        $this->assertSame($estudiante->getKey(), $primerEstudiante['id'] ?? null);
    }

    private function crearExamen(): int
    {
        $docente = Docente::query()->create([
            'codigo_docente' => 'DOC-HU14',
            'nombres' => 'Docente',
            'apellidos' => 'HU14',
            'estado' => true,
        ]);
        $asignatura = Asignatura::query()->create([
            'codigo' => 'HU14-001',
            'nombre' => 'Asignatura HU14',
            'semestre' => '1',
            'estado' => true,
        ]);
        $grupo = $asignatura->grupos()->create([
            'docente_id' => $docente->getKey(),
            'codigo_grupo' => 'A',
            'cupo' => 30,
        ]);

        return (int) DB::table('examenes')->insertGetId([
            'grupo_id' => $grupo->getKey(),
            'nombre' => 'Parcial HU14',
            'fecha' => '2026-10-20',
            'hora_inicio' => '09:00:00',
            'duracion_minutos' => 90,
        ]);
    }
}
