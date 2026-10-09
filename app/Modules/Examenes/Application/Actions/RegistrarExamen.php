<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\ConsultaExamenGateway;
use App\Modules\Examenes\Application\Contracts\ExamenGateway;
use App\Modules\Examenes\Application\DTOs\ExamenDetalleData;
use App\Modules\Examenes\Application\DTOs\GuardarExamenData;
use App\Modules\Examenes\Domain\Exceptions\DatoInvalidoException;

/**
 * Registra el examen de una vez, con sus grupos, sus aulas y sus normas.
 * Puede sumar grupos de otros docentes sin pedirles aprobacion:
 * queda en la bitacora.
 */
final readonly class RegistrarExamen
{
    public function __construct(
        private ComprobarDatosDeExamen $comprobar,
        private ExamenGateway $examenes,
        private ConsultaExamenGateway $consulta,
    ) {}

    /**
     * @throws DatoInvalidoException
     */
    public function execute(GuardarExamenData $datos, int $usuarioId): ?ExamenDetalleData
    {
        $examenId = $this->examenes->registrar(
            $this->comprobar->execute($datos, $usuarioId),
            $usuarioId,
        );

        return $this->consulta->detalle($examenId, $usuarioId);
    }
}
