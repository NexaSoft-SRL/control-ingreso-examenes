<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

interface EstadoExpulsionGateway
{
    public function estaExpulsado(
        int $examenId,
        int $estudianteId,
    ): bool;
}
