<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\Contracts;

use App\Modules\Ingreso\Application\DTOs\CondicionData;

/**
 * La condicion de habilitacion de un estudiante, leida para la puerta.
 *
 * Es un contrato propio de Ingreso para no depender de un modulo que se
 * escribia a la vez: se puede sustituir por
 * `Habilitacion\Application\Contracts\HabilitacionGateway::condicionDe()`.
 */
interface CondicionHabilitacionGateway
{
    /**
     * Nulo = sin revisar (no hay fila).
     */
    public function condicionDe(int $examenId, int $estudianteId): ?CondicionData;
}
