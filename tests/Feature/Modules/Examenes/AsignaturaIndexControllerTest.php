<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Examenes\Domain\Models\Asignatura;
use App\Modules\Examenes\Domain\Models\Docente;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AsignaturaIndexControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_subjects_with_groups_and_responsible_teachers(): void
    {
        $user = UserFactory::new()->createOne();

        $docenteA = Docente::query()->create([
            'codigo_docente' => 'DOC-501',
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'estado' => true,
        ]);

        $docenteB = Docente::query()->create([
            'codigo_docente' => 'DOC-502',
            'nombres' => 'Luis',
            'apellidos' => 'Rojas',
            'estado' => true,
        ]);

        $asignaturaB = Asignatura::query()->create([
            'codigo' => 'INF-400',
            'nombre' => 'Sistemas Distribuidos',
            'semestre' => '8',
            'descripcion' => null,
            'estado' => true,
        ]);

        $asignaturaA = Asignatura::query()->create([
            'codigo' => 'INF-100',
            'nombre' => 'Introducción a la Programación',
            'semestre' => '1',
            'descripcion' => 'Asignatura inicial.',
            'estado' => true,
        ]);

        $asignaturaA->grupos()->create([
            'docente_id' => $docenteB->getKey(),
            'codigo_grupo' => '2',
            'cupo' => 35,
        ]);

        $asignaturaA->grupos()->create([
            'docente_id' => $docenteA->getKey(),
            'codigo_grupo' => '1',
            'cupo' => 30,
        ]);

        $asignaturaB->grupos()->create([
            'docente_id' => $docenteB->getKey(),
            'codigo_grupo' => '1',
            'cupo' => 40,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/asignaturas');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.codigo', 'INF-100')
            ->assertJsonPath(
                'data.0.nombre',
                'Introducción a la Programación'
            )
            ->assertJsonPath('data.0.semestre', '1')
            ->assertJsonPath(
                'data.0.descripcion',
                'Asignatura inicial.'
            )
            ->assertJsonPath('data.0.estado', true)
            ->assertJsonCount(2, 'data.0.grupos')
            ->assertJsonPath(
                'data.0.grupos.0.codigo_grupo',
                '1'
            )
            ->assertJsonPath(
                'data.0.grupos.0.cupo',
                30
            )
            ->assertJsonPath(
                'data.0.grupos.0.docente.codigo_docente',
                'DOC-501'
            )
            ->assertJsonPath(
                'data.0.grupos.0.docente.nombres',
                'Ana'
            )
            ->assertJsonPath(
                'data.0.grupos.0.docente.apellidos',
                'Pérez'
            )
            ->assertJsonPath(
                'data.0.grupos.1.codigo_grupo',
                '2'
            )
            ->assertJsonPath(
                'data.0.grupos.1.docente.codigo_docente',
                'DOC-502'
            )
            ->assertJsonPath('data.1.codigo', 'INF-400')
            ->assertJsonCount(1, 'data.1.grupos');
    }

    public function test_guest_cannot_list_subjects(): void
    {
        $this->getJson('/api/asignaturas')
            ->assertUnauthorized();
    }
}
