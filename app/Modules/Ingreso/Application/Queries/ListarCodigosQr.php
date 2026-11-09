<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Queries;

use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
use App\Modules\Ingreso\Application\DTOs\FiltroCodigosData;

/**
 * Los habilitados del examen con el estado de su codigo QR.
 */
final readonly class ListarCodigosQr
{
    public function __construct(
        private CodigoQrGateway $codigos,
        private AlcancePuesto $alcance,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function execute(int $examenId, FiltroCodigosData $filtro, ?int $usuarioId): array
    {
        $this->alcance->exigirDocente($usuarioId, $examenId);

        $pagina = $this->codigos->listar($examenId, $filtro);
        $resumen = $this->codigos->resumen($examenId);
        $opciones = $this->codigos->opciones($examenId);

        $estado = match (true) {
            $resumen['habilitados'] === 0,
            $resumen['emitidos'] < $resumen['habilitados'] => 'sin_emitir',
            $resumen['enviados'] < $resumen['habilitados'] => 'emitido',
            default => 'enviado',
        };

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
                'estado' => $estado,
                'grupos' => $opciones['grupos'],
                'aulas' => $opciones['aulas'],
            ],
        ];
    }
}
