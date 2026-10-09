<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\DTOs;

/**
 * Resultado de detectar los periodos: cuantos se crearon, cuantos ya
 * existian y se revisaron, y como quedaron todos.
 */
final readonly class DeteccionPeriodosData
{
    /**
     * @param  list<PeriodoData>  $periodos
     */
    public function __construct(
        public int $creados,
        public int $actualizados,
        public array $periodos,
    ) {}
}
