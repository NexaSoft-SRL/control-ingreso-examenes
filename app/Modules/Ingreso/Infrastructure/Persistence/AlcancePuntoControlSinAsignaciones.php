<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Infrastructure\Persistence;

use App\Modules\Ingreso\Application\Contracts\AlcancePuntoControl;

/**
 * Politica cerrada hasta integrar las asignaciones de operadores.
 * Tener el permiso general no demuestra autorizacion para un aula.
 */
class AlcancePuntoControlSinAsignaciones implements AlcancePuntoControl
{
    public function puedeOperar(
        int $usuarioId,
        int $examenId,
        int $aulaId,
    ): bool {
        return false;
    }

    public function aulasOperables(
        int $usuarioId,
        int $examenId,
    ): array {
        return [];
    }
}
