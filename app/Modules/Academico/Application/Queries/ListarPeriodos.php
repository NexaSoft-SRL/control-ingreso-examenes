<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Queries;

use App\Modules\Academico\Application\Contracts\ConsultaOfertaGateway;
use App\Modules\Academico\Application\DTOs\PaginaData;

/**
 * Los periodos detectados, con los vigentes primero.
 */
final readonly class ListarPeriodos
{
    public function __construct(
        private ConsultaOfertaGateway $oferta,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{total: int, pagina: int, por_pagina: int}}
     */
    public function execute(PaginaData $pagina): array
    {
        $periodos = $this->oferta->periodos($pagina);

        return [
            'data' => $periodos['filas'],
            'meta' => $pagina->meta($periodos['total']),
        ];
    }
}
