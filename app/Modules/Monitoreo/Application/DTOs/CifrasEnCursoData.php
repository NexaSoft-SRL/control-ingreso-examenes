<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\DTOs;

/**
 * Las cifras del examen entero.
 */
final readonly class CifrasEnCursoData
{
    /**
     * @param  list<array{hora: string, ingresos: int}>  $llegadas  ingresos
     *                                                              por tramo de cinco minutos, del primero al ultimo
     */
    public function __construct(
        public int $ingresaron,
        public int $faltan,
        public int $aulas,
        public int $incidenciasSinAtender,
        public ?string $ultimoIngreso = null,
        public array $llegadas = [],
    ) {}
}
