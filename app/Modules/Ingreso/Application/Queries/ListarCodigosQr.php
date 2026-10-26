<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Ingreso\Application\Authorization\AlcanceCodigosQr;
use App\Modules\Ingreso\Application\Contracts\EmisionQrGateway;
use App\Modules\Ingreso\Application\DTOs\FiltroCodigosData;
use App\Modules\Ingreso\Domain\Exceptions\ExamenAjenoException;

/**
 * Los habilitados del examen con el estado de su codigo QR y las cifras
 * del examen.
 */
final readonly class ListarCodigosQr
{
    public function __construct(
        private EmisionQrGateway $codigos,
        private AlcanceCodigosQr $alcance,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     *
     * @throws ExamenAjenoException
     */
    public function execute(int $examenId, FiltroCodigosData $filtro, int $usuarioId): array
    {
        $this->alcance->exigirDocente($usuarioId, $examenId);

        $pagina = $this->codigos->listar($examenId, $filtro);
        $resumen = $this->codigos->resumen($examenId);
        $opciones = $this->codigos->opciones($examenId);

        return [
            'data' => $pagina['data'],
            'meta' => [
                'total' => $pagina['total'],
                'pagina' => $filtro->pagina,
                'por_pagina' => $filtro->porPagina,
                'habilitados' => $resumen['habilitados'],
                'emitidos' => $resumen['emitidos'],
                'enviados' => $resumen['enviados'],
                'fallidos' => $resumen['fallidos'],
                'sin_aula' => $resumen['sin_aula'],
                'grupos' => $opciones['grupos'],
                'aulas' => $opciones['aulas'],
            ],
        ];
    }
}
