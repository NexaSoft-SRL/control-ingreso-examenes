<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
use App\Modules\Ingreso\Domain\Exceptions\SinCodigosException;

/**
 * Emite un codigo QR a cada habilitado que no lo tenga, cuando todos
 * tienen aula. Repetirla no cambia los codigos ya emitidos.
 */
final readonly class EmitirCodigosQr
{
    public function __construct(
        private CodigoQrGateway $codigos,
        private AlcancePuesto $alcance,
    ) {}

    /**
     * @return array{emitidos: int, total: int}
     */
    public function execute(int $examenId, ?int $usuarioId): array
    {
        $this->alcance->exigirDocente($usuarioId, $examenId);

        $resumen = $this->codigos->resumen($examenId);

        if ($resumen['habilitados'] === 0) {
            throw SinCodigosException::sinHabilitados();
        }

        // Antes de emitir, todos los habilitados tienen aula: la credencial
        // la muestra y la puerta la usa.
        if ($resumen['sin_aula'] > 0) {
            throw SinCodigosException::sinAula($resumen['sin_aula']);
        }

        $emitidos = $this->codigos->emitirLote($examenId, $usuarioId);

        return [
            'emitidos' => $emitidos,
            'total' => $this->codigos->resumen($examenId)['emitidos'],
        ];
    }
}
