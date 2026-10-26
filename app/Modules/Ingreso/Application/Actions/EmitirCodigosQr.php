<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Authorization\AlcanceCodigosQr;
use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
use App\Modules\Ingreso\Application\Contracts\EmisionQrGateway;
use App\Modules\Ingreso\Domain\Exceptions\ExamenAjenoException;
use App\Modules\Ingreso\Domain\Exceptions\SinCodigosException;

/**
 * Emite un codigo QR a cada habilitado que no lo tenga, cuando todos
 * tienen aula. El contenido sale de `CodigoQrGateway`, el formato que lee
 * la puerta (HU-19). Repetirla no cambia los codigos ya emitidos.
 */
final readonly class EmitirCodigosQr
{
    public function __construct(
        private EmisionQrGateway $codigos,
        private CodigoQrGateway $qr,
        private AlcanceCodigosQr $alcance,
    ) {}

    /**
     * @return array{emitidos: int, total: int}
     *
     * @throws ExamenAjenoException
     * @throws SinCodigosException
     */
    public function execute(int $examenId, int $usuarioId): array
    {
        $this->alcance->exigirDocente($usuarioId, $examenId);

        $resumen = $this->codigos->resumen($examenId);

        if ($resumen['habilitados'] === 0) {
            throw SinCodigosException::sinHabilitados();
        }

        // La credencial muestra el aula y la puerta la usa.
        if ($resumen['sin_aula'] > 0) {
            throw SinCodigosException::sinAula($resumen['sin_aula']);
        }

        $contenidos = [];

        foreach ($this->codigos->pendientesDeEmitir($examenId) as $pendiente) {
            $contenidos[$pendiente['estudiante_id']] = $this->qr->generar($examenId, $pendiente['codigo']);
        }

        $emitidos = $contenidos === []
            ? 0
            : $this->codigos->guardarLote($examenId, $contenidos, $usuarioId);

        return [
            'emitidos' => $emitidos,
            'total' => $this->codigos->resumen($examenId)['emitidos'],
        ];
    }
}
