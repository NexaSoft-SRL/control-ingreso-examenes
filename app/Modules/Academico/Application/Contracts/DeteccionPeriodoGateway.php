<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\Contracts;

use App\Modules\Academico\Application\DTOs\CalendarioData;
use App\Modules\Academico\Application\DTOs\PeriodoData;
use App\Modules\Academico\Domain\Enums\TipoPeriodo;

/**
 * Escritura de los periodos que el sistema detecta en las fuentes.
 */
interface DeteccionPeriodoGateway
{
    /**
     * Crea el periodo o lo actualiza por su codigo. Pone fechas y ventanas
     * solo si el calendario las trae, y nunca pisa las de un periodo que
     * alguien ajusto a mano. Devuelve true si lo creo.
     */
    public function registrar(
        string $codigo,
        int $anio,
        int $numero,
        TipoPeriodo $tipo,
        ?CalendarioData $calendario,
    ): bool;

    /**
     * El id del periodo; si no existe lo crea sin fechas.
     */
    public function asegurar(string $codigo, int $anio, int $numero, TipoPeriodo $tipo): int;

    /**
     * Todos los periodos, del mas reciente al mas antiguo.
     *
     * @return list<PeriodoData>
     */
    public function todos(): array;
}
