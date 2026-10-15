<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

interface AlcancePuntoControl
{
    public function puedeOperar(
        int $usuarioId,
        int $examenId,
        int $aulaId,
    ): bool;
}
