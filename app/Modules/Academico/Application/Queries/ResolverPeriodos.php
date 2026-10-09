<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Queries;

use App\Modules\Academico\Application\Contracts\PeriodoGateway;

/**
 * Los periodos sobre los que trabaja un listado: el pedido o, si no se
 * pide ninguno, todos los vigentes. Una lista vacia significa «sin filtrar»
 * (hoy no cae en ningun periodo).
 */
final readonly class ResolverPeriodos
{
    public function __construct(
        private PeriodoGateway $periodos,
    ) {}

    /**
     * @return list<int>
     */
    public function execute(?int $periodoId): array
    {
        if ($periodoId !== null) {
            return [$periodoId];
        }

        $ids = [];

        foreach ($this->periodos->vigentes() as $periodo) {
            $ids[] = $periodo->id;
        }

        return $ids;
    }
}
