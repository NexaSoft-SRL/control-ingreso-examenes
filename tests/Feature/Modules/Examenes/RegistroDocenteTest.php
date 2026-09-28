<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Examenes;

use App\Modules\Examenes\Domain\Models\Docente;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alta de docentes desde la administracion de usuarios y roles (HU-02): sin
 * ella HU-05 no tiene a quien poner como responsable de un grupo.
 */
final class RegistroDocenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_register_teachers(): void
    {
        $this->postJson('/api/docentes', [
            'codigo_docente' => 'DOC-900',
            'nombres' => 'Intruso',
            'apellidos' => 'Sin sesión',
        ])->assertUnauthorized();
    }

    public function test_the_administrator_registers_a_teacher_linked_to_an_account(): void
    {
        $administrador = UserFactory::new()->createOne();
        $cuenta = UserFactory::new()->createOne();

        $response = $this
            ->actingAs($administrador)
            ->postJson('/api/docentes', [
                'codigo_docente' => 'DOC-900',
                'nombres' => 'Marcela',
                'apellidos' => 'Quiroga',
                'correo' => 'marcela.quiroga@umss.edu.bo',
                'telefono' => '70011223',
                'user_id' => $cuenta->getKey(),
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.codigo_docente', 'DOC-900')
            ->assertJsonPath('data.nombres', 'Marcela')
            ->assertJsonPath('data.apellidos', 'Quiroga');

        $this->assertDatabaseHas('docentes', [
            'codigo_docente' => 'DOC-900',
            'user_id' => $cuenta->getKey(),
            'estado' => true,
        ]);
    }

    public function test_the_new_teacher_appears_in_the_list_used_by_the_subjects_screen(): void
    {
        $administrador = UserFactory::new()->createOne();

        $this->actingAs($administrador)->postJson('/api/docentes', [
            'codigo_docente' => 'DOC-901',
            'nombres' => 'Iván',
            'apellidos' => 'Camacho',
        ])->assertCreated();

        $this->actingAs($administrador)
            ->getJson('/api/docentes')
            ->assertOk()
            ->assertJsonPath('data.0.codigo_docente', 'DOC-901');
    }

    public function test_the_registration_leaves_a_trace_in_the_log(): void
    {
        $administrador = UserFactory::new()->createOne();

        $this->actingAs($administrador)->postJson('/api/docentes', [
            'codigo_docente' => 'DOC-902',
            'nombres' => 'Rosa',
            'apellidos' => 'Vargas',
        ])->assertCreated();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'docente.registrar',
            'tabla_afectada' => 'docentes',
            'usuario_id' => $administrador->getKey(),
        ]);
    }

    public function test_the_teacher_code_cannot_be_repeated(): void
    {
        $administrador = UserFactory::new()->createOne();

        Docente::query()->create([
            'codigo_docente' => 'DOC-903',
            'nombres' => 'Pedro',
            'apellidos' => 'Soliz',
            'estado' => true,
        ]);

        $this->actingAs($administrador)->postJson('/api/docentes', [
            'codigo_docente' => 'DOC-903',
            'nombres' => 'Otro',
            'apellidos' => 'Docente',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('codigo_docente');
    }

    public function test_an_account_cannot_be_linked_to_two_teachers(): void
    {
        $administrador = UserFactory::new()->createOne();
        $cuenta = UserFactory::new()->createOne();

        Docente::query()->create([
            'user_id' => $cuenta->getKey(),
            'codigo_docente' => 'DOC-904',
            'nombres' => 'Delia',
            'apellidos' => 'Mamani',
            'estado' => true,
        ]);

        $this->actingAs($administrador)->postJson('/api/docentes', [
            'codigo_docente' => 'DOC-905',
            'nombres' => 'Otra',
            'apellidos' => 'Cuenta',
            'user_id' => $cuenta->getKey(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    public function test_the_teacher_needs_a_code_and_a_name(): void
    {
        $administrador = UserFactory::new()->createOne();

        $this->actingAs($administrador)->postJson('/api/docentes', [
            'codigo_docente' => '',
            'nombres' => '',
            'apellidos' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo_docente', 'nombres', 'apellidos']);
    }
}
