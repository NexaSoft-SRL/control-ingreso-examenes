<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Ingreso\Application\Contracts\ContextoOperativoPuntoControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

final class ContextoPuntoControlHttpTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    private const URL = '/api/punto-control/contexto';

    public function test_visitante_no_consulta_contexto(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
    }

    public function test_usuario_sin_permiso_no_consulta_contexto(): void
    {
        $usuario = $this->usuarioConPermisos([]);

        $this->actingAs($usuario)
            ->getJson(self::URL)
            ->assertForbidden();
    }

    public function test_permiso_general_sin_asignacion_no_revela_examenes(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);

        $this->actingAs($usuario)
            ->getJson(self::URL)
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_contexto_usa_identidad_autenticada_y_contrato(): void
    {
        $usuario = $this->usuarioConPermisos(['punto_control']);

        $contexto = $this->createMock(ContextoOperativoPuntoControl::class);

        $contexto->expects(self::once())
            ->method('examenesAutorizados')
            ->with($usuario->id)
            ->willReturn([
                [
                    'id' => 10,
                    'fecha' => '2026-10-15',
                    'tipo' => 'parcial',
                    'asignatura' => ['nombre' => 'Programación'],
                    'aulas' => [
                        ['id' => 7, 'nombre' => 'A-101'],
                    ],
                ],
            ]);

        $this->app->instance(ContextoOperativoPuntoControl::class, $contexto);

        $this->actingAs($usuario)
            ->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.0.id', 10)
            ->assertJsonPath('data.0.aulas.0.id', 7);
    }
}
