<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Factories\StudentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_estudiante(): void
    {
        $response = $this->postJson('/api/students', [
            'nombre' => 'Juan',
            'apellido' => 'Perez',
            'ci' => '123456',
            'correo' => 'juan@umss.edu',
            'activo' => true,
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonFragment(['nombre' => 'Juan']);

        $this->assertDatabaseHas('students', [
            'ci' => '123456',
            'correo' => 'juan@umss.edu',
        ]);
    }

    public function test_listar_estudiantes(): void
    {
        StudentFactory::new()->create(['nombre' => 'Maria']);

        $response = $this->getJson('/api/students');

        $response
            ->assertStatus(200)
            ->assertJsonFragment(['nombre' => 'Maria']);
    }

    public function test_actualizar_estudiante(): void
    {
        $student = StudentFactory::new()->create([
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

        $response
            ->assertStatus(200)
            ->assertJsonFragment(['correo' => 'carlos_actualizado@umss.edu']);

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'correo' => 'carlos_actualizado@umss.edu',
            'activo' => false,
        ]);
    }
}
