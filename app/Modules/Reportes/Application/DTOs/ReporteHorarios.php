<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

/**
 * Como se repartieron los ingresos en tramos de quince minutos.
 */
final readonly class ReporteHorarios
{
    /**
     * @param  list<array{tramo: string, cantidad: int}>  $tramos
     */
    public function __construct(
        public array $tramos,
        public ?string $primerIngreso,
        public ?string $ultimoIngreso,
        public int $total,
    ) {}
}
