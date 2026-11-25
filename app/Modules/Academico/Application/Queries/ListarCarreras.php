<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Queries;

use App\Modules\Academico\Application\Contracts\ConsultaOfertaGateway;

/**
 * Las carreras de una facultad (o todas), por nombre; opcionalmente solo las
 * que tienen oferta en un período.
 */
final readonly class ListarCarreras
{
    public function __construct(
        private ConsultaOfertaGateway $oferta,
        private ResolverPeriodos $resolverPeriodos,
    ) {}

    /**
     * @return list<array{id: int, codigo: string, nombre: string, regimen: string}>
     */
    public function execute(?string $claveFacultad, bool $conOferta = false, ?int $periodoId = null): array
    {
        // Sin pedir oferta, todas (el Padrón las usa así). Con oferta, las del
        // período pedido o, si no se pide ninguno, las de los vigentes.
        $periodos = $conOferta || $periodoId !== null
            ? $this->resolverPeriodos->execute($periodoId)
            : null;

        return $this->oferta->carreras($claveFacultad, $periodos);
    }
}
