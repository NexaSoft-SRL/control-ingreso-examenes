<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * La bitacora se escribe sola: cada operacion deja su asiento con autor,
 * tabla, registro y hora, sin que nadie lo pida.
 */
final class BitacoraRegistroAutomaticoTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_creating_a_user_records_the_operation(): void
    {
        $this->travelTo('2026-10-12 12:21:00');

        $administrador = $this->usuarioConRol('Administrador');

        $id = $this->actingAs($administrador)
            ->postJson('/api/usuarios', [
                'nombre' => 'Blanco Coca Leticia',
                'usuario' => 'leticia.blanco',
                'correo' => null,
                'rol' => 'Docente',
            ])
            ->assertCreated()
            ->json('data.id');

        self::assertIsInt($id);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $administrador->getKey(),
            'operacion' => 'usuario.crear',
            'tabla_afectada' => 'users',
            'registro_id' => $id,
            'fecha_operacion' => '2026-10-12 12:21:00',
        ]);

        $this->assertDatabaseCount('bitacora_operaciones', 1);
    }

    public function test_the_recorded_operation_shows_up_in_the_log_screen(): void
    {
        $administrador = $this->usuarioConRol('Administrador', ['name' => 'Rojas Vargas Ana']);

        $this->actingAs($administrador)
            ->postJson('/api/usuarios', [
                'nombre' => 'Blanco Coca Leticia',
                'usuario' => 'leticia.blanco',
                'rol' => 'Docente',
            ])
            ->assertCreated();

        $this->actingAs($administrador)
            ->getJson('/api/bitacora?operacion=usuario.crear')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.usuario', 'Rojas Vargas Ana')
            ->assertJsonPath('data.0.rol', 'Administrador')
            ->assertJsonPath('data.0.operacion_texto', 'Creó el usuario');
    }

    public function test_a_failed_creation_does_not_record_anything(): void
    {
        $administrador = $this->usuarioConRol('Administrador');

        $this->actingAs($administrador)
            ->postJson('/api/usuarios', [
                'nombre' => 'Blanco Coca Leticia',
                'usuario' => 'leticia/blanco',
                'rol' => 'Docente',
            ])
            ->assertUnprocessable();

        $this->assertSame(0, DB::table('bitacora_operaciones')->count());
    }
}
