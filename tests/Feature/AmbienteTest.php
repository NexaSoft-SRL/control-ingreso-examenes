<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Administracion\Domain\Models\User;
use Database\Factories\AmbienteFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmbienteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var User $user */
        $user = UserFactory::new()->create([
            'is_active' => true,
        ]);

        $this->user = $user;
    }

    public function test_crear_ambiente(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/admin/ambientes', [
                'nombre' => 'Aula 101',
                'ubicacion' => 'Edificio A',
                'capacidad' => 40,
                'estado' => 'DISPONIBLE',
            ]);

        $response
            ->assertStatus(201)
            ->assertJsonFragment(['nombre' => 'Aula 101']);

        $this->assertDatabaseHas('ambientes', [
            'nombre' => 'Aula 101',
            'capacidad' => 40,
        ]);
    }

    public function test_listar_ambientes(): void
    {
        AmbienteFactory::new()->create(['nombre' => 'Aula 202']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/admin/ambientes');

        $response
            ->assertStatus(200)
            ->assertJsonFragment(['nombre' => 'Aula 202']);
    }

    public function test_actualizar_ambiente(): void
    {
        $ambiente = AmbienteFactory::new()->create([
            'nombre' => 'Aula 303',
            'capacidad' => 30,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/admin/ambientes/{$ambiente->id}", [
                'nombre' => 'Aula 303',
                'ubicacion' => 'Edificio C',
                'capacidad' => 50,
                'estado' => 'MANTENIMIENTO',
            ]);

        $response
            ->assertStatus(200)
            ->assertJsonFragment(['capacidad' => 50]);

        $this->assertDatabaseHas('ambientes', [
            'id' => $ambiente->id,
            'capacidad' => 50,
            'estado' => 'MANTENIMIENTO',
        ]);
    }

    public function test_eliminar_ambiente(): void
    {
        $ambiente = AmbienteFactory::new()->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/admin/ambientes/{$ambiente->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('ambientes', [
            'id' => $ambiente->id,
        ]);
    }
}
