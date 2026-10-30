<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Application\Contracts\ExportadorBaseDeDatos;
use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * HU-25: Generación de respaldos puntuales (a demanda) y programados (cron),
 * almacenamiento en disco seguro, exclusión de tablas transitorias y bitácora.
 */
final class RespaldoGeneracionTest extends TestCase
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

    public function test_guests_cannot_generate_backups(): void
    {
        $this->postJson('/api/respaldos')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'No hay una sesión activa.');
    }

    public function test_users_without_permission_cannot_generate_backups(): void
    {
        $docente = $this->usuarioConRol('Docente');

        $this->actingAs($docente)
            ->postJson('/api/respaldos')
            ->assertForbidden();
    }

    public function test_admin_generates_punctual_backup_on_demand_successfully(): void
    {
        $respuesta = $this->actingAs($this->administrador)
            ->postJson('/api/respaldos')
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'Puntual')
            ->assertJsonPath('data.estado', 'Correcto')
            ->assertJsonPath('data.usuario_id', $this->administrador->id)
            ->assertJsonPath('message', 'Respaldo generado.');

        $archivoValor = $respuesta->json('data.archivo');
        $archivo = is_string($archivoValor) ? $archivoValor : '';
        $tamanoValor = $respuesta->json('data.tamano_bytes');
        $tamanoBytes = is_numeric($tamanoValor) ? (int) $tamanoValor : 0;
        $idValor = $respuesta->json('data.id');
        $id = is_numeric($idValor) ? (int) $idValor : 0;

        $this->assertMatchesRegularExpression('/^respaldo-\d{8}-\d{6}(?:_\d+)?\.sql$/', $archivo);
        $this->assertGreaterThan(0, $tamanoBytes);

        // Verifica almacenamiento en disco seguro
        $rutaEnDisco = "respaldos/{$archivo}";
        $this->assertTrue(Storage::disk('local')->exists($rutaEnDisco));

        $contenidoSql = (string) Storage::disk('local')->get($rutaEnDisco);
        $this->assertStringContainsString('SET session_replication_role', $contenidoSql);
        $this->assertStringContainsString('BEGIN;', $contenidoSql);
        $this->assertStringContainsString('COMMIT;', $contenidoSql);

        // Tablas excluidas no deben estar en el volcado
        $this->assertStringNotContainsString('DELETE FROM "respaldos"', $contenidoSql);
        $this->assertStringNotContainsString('DELETE FROM "sessions"', $contenidoSql);
        $this->assertStringNotContainsString('DELETE FROM "cache"', $contenidoSql);
        $this->assertStringNotContainsString('DELETE FROM "jobs"', $contenidoSql);
        $this->assertStringNotContainsString('DELETE FROM "migrations"', $contenidoSql);
        $this->assertStringNotContainsString('DELETE FROM "presencias_puerta"', $contenidoSql);

        // Registro en la base de datos
        $this->assertDatabaseHas('respaldos', [
            'id' => $id,
            'archivo' => $archivo,
            'tipo' => 'puntual',
            'estado' => 'correcto',
            'usuario_id' => $this->administrador->id,
        ]);

        // Registro en la bitácora
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->administrador->id,
            'operacion' => 'respaldo.generar',
            'tabla_afectada' => 'respaldos',
            'registro_id' => $id,
        ]);
    }

    public function test_failed_backup_records_failure_state_and_returns_500(): void
    {
        $exportadorMock = $this->createMock(ExportadorBaseDeDatos::class);
        $exportadorMock->method('exportar')
            ->willThrowException(new RuntimeException('Error de conexión con el motor de base de datos'));

        $this->app->instance(ExportadorBaseDeDatos::class, $exportadorMock);

        $this->actingAs($this->administrador)
            ->postJson('/api/respaldos')
            ->assertStatus(500)
            ->assertJsonPath('message', 'No se pudo generar el respaldo: Error de conexión con el motor de base de datos');

        $this->assertDatabaseHas('respaldos', [
            'tipo' => 'puntual',
            'estado' => 'fallido',
            'error' => 'Error de conexión con el motor de base de datos',
            'usuario_id' => $this->administrador->id,
            'tamano_bytes' => 0,
        ]);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $this->administrador->id,
            'operacion' => 'respaldo.fallido',
            'tabla_afectada' => 'respaldos',
        ]);
    }

    public function test_artisan_command_generates_scheduled_backup(): void
    {
        $codigoSalida = Artisan::call('respaldo:generar');
        $this->assertSame(0, $codigoSalida);

        $registro = DB::table('respaldos')->latest('id')->first();
        $this->assertNotNull($registro);
        /** @var array<string, mixed> $ultimo */
        $ultimo = (array) $registro;
        $this->assertSame('programado', $ultimo['tipo'] ?? null);
        $this->assertSame('correcto', $ultimo['estado'] ?? null);
        $this->assertNull($ultimo['usuario_id'] ?? null);
        $tamanoBytes = is_numeric($ultimo['tamano_bytes'] ?? null) ? (int) $ultimo['tamano_bytes'] : 0;
        $this->assertGreaterThan(0, $tamanoBytes);

        $archivo = is_string($ultimo['archivo'] ?? null) ? $ultimo['archivo'] : '';
        $this->assertTrue(Storage::disk('local')->exists("respaldos/{$archivo}"));

        $ultimoId = is_numeric($ultimo['id'] ?? null) ? (int) $ultimo['id'] : 0;
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => null,
            'operacion' => 'respaldo.generar',
            'tabla_afectada' => 'respaldos',
            'registro_id' => $ultimoId,
        ]);
    }

    public function test_artisan_command_handles_failure_gracefully(): void
    {
        $exportadorMock = $this->createMock(ExportadorBaseDeDatos::class);
        $exportadorMock->method('exportar')
            ->willThrowException(new RuntimeException('Falla en disco al volcar'));

        $this->app->instance(ExportadorBaseDeDatos::class, $exportadorMock);

        $codigoSalida = Artisan::call('respaldo:generar');
        $this->assertSame(1, $codigoSalida);

        $this->assertDatabaseHas('respaldos', [
            'tipo' => 'programado',
            'estado' => 'fallido',
            'error' => 'Falla en disco al volcar',
            'usuario_id' => null,
        ]);

        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => null,
            'operacion' => 'respaldo.fallido',
            'tabla_afectada' => 'respaldos',
        ]);
    }

    public function test_artisan_command_supports_tipo_option(): void
    {
        $codigoSalida = Artisan::call('respaldo:generar', ['--tipo' => 'puntual']);
        $this->assertSame(0, $codigoSalida);

        $registro = DB::table('respaldos')->latest('id')->first();
        $this->assertNotNull($registro);
        /** @var array<string, mixed> $ultimo */
        $ultimo = (array) $registro;
        $this->assertSame('puntual', $ultimo['tipo'] ?? null);
        $this->assertSame('correcto', $ultimo['estado'] ?? null);
    }

    public function test_cron_command_is_scheduled_daily_at_0300(): void
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $eventos = collect($schedule->events())->filter(function (Event $event): bool {
            return is_string($event->command) && str_contains($event->command, 'respaldo:generar');
        });

        $this->assertNotEmpty($eventos, 'El comando respaldo:generar debe estar programado en Schedule.');
        $primerEvento = $eventos->first();
        $this->assertInstanceOf(Event::class, $primerEvento);
        $this->assertSame('0 3 * * *', $primerEvento->expression);
    }
}
