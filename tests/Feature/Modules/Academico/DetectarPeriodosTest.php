<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Academico;

use App\Modules\Academico\Application\Actions\DetectarPeriodos;
use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use App\Modules\Academico\Domain\Models\Periodo;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DetectarPeriodosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'umss.ruta_genda' => base_path('tests/Fixtures/genda'),
            'umss.ruta_fuentes' => base_path('tests/Fixtures/genda/fuentes.json'),
        ]);
    }

    public function test_it_detects_the_periods_of_the_offer_and_of_the_calendars(): void
    {
        $resultado = $this->app->make(DetectarPeriodos::class)->execute();

        $this->assertSame(3, $resultado->creados);
        $this->assertSame(0, $resultado->actualizados);
        $this->assertSame(
            ['2/2026', '1/2026', '0/2026'],
            array_map(static fn ($periodo): string => $periodo->codigo, $resultado->periodos),
        );

        // Con calendario publicado: fechas y ventanas tal cual la fuente.
        $semestre = Periodo::where('codigo', '2/2026')->firstOrFail();

        $this->assertSame(TipoPeriodo::Semestre2, $semestre->tipo);
        $this->assertSame('2026-08-10', $semestre->fecha_inicio?->toDateString());
        $this->assertSame('2026-12-26', $semestre->fecha_fin?->toDateString());
        $this->assertSame(
            ['inicio' => null, 'primeros_parciales' => ['2026-10-12', '2026-10-31']],
            ['inicio' => null] + ($semestre->ventanas ?? []),
        );
        $this->assertSame('Calendario académico FCYT 2/2026', $semestre->fuente);

        // Solo con calendario, sin oferta.
        $this->assertDatabaseHas('periodos', ['codigo' => '1/2026', 'tipo' => 'SEMESTRE_1', 'fecha_inicio' => '2026-02-23']);
    }

    public function test_a_period_without_published_calendar_is_left_without_dates(): void
    {
        $this->app->make(DetectarPeriodos::class)->execute();

        // El anual sale de la carrera con niveles «AÑO» de FACH.
        $this->assertDatabaseHas('periodos', [
            'codigo' => '0/2026',
            'anio' => 2026,
            'numero' => 0,
            'tipo' => 'ANUAL',
            'fecha_inicio' => null,
            'fecha_fin' => null,
            'ventanas' => null,
        ]);

        // Sin registro de fuentes no hay fechas para nadie.
        Periodo::query()->delete();
        config(['umss.ruta_fuentes' => base_path('tests/Fixtures/genda/no-existe.json')]);

        $resultado = $this->app->make(DetectarPeriodos::class)->execute();

        $this->assertSame(2, $resultado->creados);
        $this->assertDatabaseHas('periodos', ['codigo' => '2/2026', 'fecha_inicio' => null, 'fecha_fin' => null]);
        $this->assertDatabaseMissing('periodos', ['codigo' => '1/2026']);
    }

    public function test_detecting_again_updates_without_duplicating(): void
    {
        Periodo::create(['codigo' => '2/2026', 'anio' => 2026, 'numero' => 2, 'tipo' => TipoPeriodo::Semestre2]);

        $primera = $this->app->make(DetectarPeriodos::class)->execute();

        $this->assertSame(2, $primera->creados);
        $this->assertSame(1, $primera->actualizados);
        $this->assertDatabaseHas('periodos', ['codigo' => '2/2026', 'fecha_inicio' => '2026-08-10']);

        $segunda = $this->app->make(DetectarPeriodos::class)->execute();

        $this->assertSame(0, $segunda->creados);
        $this->assertSame(3, $segunda->actualizados);
        $this->assertDatabaseCount('periodos', 3);
    }

    public function test_dates_adjusted_by_hand_are_never_overwritten(): void
    {
        $administrador = UserFactory::new()->createOne();

        Periodo::create([
            'codigo' => '2/2026',
            'anio' => 2026,
            'numero' => 2,
            'tipo' => TipoPeriodo::Semestre2,
            'fecha_inicio' => '2026-08-17',
            'fecha_fin' => '2026-12-19',
            'ajustado_por' => $administrador->id,
        ]);

        $this->app->make(DetectarPeriodos::class)->execute();

        $this->assertDatabaseHas('periodos', [
            'codigo' => '2/2026',
            'fecha_inicio' => '2026-08-17',
            'fecha_fin' => '2026-12-19',
            'ajustado_por' => $administrador->id,
            'ventanas' => null,
        ]);
    }
}
