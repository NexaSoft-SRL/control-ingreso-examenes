<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Modules\Administracion\Infrastructure\Persistence\EloquentStudent;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_estudiante()
    {
        $response = $this->postJson('/api/students', [
            'nombre'   => 'Juan',
            'apellido' => 'Perez',
            'ci'       => '123456',
            'correo'   => 'juan@umss.edu',
            'activo'   => true,
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['nombre' => 'Juan']);
    }

    public function test_listar_estudiantes()
    {
        EloquentStudent::factory()->create(['nombre' => 'Maria']);

        $response = $this->getJson('/api/students');

        $response->assertStatus(200)
                 ->assertJsonFragment(['nombre' => 'Maria']);
    }

  public function test_actualizar_estudiante()
{
    $student = \Database\Factories\StudentFactory::new()->create([
        'nombre' => 'Carlos',
        'apellido' => 'Lopez',
        'ci' => '789012',
        'correo' => 'carlos@umss.edu',
        'activo' => true,
    ]);

    $response = $this->putJson("/api/students/{$student->id}", [
        'nombre' => 'Carlos',
        'apellido' => 'Lopez',
        'ci' => '789012',
        'correo' => 'carlos_actualizado@umss.edu',
        'activo' => false,
    ]);

    $response->assertStatus(200)
             ->assertJsonFragment(['correo' => 'carlos_actualizado@umss.edu']);
}

}   
