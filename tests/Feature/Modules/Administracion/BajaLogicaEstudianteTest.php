<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\Student;
use Database\Factories\StudentFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HU-03 pide dar de baja al estudiante "sin borrar su historial": la baja
 * tiene que dejarlo inactivo, no eliminarlo de la base.
 */
final class BajaLogicaEstudianteTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_deregistration_keeps_the_student_in_the_roll(): void
    {
        $usuario = UserFactory::new()->createOne();
        $estudiante = StudentFactory::new()->create(['activo' => true]);

        $id = $estudiante->getKey();

        self::assertIsInt($id);

        $this->actingAs($usuario)
            ->deleteJson("/api/students/{$id}")
            ->assertOk()
            ->assertJsonPath('activo', false);

        $this->assertDatabaseHas('students', [
            'id' => $id,
            'activo' => false,
        ]);
    }

    public function test_the_deregistered_student_still_appears_in_the_list(): void
    {
        $usuario = UserFactory::new()->createOne();
        $estudiante = StudentFactory::new()->create(['activo' => true]);

        $id = $estudiante->getKey();

        self::assertIsInt($id);

        $this->actingAs($usuario)->deleteJson("/api/students/{$id}")->assertOk();

        $listado = $this->actingAs($usuario)->getJson('/api/students')->assertOk();

        $ids = array_column((array) $listado->json(), 'id');

        $this->assertContains($id, $ids);
    }

    public function test_a_deregistered_student_can_be_reinstated(): void
    {
        $usuario = UserFactory::new()->createOne();
        $estudiante = StudentFactory::new()->create(['activo' => false]);

        $id = $estudiante->getKey();

        self::assertIsInt($id);

        $this->actingAs($usuario)
            ->putJson("/api/students/{$id}", [
                'nombre' => $estudiante->nombre,
                'apellido' => $estudiante->apellido,
                'ci' => $estudiante->ci,
                'activo' => true,
            ])
            ->assertOk()
            ->assertJsonPath('activo', true);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'estudiante.reactivar',
            'tabla_afectada' => 'students',
            'registro_id' => $id,
        ]);
    }

    public function test_the_deregistration_is_recorded_in_the_log(): void
    {
        $usuario = UserFactory::new()->createOne();
        $estudiante = StudentFactory::new()->create(['activo' => true]);

        $id = $estudiante->getKey();
        $usuarioId = $usuario->getKey();

        self::assertIsInt($id);
        self::assertIsInt($usuarioId);

        $this->actingAs($usuario)->deleteJson("/api/students/{$id}")->assertOk();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $usuarioId,
            'operacion' => 'estudiante.baja',
            'tabla_afectada' => 'students',
            'registro_id' => $id,
        ]);
    }

    public function test_a_guest_cannot_deregister_a_student(): void
    {
        $estudiante = StudentFactory::new()->create(['activo' => true]);

        $id = $estudiante->getKey();

        self::assertIsInt($id);

        $this->deleteJson("/api/students/{$id}")->assertUnauthorized();

        $this->assertDatabaseHas('students', [
            'id' => $id,
            'activo' => true,
        ]);
    }

    public function test_deregistering_a_student_that_does_not_exist_is_reported(): void
    {
        $usuario = UserFactory::new()->createOne();

        $this->actingAs($usuario)
            ->deleteJson('/api/students/999999')
            ->assertNotFound();

        $this->assertSame(0, Student::query()->count());
    }
}
