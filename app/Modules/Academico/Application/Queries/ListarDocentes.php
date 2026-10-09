<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Queries;

use App\Modules\Academico\Application\Contracts\ConsultaDocentesGateway;
use App\Modules\Academico\Application\DTOs\FiltroDocentesData;
use App\Modules\Academico\Application\DTOs\PaginaData;

/**
 * Los docentes de la oferta, una fila por docente aunque dicte en varias
 * facultades.
 */
final readonly class ListarDocentes
{
    public function __construct(
        private ConsultaDocentesGateway $docentes,
        private ResolverPeriodos $resolverPeriodos,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function execute(FiltroDocentesData $filtro, PaginaData $pagina): array
    {
        $periodoIds = $this->resolverPeriodos->execute($filtro->periodoId);
        $docentes = $this->docentes->listar($filtro, $periodoIds, $pagina);

        return [
            'data' => $docentes['filas'],
            'meta' => [
                ...$pagina->meta($docentes['total']),
                'conteos' => $this->docentes->conteos($periodoIds),
            ],
        ];
    }
}
