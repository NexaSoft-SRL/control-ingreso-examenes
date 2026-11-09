<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Modules\Ingreso\Domain\Enums\Gravedad;
use App\Modules\Ingreso\Domain\Enums\TipoIncidencia;
use App\Modules\Reportes\Domain\Enums\GravedadIncidencia;
use Tests\TestCase;

/**
 * Los reportes calculan la gravedad de cada incidencia por su cuenta (no
 * importan el dominio de Ingreso): tiene que ser la misma que muestran las
 * listas de incidencias.
 */
final class GravedadIncidenciaTest extends TestCase
{
    public function test_each_type_has_the_same_severity_in_the_reports_and_in_the_lists(): void
    {
        foreach (TipoIncidencia::cases() as $tipo) {
            $this->assertSame(
                [$tipo->gravedad()->value, $tipo->gravedad()->etiqueta()],
                [GravedadIncidencia::deTipo($tipo->value)->value, GravedadIncidencia::deTipo($tipo->value)->etiqueta()],
                $tipo->value,
            );
        }
    }

    public function test_the_three_levels_of_the_consultant(): void
    {
        $this->assertSame(['MUY_GRAVE', 'GRAVE', 'LEVE'], Gravedad::valores());
        $this->assertSame(
            ['SUPLANTACION', 'EXPULSION'],
            array_values(array_map(
                static fn (TipoIncidencia $tipo): string => $tipo->value,
                array_filter(TipoIncidencia::cases(), static fn (TipoIncidencia $tipo): bool => $tipo->gravedad() === Gravedad::MuyGrave),
            )),
        );
        $this->assertSame(Gravedad::Leve, TipoIncidencia::CambioAula->gravedad());
        $this->assertSame(Gravedad::Leve, TipoIncidencia::Otra->gravedad());
    }
}
