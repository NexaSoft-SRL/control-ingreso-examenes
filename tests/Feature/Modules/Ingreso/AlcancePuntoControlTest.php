<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ingreso;

use App\Modules\Ingreso\Application\Actions\RegistrarIngreso;
use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;
use App\Modules\Ingreso\Infrastructure\Persistence\AlcancePuntoControlSinAsignaciones;
use Tests\TestCase;

final class AlcancePuntoControlTest extends TestCase
{
    public function test_el_contenedor_resuelve_la_politica_cerrada(): void
    {
        $alcance = app(AlcancePuntoControl::class);

        self::assertInstanceOf(
            AlcancePuntoControlSinAsignaciones::class,
            $alcance,
        );

        self::assertFalse($alcance->puedeOperar(1, 1, 1));
    }

    public function test_deniega_todos_los_identificadores_sin_asignacion(): void
    {
        $alcance = app(AlcancePuntoControl::class);

        foreach ([[1, 1, 1], [2, 1, 1], [1, 2, 3]] as $datos) {
            self::assertFalse($alcance->puedeOperar(...$datos));
        }
    }

    public function test_el_caso_de_uso_se_resuelve_desde_laravel(): void
    {
        self::assertInstanceOf(
            RegistrarIngreso::class,
            app(RegistrarIngreso::class),
        );
    }
}
