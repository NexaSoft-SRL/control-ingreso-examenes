<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use LogicException;
use Tests\TestCase;

final class BitacoraSoloLecturaTest extends TestCase
{
    use RefreshDatabase;

    public function test_bitacora_exposes_only_read_route(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(
                static fn ($route): bool => $route->uri() === 'api/bitacora'
                    || str_starts_with(
                        $route->uri(),
                        'api/bitacora/',
                    )
            )
            ->values();

        self::assertCount(
            1,
            $routes,
            'La bitácora debe exponer únicamente su ruta de consulta.'
        );

        $route = $routes->first();

        self::assertInstanceOf(
            \Illuminate\Routing\Route::class,
            $route,
        );

        $methods = $route->methods();

        self::assertContains('GET', $methods);
        self::assertContains('HEAD', $methods);

        self::assertNotContains('POST', $methods);
        self::assertNotContains('PUT', $methods);
        self::assertNotContains('PATCH', $methods);
        self::assertNotContains('DELETE', $methods);
    }

    public function test_authenticated_user_cannot_create_audit_record_manually(): void
    {
        $user = UserFactory::new()->createOne();

        $this
            ->actingAs($user)
            ->postJson('/api/bitacora', [
                'usuario_id' => $this->modelId($user),
                'operacion' => 'manipulacion.manual',
                'tabla_afectada' => 'bitacora_operaciones',
            ])
            ->assertMethodNotAllowed();

        $this->assertDatabaseCount(
            'bitacora_operaciones',
            0
        );
    }

    public function test_authenticated_user_cannot_update_audit_record(): void
    {
        $user = UserFactory::new()->createOne();

        $auditId = $this->insertAuditOperation(
            usuarioId: $this->modelId($user),
            operacion: 'asignatura.registrar',
        );

        $this
            ->actingAs($user)
            ->patchJson(
                "/api/bitacora/{$auditId}",
                [
                    'operacion' => 'manipulada',
                    'descripcion' => 'Registro alterado',
                ],
            )
            ->assertNotFound();

        $this->assertDatabaseHas(
            'bitacora_operaciones',
            [
                'id' => $auditId,
                'operacion' => 'asignatura.registrar',
                'descripcion' => null,
            ]
        );
    }

    public function test_authenticated_user_cannot_replace_audit_record(): void
    {
        $user = UserFactory::new()->createOne();

        $auditId = $this->insertAuditOperation(
            usuarioId: $this->modelId($user),
            operacion: 'asignatura.registrar',
        );

        $this
            ->actingAs($user)
            ->putJson(
                "/api/bitacora/{$auditId}",
                [
                    'operacion' => 'manipulada',
                ],
            )
            ->assertNotFound();

        $this->assertDatabaseHas(
            'bitacora_operaciones',
            [
                'id' => $auditId,
                'operacion' => 'asignatura.registrar',
            ]
        );
    }

    public function test_authenticated_user_cannot_delete_audit_record(): void
    {
        $user = UserFactory::new()->createOne();

        $auditId = $this->insertAuditOperation(
            usuarioId: $this->modelId($user),
            operacion: 'asignatura.registrar',
        );

        $this
            ->actingAs($user)
            ->deleteJson("/api/bitacora/{$auditId}")
            ->assertNotFound();

        $this->assertDatabaseHas(
            'bitacora_operaciones',
            [
                'id' => $auditId,
                'operacion' => 'asignatura.registrar',
            ]
        );
    }

    private function modelId(Model $model): int
    {
        $id = $model->getKey();

        if (! is_int($id)) {
            throw new LogicException(
                'El modelo no tiene un identificador entero válido.'
            );
        }

        return $id;
    }

    private function insertAuditOperation(
        ?int $usuarioId,
        string $operacion,
    ): int {
        return (int) DB::table('bitacora_operaciones')
            ->insertGetId([
                'usuario_id' => $usuarioId,
                'operacion' => $operacion,
                'tabla_afectada' => 'asignaturas',
                'registro_id' => 1,
                'descripcion' => null,
                'fecha_operacion' => '2026-09-21 20:00:00',
            ]);
    }
}
