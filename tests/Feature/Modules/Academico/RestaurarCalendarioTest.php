<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Academico;

use App\Modules\Academico\Application\Actions\DetectarPeriodos;
use App\Modules\Academico\Domain\Models\Periodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsuarioConPermisos;
use Tests\TestCase;

/**
 * El calendario académico sale de lo que publica la universidad: el
 * administrador lo ajusta a propósito y puede volver al de la universidad.
 */
final class RestaurarCalendarioTest extends TestCase
{
    use RefreshDatabase;
    use UsuarioConPermisos;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'umss.ruta_genda' => base_path('tests/Fixtures/genda'),
            'umss.ruta_fuentes' => base_path('tests/Fixtures/genda/fuentes.json'),
        ]);

        $this->app->make(DetectarPeriodos::class)->execute();
    }

    public function test_the_administrator_goes_back_to_the_university_calendar(): void
    {
        $periodo = Periodo::where('codigo', '2/2026')->firstOrFail();
        $administrador = $this->usuarioConPermisos(['periodo_oferta']);

        $this->actingAs($administrador)
            ->putJson("/api/periodos/{$periodo->id}/calendario", [
                'ventanas' => ['segundos_parciales' => ['2026-11-23', '2026-12-17']],
            ])
            ->assertOk()
            ->assertJsonPath('data.calendario_ajustado', true)
            ->assertJsonPath('data.calendario_ajustado_por', $administrador->name)
            ->assertJsonPath('data.calendario.segundos_parciales', ['2026-11-23', '2026-12-17']);

        // Ajustado, la deteccion no lo pisa.
        $this->actingAs($administrador)->postJson('/api/periodos/detectar')->assertOk();
        $this->assertSame(['2026-11-23', '2026-12-17'], $periodo->refresh()->ventanas['segundos_parciales'] ?? null);

        $this->actingAs($administrador)
            ->deleteJson("/api/periodos/{$periodo->id}/calendario")
            ->assertOk()
            ->assertJsonPath('data.calendario_ajustado', false)
            ->assertJsonPath('data.calendario_ajustado_por', null)
            ->assertJsonPath('data.calendario_ajustado_en', null)
            ->assertJsonPath('data.calendario.primeros_parciales', ['2026-10-12', '2026-10-31'])
            ->assertJsonPath('data.calendario.segundos_parciales', null)
            ->assertJsonPath('data.fecha_inicio', '2026-08-10');

        $periodo->refresh();

        $this->assertNull($periodo->ajustado_por);
        $this->assertSame(['primeros_parciales' => ['2026-10-12', '2026-10-31']], $periodo->ventanas);
        $this->assertDatabaseHas('bitacora_operaciones', [
            'usuario_id' => $administrador->id,
            'operacion' => 'periodo.calendario_restaurar',
            'registro_id' => $periodo->id,
        ]);
    }

    public function test_the_adjustment_says_who_and_when(): void
    {
        $periodo = Periodo::where('codigo', '2/2026')->firstOrFail();
        $administrador = $this->usuarioConPermisos(['periodo_oferta']);

        $this->actingAs($administrador)->putJson("/api/periodos/{$periodo->id}/calendario", [
            'ventanas' => ['segundos_parciales' => ['2026-11-23', '2026-12-17']],
        ])->assertOk();

        // La lista va por año y por número (0, 1, 2): se busca la fila del período.
        $filas = $this->actingAs($administrador)->getJson('/api/periodos')->assertOk()
            ->json('data');
        $fila = collect(is_array($filas) ? $filas : [])->firstWhere('codigo', '2/2026');

        $this->assertIsArray($fila);
        $this->assertSame('2/2026', $fila['codigo']);
        $this->assertTrue($fila['calendario_ajustado']);
        $this->assertSame($administrador->name, $fila['calendario_ajustado_por']);
        $this->assertIsString($fila['calendario_ajustado_en']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $fila['calendario_ajustado_en']);

        // Sin ajuste, no se atribuye a nadie.
        $otra = Periodo::where('codigo', '1/2026')->firstOrFail();
        $this->assertNull($otra->ajustado_por);
    }

    public function test_without_a_published_calendar_there_is_nothing_to_go_back_to(): void
    {
        // El anual sale de la oferta, sin calendario de la universidad.
        $anual = Periodo::where('codigo', '0/2026')->firstOrFail();
        $administrador = $this->usuarioConPermisos(['periodo_oferta']);
        $anual->forceFill(['ventanas' => ['segundos_parciales' => ['2026-11-23', '2026-12-17']], 'ajustado_por' => $administrador->id])->save();

        $this->actingAs($administrador)
            ->deleteJson("/api/periodos/{$anual->id}/calendario")
            ->assertStatus(409)
            ->assertExactJson(['message' => 'La universidad no publica calendario para este período.']);

        $anual->refresh();

        $this->assertSame(['segundos_parciales' => ['2026-11-23', '2026-12-17']], $anual->ventanas);
        $this->assertSame($administrador->id, $anual->ajustado_por);
        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'periodo.calendario_restaurar']);
    }

    public function test_only_who_manages_the_period_can_go_back(): void
    {
        $periodo = Periodo::where('codigo', '2/2026')->firstOrFail();
        $ruta = "/api/periodos/{$periodo->id}/calendario";

        $this->deleteJson($ruta)->assertUnauthorized();
        $this->actingAs($this->usuarioConPermisos(['examenes']))->deleteJson($ruta)->assertForbidden();
        $this->actingAs($this->usuarioConPermisos(['periodo_oferta']))
            ->deleteJson('/api/periodos/999/calendario')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Período no encontrado.']);

        $this->assertDatabaseMissing('bitacora_operaciones', ['operacion' => 'periodo.calendario_restaurar']);
    }

    public function test_the_log_reads_both_calendar_operations(): void
    {
        $periodo = Periodo::where('codigo', '2/2026')->firstOrFail();
        $administrador = $this->usuarioConPermisos(['periodo_oferta', 'bitacora']);

        $this->actingAs($administrador)->putJson("/api/periodos/{$periodo->id}/calendario", [
            'ventanas' => ['segundos_parciales' => ['2026-11-23', '2026-12-17']],
        ])->assertOk();
        $this->actingAs($administrador)->deleteJson("/api/periodos/{$periodo->id}/calendario")->assertOk();

        $filas = $this->actingAs($administrador)->getJson('/api/bitacora')->assertOk()->json('data');
        $this->assertIsArray($filas);
        $textos = array_column($filas, 'operacion_texto');

        $this->assertContains('Ajustó el calendario académico', $textos);
        $this->assertContains('Volvió al calendario de la universidad', $textos);
    }
}
