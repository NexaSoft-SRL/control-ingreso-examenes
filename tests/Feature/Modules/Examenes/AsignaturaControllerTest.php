<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Examenes\Domain\Models\Asignatura;
use App\Modules\Examenes\Domain\Models\Docente;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AsignaturaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_register_subject_with_groups_and_responsible_teachers(): void
    {
        $user = UserFactory::new()->createOne();

        $docenteA = Docente::query()->create([
            'codigo_docente' => 'DOC-401',
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'estado' => true,
        ]);

        $docenteB = Docente::query()->create([
            'codigo_docente' => 'DOC-402',
            'nombres' => 'Luis',
            'apellidos' => 'Rojas',
            'estado' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/asignaturas', [
                'codigo' => 'INF-321',
                'nombre' => 'Redes de Computadoras',
                'semestre' => '6',
                'descripcion' => 'Materia de redes.',
                'grupos' => [
                    [
                        'codigo_grupo' => '1',
                        'docente_id' => $docenteA->getKey(),
                        'cupo' => 30,
                    ],
                    [
                        'codigo_grupo' => '2',
                        'docente_id' => $docenteB->getKey(),
                        'cupo' => 35,
                    ],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.codigo', 'INF-321')
            ->assertJsonPath('data.nombre', 'Redes de Computadoras')
            ->assertJsonPath('data.semestre', '6')
            ->assertJsonPath('data.descripcion', 'Materia de redes.')
            ->assertJsonPath('data.estado', true)
            ->assertJsonCount(2, 'data.grupos')
            ->assertJsonPath('data.grupos.0.codigo_grupo', '1')
            ->assertJsonPath('data.grupos.0.cupo', 30)
            ->assertJsonPath(
                'data.grupos.0.docente.id',
                $docenteA->getKey()
            )
            ->assertJsonPath(
                'data.grupos.0.docente.codigo_docente',
                'DOC-401'
            )
            ->assertJsonPath('data.grupos.1.codigo_grupo', '2')
            ->assertJsonPath('data.grupos.1.cupo', 35)
            ->assertJsonPath(
                'data.grupos.1.docente.id',
                $docenteB->getKey()
            )
            ->assertJsonPath(
                'data.grupos.1.docente.codigo_docente',
                'DOC-402'
            );

        $this->assertDatabaseHas('asignaturas', [
            'codigo' => 'INF-321',
            'nombre' => 'Redes de Computadoras',
            'semestre' => '6',
        ]);

        $this->assertDatabaseHas('grupos_asignatura', [
            'docente_id' => $docenteA->getKey(),
            'codigo_grupo' => '1',
            'cupo' => 30,
        ]);

        $this->assertDatabaseHas('grupos_asignatura', [
            'docente_id' => $docenteB->getKey(),
            'codigo_grupo' => '2',
            'cupo' => 35,
        ]);

        $this->assertDatabaseCount('asignaturas', 1);
        $this->assertDatabaseCount('grupos_asignatura', 2);
    }

    public function test_guest_cannot_register_subject(): void
    {
        $this->postJson('/api/asignaturas', [
            'codigo' => 'INF-321',
            'nombre' => 'Redes de Computadoras',
            'grupos' => [],
        ])->assertUnauthorized();

        $this->assertDatabaseCount('asignaturas', 0);
    }

    public function test_subject_code_must_be_unique(): void
    {
        $user = UserFactory::new()->createOne();

        $docente = Docente::query()->create([
            'codigo_docente' => 'DOC-UNIQUE-001',
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'estado' => true,
        ]);

        Asignatura::query()->create([
            'codigo' => 'INF-321',
            'nombre' => 'Redes de Computadoras',
            'semestre' => '6',
            'descripcion' => null,
            'estado' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/asignaturas', [
                'codigo' => 'INF-321',
                'nombre' => 'Otra asignatura',
                'semestre' => '7',
                'descripcion' => null,
                'grupos' => [
                    [
                        'codigo_grupo' => '1',
                        'docente_id' => $docente->getKey(),
                        'cupo' => 30,
                    ],
                ],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'codigo',
            ]);

        $this->assertDatabaseCount('asignaturas', 1);
        $this->assertDatabaseCount('grupos_asignatura', 0);
    }

    public function test_subject_requires_at_least_one_group(): void
    {
        $user = UserFactory::new()->createOne();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/asignaturas', [
                'codigo' => 'INF-322',
                'nombre' => 'Sistemas Distribuidos',
                'semestre' => '7',
                'descripcion' => null,
                'grupos' => [],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'grupos',
            ]);

        $this->assertDatabaseCount('asignaturas', 0);
        $this->assertDatabaseCount('grupos_asignatura', 0);
    }

    public function test_group_codes_must_be_distinct_within_subject_registration(): void
    {
        $user = UserFactory::new()->createOne();

        $docenteA = Docente::query()->create([
            'codigo_docente' => 'DOC-DISTINCT-001',
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'estado' => true,
        ]);

        $docenteB = Docente::query()->create([
            'codigo_docente' => 'DOC-DISTINCT-002',
            'nombres' => 'Luis',
            'apellidos' => 'Rojas',
            'estado' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/asignaturas', [
                'codigo' => 'INF-323',
                'nombre' => 'Arquitectura de Software',
                'semestre' => '7',
                'descripcion' => null,
                'grupos' => [
                    [
                        'codigo_grupo' => '1',
                        'docente_id' => $docenteA->getKey(),
                        'cupo' => 30,
                    ],
                    [
                        'codigo_grupo' => '1',
                        'docente_id' => $docenteB->getKey(),
                        'cupo' => 35,
                    ],
                ],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'grupos.0.codigo_grupo',
            ]);

        $this->assertDatabaseCount('asignaturas', 0);
        $this->assertDatabaseCount('grupos_asignatura', 0);
    }

    public function test_group_cannot_reference_nonexistent_teacher(): void
    {
        $user = UserFactory::new()->createOne();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/asignaturas', [
                'codigo' => 'INF-324',
                'nombre' => 'Inteligencia Artificial',
                'semestre' => '8',
                'descripcion' => null,
                'grupos' => [
                    [
                        'codigo_grupo' => '1',
                        'docente_id' => 999999999,
                        'cupo' => 30,
                    ],
                ],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'grupos.0.docente_id',
            ]);

        $this->assertDatabaseCount('asignaturas', 0);
        $this->assertDatabaseCount('grupos_asignatura', 0);
    }

    public function test_group_cannot_reference_inactive_teacher(): void
    {
        $user = UserFactory::new()->createOne();

        $docente = Docente::query()->create([
            'codigo_docente' => 'DOC-INACTIVE-001',
            'nombres' => 'Carlos',
            'apellidos' => 'Mendoza',
            'estado' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/asignaturas', [
                'codigo' => 'INF-325',
                'nombre' => 'Compiladores',
                'semestre' => '8',
                'descripcion' => null,
                'grupos' => [
                    [
                        'codigo_grupo' => '1',
                        'docente_id' => $docente->getKey(),
                        'cupo' => 30,
                    ],
                ],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'grupos.0.docente_id',
            ]);

        $this->assertDatabaseCount('asignaturas', 0);
        $this->assertDatabaseCount('grupos_asignatura', 0);

        $this->assertDatabaseHas('docentes', [
            'codigo_docente' => 'DOC-INACTIVE-001',
            'estado' => false,
        ]);
    }

    public function test_group_capacity_cannot_be_negative(): void
    {
        $user = UserFactory::new()->createOne();

        $docente = Docente::query()->create([
            'codigo_docente' => 'DOC-CAPACITY-001',
            'nombres' => 'María',
            'apellidos' => 'Flores',
            'estado' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/asignaturas', [
                'codigo' => 'INF-326',
                'nombre' => 'Seguridad Informática',
                'semestre' => '8',
                'descripcion' => null,
                'grupos' => [
                    [
                        'codigo_grupo' => '1',
                        'docente_id' => $docente->getKey(),
                        'cupo' => -1,
                    ],
                ],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'grupos.0.cupo',
            ]);

        $this->assertDatabaseCount('asignaturas', 0);
        $this->assertDatabaseCount('grupos_asignatura', 0);
    }
}
