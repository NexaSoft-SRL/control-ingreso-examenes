<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class IdentificarEnPuertaHttpTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    private const RUTA = '/api/aulas/1/punto-control/identificar';

    public function test_requiere_autenticacion(): void
    {
        $this->postJson(self::RUTA, [
            'tipo' => 'codigo',
            'identificador' => '20260017',
        ])->assertUnauthorized();
    }

    public function test_requiere_permiso_de_punto_control(): void
    {
        $usuario = $this->usuarioConPermisos([]);

        $this->actingAs($usuario)
            ->postJson(self::RUTA, [
                'tipo' => 'codigo',
                'identificador' => '20260017',
            ])
            ->assertForbidden();
    }

    public function test_rechaza_identificador_invalido(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);

        $this->actingAs($usuario)
            ->postJson(self::RUTA, [
                'tipo' => 'codigo',
                'identificador' => '12A',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['identificador']);
    }

    public function test_rechaza_tipo_no_admitido(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);

        $this->actingAs($usuario)
            ->postJson(self::RUTA, [
                'tipo' => 'invalido',
                'identificador' => '20260017',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo']);
    }

    public function test_no_permite_identificar_sin_asignacion(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);

        $this->actingAs($usuario)
            ->postJson(self::RUTA, [
                'tipo' => 'codigo',
                'identificador' => '20260017',
            ])
            ->assertStatus(403);
    }
}
