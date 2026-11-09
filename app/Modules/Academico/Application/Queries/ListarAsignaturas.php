<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Queries;

use App\Modules\Academico\Application\Contracts\ConsultaOfertaGateway;
use App\Modules\Academico\Application\DTOs\FiltroAsignaturasData;
use App\Modules\Academico\Application\DTOs\PaginaData;

/**
 * Las asignaturas con grupos en la oferta, con sus cifras.
 */
final readonly class ListarAsignaturas
{
    public function __construct(
        private ConsultaOfertaGateway $oferta,
        private ResolverPeriodos $resolverPeriodos,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function execute(FiltroAsignaturasData $filtro, PaginaData $pagina): array
    {
        $asignaturas = $this->oferta->asignaturas(
            $filtro,
            $this->resolverPeriodos->execute($filtro->periodoId),
            $pagina,
        );

        return [
            'data' => $asignaturas['filas'],
            'meta' => [
                ...$pagina->meta($asignaturas['total']),
                'grupos' => $asignaturas['grupos'],
                'niveles' => $asignaturas['niveles'],
            ],
        ];
    }
}
