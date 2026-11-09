<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

/**
 * Un ingreso ya asentado. `hora` va como `HH:MM:SS`; `expulsadoA`, como
 * `HH:MM`, solo si hay una expulsion sobre el.
 */
final readonly class IngresoData
{
    public function __construct(
        public int $id,
        public int $examenId,
        public int $estudianteId,
        public int $aulaId,
        public string $aula,
        public string $hora,
        public ?string $expulsadoA = null,
    ) {}

    /**
     * La hora como se muestra: `HH:MM`.
     */
    public function horaCorta(): string
    {
        return substr($this->hora, 0, 5);
    }
}
