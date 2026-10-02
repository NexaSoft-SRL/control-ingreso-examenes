<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\Actions;

use App\Modules\Examenes\Application\Contracts\NormaExamenGateway;

final readonly class EliminarNormaExamen
{
    public function __construct(
        private NormaExamenGateway $gateway,
    ) {}

    public function execute(
        int $examenId,
        int $normaId,
        int $usuarioId,
    ): ?bool {
        return $this->gateway->eliminar($examenId, $normaId, $usuarioId);
    }
}
