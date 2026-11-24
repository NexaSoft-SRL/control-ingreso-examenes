<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Los estudiantes de los grupos elegidos que ya tienen otro examen a esa
 * hora. `total` cuenta a todos; `estudiantes` trae los primeros, para no
 * mandar listas enormes.
 */
final readonly class ChoquesDeEstudiantesData
{
    /**
     * @param  list<array{nombre: string, codigo: string, examenes: list<array{examen: string, inicio: string, fin: string}>}>  $estudiantes
     */
    public function __construct(
        public int $total,
        public array $estudiantes,
    ) {}
}
