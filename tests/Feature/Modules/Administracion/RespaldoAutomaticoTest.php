<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Application\Actions\GenerarRespaldoAutomatico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * El servidor de TIS no tiene tareas programadas: el respaldo de cada noche
 * se genera al terminar la primera peticion web despues de las 03:00 (hora
 * de La Paz), una vez al dia, sin duplicarse con el planificador.
 */
final class RespaldoAutomaticoTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['umss.respaldo_automatico.habilitado' => true]);
    }

    private function peticion(): void
    {
        // Pasados los 5 minutos del candado de revision.
        Cache::forget('respaldo:automatico:revisado');

        $this->get('/login')->assertOk();
    }

    private function respaldos(): int
    {
        return DB::table('respaldos')->count();
    }

    private function fila(string $tipo, string $estado, string $creado): void
    {
        DB::table('respaldos')->insert([
            'archivo' => $estado === 'CORRECTO' ? 'respaldo_'.md5($tipo.$creado).'.sql' : null,
            'tipo' => $tipo,
            'estado' => $estado,
            'tamano_bytes' => 10,
            'tablas' => 1,
            'filas' => 1,
            'created_at' => $creado,
        ]);
    }

    private function romperElDisco(): void
    {
        Storage::disk('local')->deleteDirectory('respaldos');
        Storage::disk('local')->put('respaldos', 'no es una carpeta');
    }

    public function test_disabled_nothing_is_generated(): void
    {
        config(['umss.respaldo_automatico.habilitado' => false]);
        $this->travelTo('2026-11-25 08:00:00');

        $this->peticion();

        self::assertSame(0, $this->respaldos());
    }

    public function test_before_the_cut_hour_nothing_is_generated(): void
    {
        $this->travelTo('2026-11-25 02:59:00');

        $this->peticion();

        self::assertSame(0, $this->respaldos());
    }

    public function test_the_first_request_after_the_cut_generates_a_scheduled_backup_from_the_system(): void
    {
        $this->travelTo('2026-11-25 08:00:00');

        $this->peticion();

        $this->assertDatabaseHas('respaldos', ['tipo' => 'PROGRAMADO', 'estado' => 'CORRECTO', 'generado_por' => null]);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => null,
            'operacion' => 'respaldo.generar',
        ]);
        self::assertSame(1, $this->respaldos());
    }

    public function test_once_a_day_even_with_many_requests(): void
    {
        $this->travelTo('2026-11-25 08:00:00');

        $this->peticion();
        $this->peticion();
        $this->travelTo('2026-11-25 20:00:00');
        $this->peticion();

        self::assertSame(1, $this->respaldos());
    }

    public function test_the_next_day_generates_another(): void
    {
        $this->travelTo('2026-11-25 08:00:00');
        $this->peticion();

        $this->travelTo('2026-11-26 03:10:00');
        $this->peticion();

        self::assertSame(2, $this->respaldos());
    }

    public function test_the_schedulers_backup_of_the_day_is_not_duplicated(): void
    {
        $this->fila('PROGRAMADO', 'CORRECTO', '2026-11-25 03:00:02');
        $this->travelTo('2026-11-25 08:00:00');

        $this->peticion();

        self::assertSame(1, $this->respaldos());
    }

    public function test_a_manual_backup_does_not_count_as_the_nightly_one(): void
    {
        $this->fila('PUNTUAL', 'CORRECTO', '2026-11-25 05:00:00');
        $this->travelTo('2026-11-25 08:00:00');

        $this->peticion();

        self::assertSame(2, $this->respaldos());
        $this->assertDatabaseHas('respaldos', ['tipo' => 'PROGRAMADO', 'estado' => 'CORRECTO']);
    }

    public function test_a_failure_does_not_break_the_response_nor_retry_in_a_loop(): void
    {
        $this->romperElDisco();
        $this->travelTo('2026-11-25 08:00:00');

        $this->peticion();
        $this->assertDatabaseHas('respaldos', ['tipo' => 'PROGRAMADO', 'estado' => 'FALLIDO']);
        self::assertSame(1, $this->respaldos());

        // A los 10 minutos no se reintenta; a los 31, si.
        $this->travelTo('2026-11-25 08:10:00');
        $this->peticion();
        self::assertSame(1, $this->respaldos());

        $this->travelTo('2026-11-25 08:31:00');
        Cache::forget('respaldo:automatico:intento');
        $this->peticion();
        self::assertSame(2, $this->respaldos());
    }

    public function test_the_retry_window_is_held_by_a_lock_in_the_cache(): void
    {
        $this->romperElDisco();
        $this->travelTo('2026-11-25 08:00:00');

        $this->peticion();

        self::assertTrue(Cache::has('respaldo:automatico:intento'));
    }

    public function test_the_cut_hour_is_configurable_and_read_in_the_universitys_zone(): void
    {
        config(['umss.respaldo_automatico.hora' => '23:30']);
        $accion = $this->app->make(GenerarRespaldoAutomatico::class);

        // 08:00 en La Paz son las 12:00 UTC: sigue siendo antes de las 23:30 locales.
        self::assertFalse($accion->corresponde(new \DateTimeImmutable('2026-11-25 12:00:00+00:00')));
        // 23:45 en La Paz son las 03:45 UTC del dia siguiente.
        self::assertTrue($accion->corresponde(new \DateTimeImmutable('2026-11-26 03:45:00+00:00')));
    }

    public function test_the_scheduled_backups_show_up_as_automatic_in_the_history(): void
    {
        $this->travelTo('2026-11-25 08:00:00');
        $this->peticion();

        $administrador = $this->usuarioConRol('Administrador');
        $this->actingAs($administrador)
            ->getJson('/api/respaldos?filtro=programados')
            ->assertOk()
            ->assertJsonPath('data.0.tipo', 'Programado');
    }
}
