<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

final readonly class ReporteConsolidadoData
{
    /**
     * @param  list<TablaReporte>  $hojas
     */
    public function __construct(
        public TablaReporte $resumen,
        public array $hojas,
    ) {}
}
