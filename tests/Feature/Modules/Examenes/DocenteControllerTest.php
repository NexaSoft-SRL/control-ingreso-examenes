<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Examenes\Domain\Models\Docente;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DocenteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_only_active_teachers_in_name_order(): void
    {
        $user = UserFactory::new()->createOne();

        Docente::query()->create([
            'codigo_docente' => 'DOC-602',
            'nombres' => 'Luis',
            'apellidos' => 'Rojas',
            'estado' => true,
        ]);

        Docente::query()->create([
            'codigo_docente' => 'DOC-603',
            'nombres' => 'Carlos',
            'apellidos' => 'Mendoza',
            'estado' => false,
        ]);

        Docente::query()->create([
            'codigo_docente' => 'DOC-601',
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
            'estado' => true,
        ]);

        Docente::query()->create([
            'codigo_docente' => 'DOC-600',
            'nombres' => 'Beatriz',
            'apellidos' => 'Alvarez',
            'estado' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/docentes');

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath(
                'data.0.codigo_docente',
                'DOC-600'
            )
            ->assertJsonPath(
                'data.0.nombres',
                'Beatriz'
            )
            ->assertJsonPath(
                'data.0.apellidos',
                'Alvarez'
            )
            ->assertJsonPath(
                'data.1.codigo_docente',
                'DOC-601'
            )
            ->assertJsonPath(
                'data.1.nombres',
                'Ana'
            )
            ->assertJsonPath(
                'data.1.apellidos',
                'Perez'
            )
            ->assertJsonPath(
                'data.2.codigo_docente',
                'DOC-602'
            )
            ->assertJsonPath(
                'data.2.nombres',
                'Luis'
            )
            ->assertJsonPath(
                'data.2.apellidos',
                'Rojas'
            )
            ->assertJsonMissing([
                'codigo_docente' => 'DOC-603',
            ]);
    }

    public function test_guest_cannot_list_teachers(): void
    {
        $this->getJson('/api/docentes')
            ->assertUnauthorized();
    }
}
