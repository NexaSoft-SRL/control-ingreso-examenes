<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Administracion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * El respaldo automatico se configura desde la pantalla: lo guardado manda
 * sobre el .env y cada cambio queda en la bitacora.
 */
final class AjustesDeRespaldoTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_without_saved_settings_the_environment_values_apply(): void
    {
        config([
            'umss.respaldo_automatico.habilitado' => true,
            'umss.respaldo_automatico.hora' => '04:15',
            'umss.respaldo_automatico.reintento_minutos' => 45,
        ]);

        $this->actingAs($this->usuarioConRol('Administrador'))
            ->getJson('/api/respaldos/ajustes')
            ->assertOk()
            ->assertExactJson(['data' => [
                'habilitado' => true,
                'hora' => '04:15',
                'reintento_minutos' => 45,
                'programacion' => 'Diario, 04:15',
            ]]);
    }

    public function test_the_administration_saves_the_settings_and_they_override_the_environment(): void
    {
        config(['umss.respaldo_automatico.habilitado' => false]);
        $admin = $this->usuarioConRol('Administrador');

        $this->actingAs($admin)
            ->putJson('/api/respaldos/ajustes', ['habilitado' => true, 'hora' => '22:30'])
            ->assertOk()
            ->assertJsonPath('data.programacion', 'Diario, 22:30');

        $this->actingAs($admin)
            ->getJson('/api/respaldos/ajustes')
            ->assertJsonPath('data.habilitado', true)
            ->assertJsonPath('data.hora', '22:30')
            ->assertJsonPath('data.reintento_minutos', 30);
        $this->actingAs($admin)
            ->getJson('/api/respaldos')
            ->assertJsonPath('meta.programacion', 'Diario, 22:30');

        $descripcion = DB::table('bitacora_operaciones')->where('operacion', 'respaldo.ajustes')->value('descripcion');
        $this->assertIsString($descripcion);
        $this->assertStringContainsString('Hora: 03:00 → 22:30', $descripcion);
        $this->assertStringContainsString('Automático: no → sí', $descripcion);
    }

    public function test_the_retry_wait_is_kept_when_only_the_switch_and_the_hour_are_sent(): void
    {
        config(['umss.respaldo_automatico.reintento_minutos' => 45]);
        $admin = $this->usuarioConRol('Administrador');

        $this->actingAs($admin)
            ->putJson('/api/respaldos/ajustes', ['habilitado' => true, 'hora' => '05:00'])
            ->assertOk()
            ->assertJsonPath('data.reintento_minutos', 45);
    }

    public function test_it_says_when_the_automatic_backup_is_off(): void
    {
        $admin = $this->usuarioConRol('Administrador');

        $this->actingAs($admin)
            ->putJson('/api/respaldos/ajustes', ['habilitado' => false, 'hora' => '03:00', 'reintento_minutos' => 30])
            ->assertOk()
            ->assertJsonPath('data.programacion', 'Automático apagado');
    }

    public function test_the_settings_are_validated(): void
    {
        $admin = $this->usuarioConRol('Administrador');

        foreach ([
            ['habilitado' => true, 'hora' => '25:00', 'reintento_minutos' => 30],
            ['habilitado' => true, 'hora' => '3am', 'reintento_minutos' => 30],
            ['habilitado' => true, 'hora' => '03:00', 'reintento_minutos' => 1],
            ['habilitado' => true, 'hora' => '03:00', 'reintento_minutos' => 5000],
            ['hora' => '03:00'],
        ] as $cuerpo) {
            $this->actingAs($admin)->putJson('/api/respaldos/ajustes', $cuerpo)->assertUnprocessable();
        }

        $this->assertDatabaseCount('ajustes', 0);
    }

    public function test_only_who_manages_backups_changes_the_settings(): void
    {
        $this->actingAs($this->usuarioConRol('Docente'))
            ->putJson('/api/respaldos/ajustes', ['habilitado' => true, 'hora' => '03:00', 'reintento_minutos' => 30])
            ->assertForbidden();
    }

    public function test_a_saved_off_switch_stops_the_nightly_backup_even_if_the_environment_enables_it(): void
    {
        config(['umss.respaldo_automatico.habilitado' => true]);
        $this->actingAs($this->usuarioConRol('Administrador'))
            ->putJson('/api/respaldos/ajustes', ['habilitado' => false, 'hora' => '00:00', 'reintento_minutos' => 30])
            ->assertOk();
        Cache::forget('respaldo:automatico:revisado');

        $this->get('/login')->assertOk();

        $this->assertSame(0, DB::table('respaldos')->count());
    }
}
