<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\Respaldo;
use App\Modules\Administracion\Domain\Models\User;
use App\Modules\Administracion\Infrastructure\Export\ExportadorSqlBaseDeDatos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * HU-25: Descarga segura de archivos .sql y restauración transaccional
 * de la base de datos con verificación de esquema y bitácora.
 */
final class RespaldoRestauracionTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

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

    private function versionActual(): string
    {
        $version = config('respaldo.version_esquema');
        if (is_string($version) && $version !== '') {
            return $version;
        }

        $migracion = DB::table('migrations')->orderByDesc('id')->value('migration');

        return is_string($migracion) ? $migracion : '2026_10_28_000100_create_respaldos_table';
    }

    // -------------------------------------------------------------------------
    // Descarga (/api/respaldos/{id}/archivo)
    // -------------------------------------------------------------------------

    public function test_guests_cannot_download_backup_file(): void
    {
        $this->getJson('/api/respaldos/1/archivo')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'No hay una sesión activa.');
    }

    public function test_users_without_permission_cannot_download_backup_file(): void
    {
        $docente = $this->usuarioConRol('Docente');

        $this->actingAs($docente)
            ->getJson('/api/respaldos/1/archivo')
            ->assertForbidden();
    }

    public function test_download_nonexistent_backup_returns_404(): void
    {
        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos/999/archivo')
            ->assertNotFound()
            ->assertJsonPath('message', 'Respaldo no encontrado.');
    }

    public function test_download_failed_backup_returns_422(): void
    {
        $respaldo = $this->crearRespaldo([
            'archivo' => 'respaldo-20261028-100000.sql',
            'tipo' => 'puntual',
            'tamano_bytes' => 0,
            'estado' => 'fallido',
            'error' => 'Disk full',
            'version_esquema' => 'v1.0.0',
            'usuario_id' => $this->administrador->id,
        ]);

        $this->actingAs($this->administrador)
            ->getJson("/api/respaldos/{$respaldo->id}/archivo")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ese respaldo falló: no hay archivo que descargar.');
    }

    public function test_download_missing_file_from_disk_returns_404(): void
    {
        $respaldo = $this->crearRespaldo([
            'archivo' => 'respaldo-20261028-110000.sql',
            'tipo' => 'puntual',
            'tamano_bytes' => 1024,
            'estado' => 'correcto',
            'error' => null,
            'version_esquema' => 'v1.0.0',
            'usuario_id' => $this->administrador->id,
        ]);

        // No existe en Storage::fake('local')
        $this->actingAs($this->administrador)
            ->getJson("/api/respaldos/{$respaldo->id}/archivo")
            ->assertNotFound()
            ->assertJsonPath('message', 'El archivo de ese respaldo ya no está en disco.');
    }

    public function test_admin_downloads_backup_file_successfully(): void
    {
        $contenidoSql = "-- Volcado de prueba\nSELECT 1;";
        $nombreArchivo = 'respaldo-20261028-120000.sql';
        Storage::disk('local')->put("respaldos/{$nombreArchivo}", $contenidoSql);

        $respaldo = $this->crearRespaldo([
            'archivo' => $nombreArchivo,
            'tipo' => 'puntual',
            'tamano_bytes' => strlen($contenidoSql),
            'estado' => 'correcto',
            'error' => null,
            'version_esquema' => 'v1.0.0',
            'usuario_id' => $this->administrador->id,
        ]);

        $respuesta = $this->actingAs($this->administrador)
            ->get("/api/respaldos/{$respaldo->id}/archivo")
            ->assertOk()
            ->assertHeader('content-type', 'application/sql')
            ->assertHeader('content-disposition', sprintf('attachment; filename="%s"', $nombreArchivo));

        $this->assertSame($contenidoSql, $respuesta->getContent());
    }

    public function test_admin_can_download_incompatible_backup_file(): void
    {
        $contenidoSql = "-- Volcado esquema anterior\nSELECT 1;";
        $nombreArchivo = 'respaldo-20261025-100000.sql';
        Storage::disk('local')->put("respaldos/{$nombreArchivo}", $contenidoSql);

        $respaldo = $this->crearRespaldo([
            'archivo' => $nombreArchivo,
            'tipo' => 'programado',
            'tamano_bytes' => strlen($contenidoSql),
            'estado' => 'incompatible',
            'error' => 'Versión incompatible',
            'version_esquema' => 'v0.9.0',
            'usuario_id' => null,
        ]);

        $this->actingAs($this->administrador)
            ->get("/api/respaldos/{$respaldo->id}/archivo")
            ->assertOk()
            ->assertHeader('content-disposition', sprintf('attachment; filename="%s"', $nombreArchivo));
    }

    // -------------------------------------------------------------------------
    // Restauración (/api/respaldos/{id}/restaurar)
    // -------------------------------------------------------------------------

    public function test_guests_cannot_restore_backup(): void
    {
        $this->postJson('/api/respaldos/1/restaurar')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'No hay una sesión activa.');
    }

    public function test_users_without_permission_cannot_restore_backup(): void
    {
        $docente = $this->usuarioConRol('Docente');

        $this->actingAs($docente)
            ->postJson('/api/respaldos/1/restaurar')
            ->assertForbidden();
    }

    public function test_restore_nonexistent_backup_returns_404(): void
    {
        $this->actingAs($this->administrador)
            ->postJson('/api/respaldos/999/restaurar')
            ->assertNotFound()
            ->assertJsonPath('message', 'Respaldo no encontrado.');
    }

    public function test_restore_failed_backup_returns_422(): void
    {
        $respaldo = $this->crearRespaldo([
            'archivo' => 'respaldo-20261028-130000.sql',
            'tipo' => 'puntual',
            'tamano_bytes' => 0,
            'estado' => 'fallido',
            'error' => 'Error de disco',
            'version_esquema' => 'v1.0.0',
            'usuario_id' => $this->administrador->id,
        ]);

        $this->actingAs($this->administrador)
            ->postJson("/api/respaldos/{$respaldo->id}/restaurar")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ese respaldo falló: no se puede restaurar.');
    }

    public function test_restore_missing_file_from_disk_returns_404(): void
    {
        $version = $this->versionActual();

        $respaldo = $this->crearRespaldo([
            'archivo' => 'respaldo-20261028-140000.sql',
            'tipo' => 'puntual',
            'tamano_bytes' => 1024,
            'estado' => 'correcto',
            'error' => null,
            'version_esquema' => $version,
            'usuario_id' => $this->administrador->id,
        ]);

        $this->actingAs($this->administrador)
            ->postJson("/api/respaldos/{$respaldo->id}/restaurar")
            ->assertNotFound()
            ->assertJsonPath('message', 'El archivo de ese respaldo ya no está en disco.');
    }

    public function test_restore_incompatible_schema_backup_returns_422_and_marks_incompatible(): void
    {
        $nombreArchivo = 'respaldo-20261020-090000.sql';
        Storage::disk('local')->put("respaldos/{$nombreArchivo}", "-- Archivo antiguo\n");

        $respaldo = $this->crearRespaldo([
            'archivo' => $nombreArchivo,
            'tipo' => 'programado',
            'tamano_bytes' => 500,
            'estado' => 'correcto',
            'error' => null,
            'version_esquema' => 'v0.5.0',
            'usuario_id' => null,
        ]);

        $this->actingAs($this->administrador)
            ->postJson("/api/respaldos/{$respaldo->id}/restaurar")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El respaldo corresponde a una versión anterior incompatible.');

        // Verifica que la fila en base de datos quedó marcada como incompatible
        $respaldo->refresh();
        $this->assertSame('incompatible', $respaldo->estado);
        $this->assertStringContainsString('v0.5.0', (string) $respaldo->error);

        // Verifica registro en bitácora
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->administrador->id,
            'operacion' => 'respaldo.incompatible',
            'tabla_afectada' => 'respaldos',
            'registro_id' => $respaldo->id,
        ]);
    }

    public function test_admin_restores_backup_successfully_and_updates_states_and_bitacora(): void
    {
        // 1. Exporta el estado actual como un volcado válido
        $exportador = new ExportadorSqlBaseDeDatos;
        $version = $this->versionActual();
        $sql = $exportador->exportar($version, 'puntual');

        $nombreArchivo = 'respaldo-20261028-150000.sql';
        Storage::disk('local')->put("respaldos/{$nombreArchivo}", $sql);

        $respaldo = $this->crearRespaldo([
            'archivo' => $nombreArchivo,
            'tipo' => 'puntual',
            'tamano_bytes' => strlen($sql),
            'estado' => 'correcto',
            'error' => null,
            'version_esquema' => $version,
            'usuario_id' => $this->administrador->id,
        ]);

        // 2. Ejecuta la restauración
        $this->actingAs($this->administrador)
            ->postJson("/api/respaldos/{$respaldo->id}/restaurar")
            ->assertOk()
            ->assertJsonPath('message', 'Restaurado con éxito')
            ->assertJsonPath('data.id', $respaldo->id)
            ->assertJsonPath('data.estado', 'Restaurado');

        // 3. Verifica en base de datos
        $respaldo->refresh();
        $this->assertSame('restaurado', $respaldo->estado);
        $this->assertNull($respaldo->error);

        // 4. Verifica registro en bitácora
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->administrador->id,
            'operacion' => 'respaldo.restaurar',
            'tabla_afectada' => 'respaldos',
            'registro_id' => $respaldo->id,
        ]);
    }

    public function test_restoring_a_second_backup_reverts_first_to_correcto(): void
    {
        $version = $this->versionActual();
        $sqlValido = "SELECT 1;\n";

        $nombre1 = 'respaldo-20261028-160000.sql';
        $nombre2 = 'respaldo-20261028-170000.sql';

        Storage::disk('local')->put("respaldos/{$nombre1}", $sqlValido);
        Storage::disk('local')->put("respaldos/{$nombre2}", $sqlValido);

        // Respaldo 1 actualmente restaurado
        $respaldo1 = $this->crearRespaldo([
            'archivo' => $nombre1,
            'tipo' => 'programado',
            'tamano_bytes' => strlen($sqlValido),
            'estado' => 'restaurado',
            'error' => null,
            'version_esquema' => $version,
            'usuario_id' => null,
        ]);

        // Respaldo 2 correcto
        $respaldo2 = $this->crearRespaldo([
            'archivo' => $nombre2,
            'tipo' => 'puntual',
            'tamano_bytes' => strlen($sqlValido),
            'estado' => 'correcto',
            'error' => null,
            'version_esquema' => $version,
            'usuario_id' => $this->administrador->id,
        ]);

        // Restaurar Respaldo 2
        $this->actingAs($this->administrador)
            ->postJson("/api/respaldos/{$respaldo2->id}/restaurar")
            ->assertOk()
            ->assertJsonPath('message', 'Restaurado con éxito')
            ->assertJsonPath('data.id', $respaldo2->id)
            ->assertJsonPath('data.estado', 'Restaurado');

        // Respaldo 1 pasa a ser Correcto
        $respaldo1->refresh();
        $this->assertSame('correcto', $respaldo1->estado);

        // Respaldo 2 es Restaurado
        $respaldo2->refresh();
        $this->assertSame('restaurado', $respaldo2->estado);
    }
}
