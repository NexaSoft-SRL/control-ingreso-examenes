<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
use App\Modules\Ingreso\Application\Contracts\CorreoQrGateway;
use App\Modules\Ingreso\Domain\Exceptions\SinCodigosException;

/**
 * Envia (o reenvia) el codigo QR de un solo estudiante, lo haya recibido
 * antes o no.
 */
final readonly class ReenviarCodigoQr
{
    public function __construct(
        private CodigoQrGateway $codigos,
        private CorreoQrGateway $correo,
        private AlcancePuesto $alcance,
    ) {}

    /**
     * El correo al que se envio; nulo si el estudiante no esta habilitado
     * en el examen.
     */
    public function execute(int $examenId, int $estudianteId, ?int $usuarioId): ?string
    {
        $this->alcance->exigirDocente($usuarioId, $examenId);

        $credencial = $this->codigos->credencial($examenId, $estudianteId);

        if ($credencial === null) {
            return null;
        }

        if ($credencial->token === null) {
            throw SinCodigosException::sinEmitir();
        }

        $this->correo->encolar($examenId, [$credencial], $usuarioId);

        return $credencial->correo;
    }
}
