<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\Respaldo;
use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * HU-25: Consulta del historial de respaldos, filtros de estado/tipo,
 * conteos globales y paginación en el servidor.
 */
final class RespaldoHistorialTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrador = $this->usuarioConRol('Administrador', [
            'nombre' => 'Rojas Vargas Ana',
            'usuario' => 'ana.rojas',
            'correo' => 'ana.rojas@umss.edu.bo',
        ]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function crearRespaldo(array $atributos): Respaldo
    {
        $respaldo = new Respaldo($atributos);
        $respaldo->timestamps = false;
        $creado = $atributos['created_at'] ?? null;
        $actualizado = $atributos['updated_at'] ?? null;
        $respaldo->created_at = is_string($creado) ? $creado : now();
        $respaldo->updated_at = is_string($actualizado) ? $actualizado : now();
        $respaldo->save();

        return $respaldo;
    }

    public function test_guests_cannot_view_backup_history(): void
    {
        $this->getJson('/api/respaldos')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'No hay una sesión activa.');
    }

    public function test_users_without_permission_cannot_view_backup_history(): void
    {
        $docente = $this->usuarioConRol('Docente');

        $this->actingAs($docente)
            ->getJson('/api/respaldos')
            ->assertForbidden();
    }

    public function test_admin_sees_empty_history_when_no_backups_exist(): void
    {
        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos')
            ->assertOk()
            ->assertExactJson([
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'pagina' => 1,
                    'por_pagina' => 15,
                ],
                'conteos' => [
                    'todos' => 0,
                    'programados' => 0,
                    'puntuales' => 0,
                    'fallidos' => 0,
                ],
                'ultimo' => null,
            ]);
    }

    public function test_backups_are_listed_newest_first_with_attributes(): void
    {
        $viejo = $this->crearRespaldo([
            'archivo' => 'respaldo-20261027-030000.sql',
            'tipo' => 'programado',
            'tamano_bytes' => 5242880,
            'estado' => 'correcto',
            'created_at' => '2026-10-27 03:00:00',
        ]);

        $nuevo = $this->crearRespaldo([
            'archivo' => 'respaldo-20261028-131500.sql',
            'tipo' => 'puntual',
            'tamano_bytes' => 5872025,
            'estado' => 'correcto',
            'usuario_id' => $this->administrador->id,
            'created_at' => '2026-10-28 13:15:00',
        ]);

        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $nuevo->id)
            ->assertJsonPath('data.0.archivo', 'respaldo-20261028-131500.sql')
            ->assertJsonPath('data.0.tipo', 'Puntual')
            ->assertJsonPath('data.0.tamano_bytes', 5872025)
            ->assertJsonPath('data.0.estado', 'Correcto')
            ->assertJsonPath('data.1.id', $viejo->id)
            ->assertJsonPath('data.1.archivo', 'respaldo-20261027-030000.sql')
            ->assertJsonPath('data.1.tipo', 'Programado')
            ->assertJsonPath('data.1.estado', 'Correcto')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('conteos.todos', 2)
            ->assertJsonPath('conteos.programados', 1)
            ->assertJsonPath('conteos.puntuales', 1)
            ->assertJsonPath('conteos.fallidos', 0)
            ->assertJsonPath('ultimo', '2026-10-28 13:15:00');
    }

    public function test_filter_programados_returns_only_scheduled_backups(): void
    {
        $this->crearRespaldo([
            'archivo' => 'respaldo-20261027-030000.sql',
            'tipo' => 'programado',
            'tamano_bytes' => 5000000,
            'estado' => 'correcto',
            'created_at' => '2026-10-27 03:00:00',
        ]);

        $this->crearRespaldo([
            'archivo' => 'respaldo-20261028-131500.sql',
            'tipo' => 'puntual',
            'tamano_bytes' => 5000000,
            'estado' => 'correcto',
            'created_at' => '2026-10-28 13:15:00',
        ]);

        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos?filtro=programados')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.archivo', 'respaldo-20261027-030000.sql')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('conteos.todos', 2)
            ->assertJsonPath('conteos.programados', 1)
            ->assertJsonPath('conteos.puntuales', 1);
    }

    public function test_filter_puntuales_returns_only_punctual_backups(): void
    {
        $this->crearRespaldo([
            'archivo' => 'respaldo-20261027-030000.sql',
            'tipo' => 'programado',
            'tamano_bytes' => 5000000,
            'estado' => 'correcto',
            'created_at' => '2026-10-27 03:00:00',
        ]);

        $this->crearRespaldo([
            'archivo' => 'respaldo-20261028-131500.sql',
            'tipo' => 'puntual',
            'tamano_bytes' => 5000000,
            'estado' => 'correcto',
            'created_at' => '2026-10-28 13:15:00',
        ]);

        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos?filtro=puntuales')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.archivo', 'respaldo-20261028-131500.sql')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_filter_fallidos_returns_only_failed_backups(): void
    {
        $this->crearRespaldo([
            'archivo' => 'respaldo-20261027-030000.sql',
            'tipo' => 'programado',
            'tamano_bytes' => 5000000,
            'estado' => 'correcto',
            'created_at' => '2026-10-27 03:00:00',
        ]);

        $this->crearRespaldo([
            'archivo' => 'respaldo-20261028-131500.sql',
            'tipo' => 'puntual',
            'tamano_bytes' => 0,
            'estado' => 'fallido',
            'error' => 'Falla de conexión',
            'created_at' => '2026-10-28 13:15:00',
        ]);

        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos?filtro=fallidos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estado', 'Fallido')
            ->assertJsonPath('data.0.error', 'Falla de conexión')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('conteos.fallidos', 1);
    }

    public function test_invalid_filter_is_rejected_with_422_and_specific_message(): void
    {
        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos?filtro=desconocido')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filtro' => 'El filtro no existe.']);
    }

    public function test_backups_are_paginated_with_15_per_page(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->crearRespaldo([
                'archivo' => sprintf('respaldo-20261028-%06d.sql', $i),
                'tipo' => 'puntual',
                'tamano_bytes' => 1024,
                'estado' => 'correcto',
                'created_at' => sprintf('2026-10-28 12:%02d:00', $i),
            ]);
        }

        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos?pagina=1&por_pagina=15')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.pagina', 1)
            ->assertJsonPath('meta.por_pagina', 15);

        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos?pagina=2&por_pagina=15')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.pagina', 2);
    }

    public function test_latest_backup_date_ignores_failed_backups(): void
    {
        $this->crearRespaldo([
            'archivo' => 'respaldo-20261028-120000.sql',
            'tipo' => 'puntual',
            'tamano_bytes' => 5000000,
            'estado' => 'correcto',
            'created_at' => '2026-10-28 12:00:00',
        ]);

        $this->crearRespaldo([
            'archivo' => 'respaldo-20261028-140000.sql',
            'tipo' => 'puntual',
            'tamano_bytes' => 0,
            'estado' => 'fallido',
            'error' => 'Falla de disco',
            'created_at' => '2026-10-28 14:00:00',
        ]);

        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos')
            ->assertOk()
            ->assertJsonPath('ultimo', '2026-10-28 12:00:00');
    }
}
