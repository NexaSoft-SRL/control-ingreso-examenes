<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Academico;

use App\Modules\Academico\Application\Contracts\PeriodoGateway;
use App\Modules\Academico\Domain\Enums\EstadoPeriodo;
use App\Modules\Academico\Domain\Enums\TipoPeriodo;
use App\Modules\Academico\Domain\Models\Periodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DatosAcademicos;
use Tests\TestCase;

final class PeriodoGatewayTest extends TestCase
{
    use DatosAcademicos;
    use RefreshDatabase;

    public function test_period_state_is_computed_from_its_dates(): void
    {
        $this->assertSame(EstadoPeriodo::Vigente, $this->periodo('2/2030', 2, -10, 10)->estado());
        $this->assertSame(EstadoPeriodo::Vigente, $this->periodo('1/2030', 1, 0, 0)->estado());
        $this->assertSame(EstadoPeriodo::Cerrado, $this->periodo('2/2029', 2, -90, -1)->estado());
        $this->assertSame(EstadoPeriodo::Proximo, $this->periodo('1/2031', 1, 1, 90)->estado());
        $this->assertSame(EstadoPeriodo::SinFechas, $this->periodo('0/2030', 0, null, null)->estado());
    }

    public function test_several_periods_may_be_current_and_the_main_one_has_the_most_groups(): void
    {
        $semestre = $this->periodo('2/2030', 2, -10, 10);
        $anual = $this->periodo('0/2030', 0, -200, 100);
        $this->periodo('1/2030', 1, -200, -20);
        $this->periodo('4/2030', 4, null, null);

        $this->grupo(null, null, null, $anual);
        $this->grupo(null, null, null, $semestre);
        $this->grupo(null, null, null, $semestre);

        $periodos = $this->app->make(PeriodoGateway::class);

        $this->assertSame(
            ['2/2030', '0/2030'],
            array_map(static fn ($periodo): string => $periodo->codigo, $periodos->vigentes()),
        );

        $principal = $periodos->principal();

        $this->assertNotNull($principal);
        $this->assertSame('2/2030', $principal->codigo);
        $this->assertSame('SEMESTRE_2', $principal->tipo);
        $this->assertSame('Semestre 2', $principal->tipoEtiqueta);
        $this->assertSame(now()->subDays(10)->toDateString(), $principal->fechaInicio);
        $this->assertSame(['primeros_parciales' => ['2030-10-12', '2030-10-31']], $principal->ventanas);
    }

    public function test_there_is_no_main_period_when_today_is_outside_all_of_them(): void
    {
        $this->periodo('1/2030', 1, -200, -20);
        $this->periodo('0/2030', 0, null, null);

        $periodos = $this->app->make(PeriodoGateway::class);

        $this->assertSame([], $periodos->vigentes());
        $this->assertNull($periodos->principal());
    }

    private function periodo(string $codigo, int $numero, ?int $desde, ?int $hasta): Periodo
    {
        $tipo = match ($numero) {
            0 => TipoPeriodo::Anual,
            1 => TipoPeriodo::Semestre1,
            4 => TipoPeriodo::Invierno,
            default => TipoPeriodo::Semestre2,
        };

        return Periodo::create([
            'codigo' => $codigo,
            'anio' => (int) substr($codigo, 2),
            'numero' => $numero,
            'tipo' => $tipo,
            'fecha_inicio' => $desde === null ? null : now()->addDays($desde)->toDateString(),
            'fecha_fin' => $hasta === null ? null : now()->addDays($hasta)->toDateString(),
            'ventanas' => ['primeros_parciales' => ['2030-10-12', '2030-10-31']],
        ]);
    }
}
