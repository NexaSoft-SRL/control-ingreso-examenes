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

    /**
     * Cambia las fechas del calendario de examenes de un periodo: solo las
     * ventanas indicadas (clave => [desde, hasta]); las demas quedan como
     * estaban. Marca quien lo ajusto, de modo que la deteccion ya no lo
     * pisa, y asienta `periodo.calendario`. Devuelve false si el periodo no
     * existe.
     *
     * @param  array<string, array{0: string, 1: string}>  $ventanas
     */
    public function ajustarCalendario(int $periodoId, array $ventanas, ?int $autorId): bool;
}
