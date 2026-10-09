<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Queries;

use App\Modules\Academico\Application\Contracts\ConsultaOfertaGateway;

/**
 * Las carreras de una facultad (o todas), por nombre.
 */
final readonly class ListarCarreras
{
    public function __construct(
        private ConsultaOfertaGateway $oferta,
    ) {}

    /**
     * @return list<array{id: int, codigo: string, nombre: string, regimen: string}>
     */
    public function execute(?string $claveFacultad): array
    {
        return $this->oferta->carreras($claveFacultad);
    }
}
