<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Queries;

use App\Modules\Academico\Application\Contracts\ConsultaOfertaGateway;

/**
 * Las facultades que tienen oferta en un período (por omisión, en los
 * vigentes): con el período anual, solo las que tienen carreras anuales.
 */
final readonly class ListarFacultadesConOferta
{
    public function __construct(
        private ConsultaOfertaGateway $oferta,
        private ResolverPeriodos $resolverPeriodos,
    ) {}

    /**
     * @return list<string> Las claves de facultad, en el orden del catálogo.
     */
    public function execute(?int $periodoId): array
    {
        return $this->oferta->facultadesConOferta($this->resolverPeriodos->execute($periodoId));
    }
}
