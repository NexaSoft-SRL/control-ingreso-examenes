<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\AmbienteExamenGateway;

final readonly class QuitarAmbienteDeExamen
{
    public function __construct(
        private AmbienteExamenGateway $gateway,
    ) {}

    public function execute(
        int $examenId,
        int $ambienteId,
        ?int $usuarioId,
    ): bool {
        return $this->gateway->quitar($examenId, $ambienteId, $usuarioId);
    }
}
