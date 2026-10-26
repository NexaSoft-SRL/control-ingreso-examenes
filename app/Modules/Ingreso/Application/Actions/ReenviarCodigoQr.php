<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Authorization\AlcanceCodigosQr;
use App\Modules\Ingreso\Application\Contracts\CorreoQrGateway;
use App\Modules\Ingreso\Application\Contracts\EmisionQrGateway;
use App\Modules\Ingreso\Domain\Exceptions\ExamenAjenoException;
use App\Modules\Ingreso\Domain\Exceptions\SinCodigosException;

/**
 * Envia (o reenvia) el mismo codigo QR a un solo estudiante, lo haya
 * recibido antes o no.
 */
final readonly class ReenviarCodigoQr
{
    public function __construct(
        private EmisionQrGateway $codigos,
        private CorreoQrGateway $correo,
        private AlcanceCodigosQr $alcance,
    ) {}

    /**
     * El correo al que se envio; nulo si el estudiante no esta habilitado
     * en el examen.
     *
     * @throws ExamenAjenoException
     * @throws SinCodigosException
     */
    public function execute(int $examenId, int $estudianteId, int $usuarioId): ?string
    {
        $this->alcance->exigirDocente($usuarioId, $examenId);

        $credencial = $this->codigos->credencial($examenId, $estudianteId);

        if ($credencial === null) {
            return null;
        }

        if ($credencial->contenido === null) {
            throw SinCodigosException::sinEmitir();
        }

        $this->correo->encolar($examenId, [$credencial], $usuarioId);

        return $credencial->correo;
    }
}
