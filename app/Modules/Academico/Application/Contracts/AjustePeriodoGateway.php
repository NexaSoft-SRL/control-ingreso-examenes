<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Contracts;

/**
 * Ajuste a mano de las fechas de un periodo. Lo que se ajusta a mano no
 * lo vuelve a pisar la deteccion.
 */
interface AjustePeriodoGateway
{
    /**
     * Guarda las fechas, marca quien las ajusto y asienta
     * `periodo.ajustar` en la misma transaccion. Devuelve false si el
     * periodo no existe.
     */
    public function ajustar(int $periodoId, string $fechaInicio, string $fechaFin, ?int $autorId): bool;
}
