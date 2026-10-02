<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AmbienteExamenGateway;
use App\Modules\Examenes\Application\DTOs\AmbienteAsignadoData;
use App\Modules\Examenes\Domain\Exceptions\AmbienteNoDisponibleException;

/**
 * Asigna un ambiente a un examen (HU-10). Un ambiente en mantenimiento o
 * inactivo no se asigna, y tampoco uno que ya tenga otro examen solapado.
 */
final readonly class AsignarAmbienteAExamen
{
    public function __construct(
        private AmbienteExamenGateway $gateway,
    ) {}

    public function execute(
        int $examenId,
        int $ambienteId,
        ?int $usuarioId,
    ): AmbienteAsignadoData {
        if (! $this->gateway->estaDisponible($examenId, $ambienteId)) {
            throw new AmbienteNoDisponibleException(
                'El ambiente no está disponible para ese examen.'
            );
        }

        return $this->gateway->asignar($examenId, $ambienteId, $usuarioId);
    }
}
