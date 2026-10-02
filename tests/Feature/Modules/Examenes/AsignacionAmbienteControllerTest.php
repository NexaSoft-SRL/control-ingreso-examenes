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

final class AsignacionAmbienteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_habilitated_students_and_validates_room_selection(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();
        $ambiente = Ambiente::query()->create([
            'nombre' => 'A101',
            'ubicacion' => 'Edificio A',
            'capacidad' => 30,
            'estado' => 'DISPONIBLE',
        ]);

        $habilitado = StudentFactory::new()->create(['activo' => true]);
        $inhabilitado = StudentFactory::new()->create(['activo' => true]);

        DB::table('habilitaciones_examen')->insert([
            [
                'examen_id' => $examen,
                'estudiante_id' => $habilitado->getKey(),
                'estado' => 'HABILITADO',
                'usuario_id' => $usuario->getKey(),
            ],
            [
                'examen_id' => $examen,
                'estudiante_id' => $inhabilitado->getKey(),
                'estado' => 'NO_HABILITADO',
                'usuario_id' => $usuario->getKey(),
            ],
        ]);

        $this->actingAs($usuario)
            ->getJson("/api/examenes/{$examen}/asignaciones")
            ->assertOk()
            ->assertJsonPath('data.ambientes.0.id', $ambiente->getKey())
            ->assertJsonPath('data.candidatos.0.id', $habilitado->getKey())
            ->assertJsonCount(1, 'data.candidatos');

        $this->actingAs($usuario)
            ->postJson("/api/examenes/{$examen}/asignaciones", [
                'ambiente_id' => 999,
                'estudiante_ids' => [$habilitado->getKey()],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ambiente_id');
    }

    public function test_it_assigns_students_to_a_room_and_rejects_duplicates_and_capacity_overflow(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();
        $ambiente = Ambiente::query()->create([
            'nombre' => 'A102',
            'ubicacion' => 'Edificio B',
            'capacidad' => 1,
            'estado' => 'DISPONIBLE',
        ]);

        $primero = StudentFactory::new()->create(['activo' => true]);
        $segundo = StudentFactory::new()->create(['activo' => true]);

        foreach ([$primero, $segundo] as $estudiante) {
            DB::table('habilitaciones_examen')->insert([
                'examen_id' => $examen,
                'estudiante_id' => $estudiante->getKey(),
                'estado' => 'HABILITADO',
                'usuario_id' => $usuario->getKey(),
            ]);
        }

        $this->actingAs($usuario)->postJson("/api/examenes/{$examen}/asignaciones", [
            'ambiente_id' => $ambiente->getKey(),
            'estudiante_ids' => [$primero->getKey()],
        ])->assertOk();

        $this->assertDatabaseHas('asignaciones_ambiente', [
            'examen_id' => $examen,
            'ambiente_id' => $ambiente->getKey(),
            'estudiante_id' => $primero->getKey(),
        ]);

        $this->actingAs($usuario)
            ->postJson("/api/examenes/{$examen}/asignaciones", [
                'ambiente_id' => $ambiente->getKey(),
                'estudiante_ids' => [$primero->getKey()],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Uno o más estudiantes ya están asignados a este examen.');

        $this->actingAs($usuario)
            ->postJson("/api/examenes/{$examen}/asignaciones", [
                'ambiente_id' => $ambiente->getKey(),
                'estudiante_ids' => [$segundo->getKey()],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El ambiente no tiene cupo suficiente para la cantidad solicitada.');
    }

    public function test_it_removes_one_student_assignment_from_a_room(): void
    {
        $usuario = UserFactory::new()->createOne();
        $examen = $this->crearExamen();
        $ambiente = Ambiente::query()->create([
            'nombre' => 'A103',
            'ubicacion' => 'Edificio C',
            'capacidad' => 10,
            'estado' => 'DISPONIBLE',
        ]);

        $estudiante = StudentFactory::new()->create(['activo' => true]);

        DB::table('habilitaciones_examen')->insert([
            'examen_id' => $examen,
            'estudiante_id' => $estudiante->getKey(),
            'estado' => 'HABILITADO',
            'usuario_id' => $usuario->getKey(),
        ]);

        DB::table('asignaciones_ambiente')->insert([
            'examen_id' => $examen,
            'ambiente_id' => $ambiente->getKey(),
            'estudiante_id' => $estudiante->getKey(),
            'usuario_id' => $usuario->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($usuario)
            ->deleteJson("/api/examenes/{$examen}/asignaciones", [
                'ambiente_id' => $ambiente->getKey(),
                'estudiante_id' => $estudiante->getKey(),
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Estudiante quitado del ambiente correctamente.');

        $this->assertDatabaseMissing('asignaciones_ambiente', [
            'examen_id' => $examen,
            'ambiente_id' => $ambiente->getKey(),
            'estudiante_id' => $estudiante->getKey(),
        ]);
    }

    private function crearExamen(): int
    {
        $docente = Docente::query()->create([
            'codigo_docente' => 'DOC-AMB-001',
            'nombres' => 'Docente',
            'apellidos' => 'Ambientes',
            'estado' => true,
        ]);
        $asignatura = Asignatura::query()->create([
            'codigo' => 'INF-AMB-001',
            'nombre' => 'Asignatura ambientes',
            'semestre' => '8',
            'estado' => true,
        ]);
        $grupo = $asignatura->grupos()->create([
            'docente_id' => $docente->getKey(),
            'codigo_grupo' => 'A',
            'cupo' => 30,
        ]);

        return (int) DB::table('examenes')->insertGetId([
            'grupo_id' => $grupo->getKey(),
            'nombre' => 'Primer parcial',
            'fecha' => '2026-10-15',
            'hora_inicio' => '08:00:00',
            'duracion_minutos' => 90,
        ]);
    }
}
