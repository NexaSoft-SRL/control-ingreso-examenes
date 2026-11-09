<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Actions;

use App\Modules\Ingreso\Application\Authorization\AlcancePuesto;
use App\Modules\Ingreso\Application\Contracts\CodigoQrGateway;
use App\Modules\Ingreso\Application\Contracts\CorreoQrGateway;
use App\Modules\Ingreso\Application\Contracts\PuertaGateway;
use App\Modules\Ingreso\Application\DTOs\FiltroCodigosData;
use App\Modules\Ingreso\Domain\Exceptions\SinCodigosException;

/**
 * Encola un correo con el codigo QR por cada estudiante del filtro que ya
 * lo tiene emitido.
 *
 * Sin tope (cola real) van todos de una vez. Con tope por peticion (cola
 * sincronica) hay dos casos: mientras quede alguien del filtro sin
 * recibirlo, la tanda es solo de esos; cuando ya lo recibieron todos, es
 * un reenvio a todos, que avanza por tandas sin repetir a nadie gracias a
 * `tanda` (el instante en que empezo, que el cliente devuelve en cada
 * vuelta).
 */
final readonly class EnviarCodigosQr
{
    public function __construct(
        private CodigoQrGateway $codigos,
        private CorreoQrGateway $correo,
        private PuertaGateway $puerta,
        private AlcancePuesto $alcance,
    ) {}

    /**
     * @return array{encolados: int, pendientes: int, tanda: string}
     */
    public function execute(int $examenId, FiltroCodigosData $filtro, ?int $usuarioId, ?string $tanda = null): array
    {
        $this->alcance->exigirDocente($usuarioId, $examenId);

        $limite = $this->correo->tamanoDeLote();
        $continua = $tanda !== null;
        $tanda ??= $this->puerta->horaServidor();

        if ($limite === null) {
            $credenciales = $this->codigos->emitidas($examenId, $filtro);
            $pendientes = 0;
        } elseif (($sinEnviar = $this->codigos->sinEnviar($examenId, $filtro)) > 0) {
            $continua = false;
            $credenciales = $this->codigos->emitidas($examenId, $filtro, $limite, true);
            $pendientes = max(0, $sinEnviar - count($credenciales));
        } else {
            $credenciales = $this->codigos->emitidas($examenId, $filtro, $limite, false, $tanda);
            $pendientes = max(0, $this->codigos->porReenviar($examenId, $filtro, $tanda) - count($credenciales));
        }

        if ($credenciales === []) {
            // La tanda ya habia terminado: no queda a quien reenviar.
            if ($continua) {
                return ['encolados' => 0, 'pendientes' => 0, 'tanda' => $tanda];
            }

            throw SinCodigosException::sinEmitir();
        }

        return [
            'encolados' => $this->correo->encolar($examenId, $credenciales, $usuarioId),
            'pendientes' => $pendientes,
            'tanda' => $tanda,
        ];
    }
}
