<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use App\Modules\Administracion\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * Ruta 14 y el historial de respaldos: cada generacion deja su fila,
 * tambien la fallida, y una restauracion no se lleva el historial ni la
 * sesion de quien la ordena.
 */
final class RespaldoHistorialTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->administrador = $this->usuarioConRol('Administrador');
    }

    private function generar(): int
    {
        $id = $this->actingAs($this->administrador)
            ->postJson('/api/respaldos')
            ->assertCreated()
            ->json('data.id');

        self::assertIsInt($id);

        return $id;
    }

    /**
     * Con la carpeta de respaldos ocupada por un archivo no se puede
     * escribir dentro: la generacion falla. Los respaldos que ya hubiera
     * se apartan y vuelven al reparar.
     */
    private function romperElDisco(): void
    {
        $disco = Storage::disk('local');

        foreach ($disco->files('respaldos') as $ruta) {
            self::assertIsString($ruta);
            $disco->move($ruta, 'apartados/'.basename($ruta));
        }

        $disco->deleteDirectory('respaldos');
        $disco->put('respaldos', 'no es una carpeta');
    }

    private function repararElDisco(): void
    {
        $disco = Storage::disk('local');

        $disco->delete('respaldos');

        foreach ($disco->files('apartados') as $ruta) {
            self::assertIsString($ruta);
            $disco->move($ruta, 'respaldos/'.basename($ruta));
        }
    }

    public function test_the_history_is_listed_newest_first_with_its_figures(): void
    {
        $this->travelTo('2026-10-11 03:00:00');
        Artisan::call('respaldo:generar');

        $this->travelTo('2026-10-12 03:00:00');
        Artisan::call('respaldo:generar');

        $this->travelTo('2026-10-12 10:30:00');
        $puntual = $this->generar();

        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $puntual)
            ->assertJsonPath('data.0.tipo', 'Puntual')
            ->assertJsonPath('data.0.fecha', '2026-10-12T10:30:00-04:00')
            ->assertJsonPath('data.1.tipo', 'Programado')
            ->assertJsonPath('data.1.fecha', '2026-10-12T03:00:00-04:00')
            ->assertJsonPath('data.1.archivo', 'respaldo-20261012-030000.sql')
            ->assertJsonPath('data.1.estado', 'correcto')
            ->assertJsonPath('data.1.restaurado', false)
            ->assertJsonPath('data.2.archivo', 'respaldo-20261011-030000.sql')
            ->assertJsonPath('meta', [
                'total' => 3,
                'pagina' => 1,
                'por_pagina' => 15,
                'conteos' => ['todos' => 3, 'programados' => 2, 'puntuales' => 1, 'fallidos' => 0],
                'programacion' => 'Diario, 03:00',
                'ultimo' => '2026-10-12T10:30:00-04:00',
            ]);

        $this->assertSame(
            ['id', 'fecha', 'tipo', 'tamano_bytes', 'estado', 'restaurado', 'archivo'],
            array_keys((array) $this->getJson('/api/respaldos')->json('data.0')),
        );
    }

    public function test_the_history_is_filtered_and_paginated(): void
    {
        $this->travelTo('2026-10-10 03:00:00');
        Artisan::call('respaldo:generar');

        $this->travelTo('2026-10-11 10:00:00');
        $primero = $this->generar();

        $this->travelTo('2026-10-12 10:00:00');
        $segundo = $this->generar();

        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos?filtro=programados')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tipo', 'Programado')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.conteos.todos', 3);

        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos?filtro=puntuales&por_pagina=1&pagina=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $primero)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.pagina', 2)
            ->assertJsonPath('meta.por_pagina', 1);

        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos?filtro=todos')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $segundo);
    }

    public function test_the_filters_are_validated(): void
    {
        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos?filtro=viejos&por_pagina=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filtro', 'por_pagina'])
            ->assertJsonPath('errors.filtro.0', 'El filtro no existe.');
    }

    public function test_an_empty_history_has_no_last_backup(): void
    {
        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.ultimo', null);
    }

    public function test_a_failed_backup_leaves_a_row_with_its_error(): void
    {
        $this->romperElDisco();

        $respuesta = $this->actingAs($this->administrador)
            ->postJson('/api/respaldos')
            ->assertStatus(500)
            ->assertJsonPath('message', 'No se pudo generar el respaldo.')
            ->assertJsonPath('data.estado', 'fallido')
            ->assertJsonPath('data.tipo', 'Puntual')
            ->assertJsonPath('data.archivo', null)
            ->assertJsonPath('data.tamano_bytes', null);

        $id = $respuesta->json('data.id');

        self::assertIsInt($id);

        $this->assertDatabaseHas('respaldos', [
            'id' => $id,
            'estado' => 'FALLIDO',
            'archivo' => null,
            'generado_por' => $this->administrador->getKey(),
        ]);

        $this->assertNotEmpty(DB::table('respaldos')->where('id', $id)->value('error'));

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'respaldo.fallar',
            'registro_id' => $id,
        ]);
        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'respaldo.generar']);
    }

    public function test_the_failed_backups_have_their_own_filter_and_do_not_count_as_the_last_one(): void
    {
        $this->travelTo('2026-10-11 10:00:00');
        $this->generar();

        $this->travelTo('2026-10-12 03:00:00');
        $this->romperElDisco();
        Artisan::call('respaldo:generar');
        $this->repararElDisco();

        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos?filtro=fallidos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estado', 'fallido')
            ->assertJsonPath('data.0.tipo', 'Programado')
            ->assertJsonPath('meta.conteos', ['todos' => 2, 'programados' => 1, 'puntuales' => 1, 'fallidos' => 1])
            ->assertJsonPath('meta.ultimo', '2026-10-11T10:00:00-04:00');
    }

    public function test_a_failed_backup_cannot_be_downloaded_or_restored(): void
    {
        $this->romperElDisco();

        $id = $this->actingAs($this->administrador)
            ->postJson('/api/respaldos')
            ->assertStatus(500)
            ->json('data.id');

        self::assertIsInt($id);

        $this->actingAs($this->administrador)
            ->getJson("/api/respaldos/{$id}/descargar")
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'RESPALDO_FALLIDO');

        $this->actingAs($this->administrador)
            ->postJson("/api/respaldos/{$id}/restaurar")
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'RESPALDO_FALLIDO');
    }

    public function test_a_backup_from_before_the_redesign_is_refused(): void
    {
        // Un guion del esquema anterior: no nombra la tabla `periodos`.
        Storage::disk('local')->put(
            'respaldos/respaldo-20260901-020000.sql',
            'BEGIN;'.PHP_EOL
            .'TRUNCATE "students", "ambientes", "users" RESTART IDENTITY CASCADE;'.PHP_EOL
            .'COMMIT;'.PHP_EOL,
        );

        $id = DB::table('respaldos')->insertGetId([
            'archivo' => 'respaldo-20260901-020000.sql',
            'tipo' => 'PROGRAMADO',
            'estado' => 'CORRECTO',
            'created_at' => '2026-09-01 06:00:00',
        ]);

        $this->actingAs($this->administrador)
            ->postJson("/api/respaldos/{$id}/restaurar")
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'RESPALDO_INCOMPATIBLE')
            ->assertJsonStructure(['message', 'codigo']);

        // No se toco nada.
        $this->assertDatabaseHas('users', ['id' => $this->administrador->getKey()]);
        $this->assertDatabaseHas('respaldos', ['id' => $id, 'restaurado_en' => null]);
        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'respaldo.restaurar']);

        // Pero se puede descargar.
        $this->actingAs($this->administrador)
            ->get("/api/respaldos/{$id}/descargar")
            ->assertOk();
    }

    public function test_only_the_last_restored_backup_is_marked_as_restored(): void
    {
        $this->travelTo('2026-10-11 10:00:00');
        $viejo = $this->generar();

        $this->travelTo('2026-10-12 10:00:00');
        $nuevo = $this->generar();

        $this->travelTo('2026-10-12 11:00:00');
        $this->actingAs($this->administrador)
            ->postJson("/api/respaldos/{$nuevo}/restaurar")
            ->assertOk();

        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos')
            ->assertOk()
            ->assertJsonPath('data.0.id', $nuevo)
            ->assertJsonPath('data.0.restaurado', true)
            ->assertJsonPath('data.1.restaurado', false);

        $this->travelTo('2026-10-12 12:00:00');
        $this->actingAs($this->administrador)
            ->postJson("/api/respaldos/{$viejo}/restaurar")
            ->assertOk();

        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos')
            ->assertOk()
            ->assertJsonPath('data.0.id', $nuevo)
            ->assertJsonPath('data.0.restaurado', false)
            ->assertJsonPath('data.1.id', $viejo)
            ->assertJsonPath('data.1.restaurado', true);

        $this->assertDatabaseHas('respaldos', [
            'id' => $viejo,
            'restaurado_en' => '2026-10-12 12:00:00',
            'restaurado_por' => $this->administrador->getKey(),
        ]);
    }

    public function test_restoring_does_not_erase_the_history(): void
    {
        $this->travelTo('2026-10-11 10:00:00');
        $viejo = $this->generar();

        // Despues del respaldo: otra cuenta genera uno mas y uno falla.
        $posterior = $this->usuarioConRol('Administrador');

        $this->travelTo('2026-10-12 10:00:00');
        $deOtraCuenta = $this->actingAs($posterior)
            ->postJson('/api/respaldos')
            ->assertCreated()
            ->json('data.id');

        $this->romperElDisco();
        $this->actingAs($this->administrador)->postJson('/api/respaldos')->assertStatus(500);
        $this->repararElDisco();

        $this->actingAs($this->administrador)
            ->postJson("/api/respaldos/{$viejo}/restaurar")
            ->assertOk();

        // Las tres filas siguen ahi, tambien las posteriores al respaldo.
        $this->assertDatabaseCount('respaldos', 3);
        $this->assertDatabaseHas('respaldos', [
            'id' => $viejo,
            'generado_por' => $this->administrador->getKey(),
        ]);

        // La cuenta que genero el segundo no existe en el respaldo: la fila
        // se conserva sin su autor.
        $this->assertDatabaseMissing('users', ['id' => $posterior->getKey()]);
        $this->assertDatabaseHas('respaldos', ['id' => $deOtraCuenta, 'generado_por' => null]);

        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos')
            ->assertOk()
            ->assertJsonPath('meta.conteos', ['todos' => 3, 'programados' => 0, 'puntuales' => 3, 'fallidos' => 1]);

        // Y el historial sigue numerando donde iba.
        $this->assertGreaterThan($deOtraCuenta, $this->generar());
    }

    public function test_restoring_does_not_close_the_session_of_who_ordered_it(): void
    {
        $respaldo = $this->generar();

        DB::table('sessions')->insert([
            'id' => 'sesion-de-prueba',
            'user_id' => $this->administrador->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'x',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->actingAs($this->administrador)
            ->postJson("/api/respaldos/{$respaldo}/restaurar")
            ->assertOk();

        $this->assertDatabaseHas('sessions', [
            'id' => 'sesion-de-prueba',
            'user_id' => $this->administrador->getKey(),
        ]);

        // La cuenta sigue ahi y la peticion siguiente entra sin volver a
        // identificarse.
        $this->actingAs($this->administrador)
            ->getJson('/api/respaldos')
            ->assertOk();
    }

    public function test_a_restore_ordered_by_an_account_newer_than_the_backup_is_still_recorded(): void
    {
        $respaldo = $this->generar();
        $posterior = $this->usuarioConRol('Administrador');

        $this->actingAs($posterior)
            ->postJson("/api/respaldos/{$respaldo}/restaurar")
            ->assertOk();

        $this->assertDatabaseHas('bitacora_operaciones', [
            'operacion' => 'respaldo.restaurar',
            'usuario_id' => null,
            'registro_id' => $respaldo,
        ]);

        $this->assertDatabaseHas('respaldos', [
            'id' => $respaldo,
            'restaurado_por' => null,
        ]);

        $this->assertNotNull(DB::table('respaldos')->where('id', $respaldo)->value('restaurado_en'));
    }
}
