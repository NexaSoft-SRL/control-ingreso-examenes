<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Una norma marcada en un examen, con el texto con que se guardo.
 * `plantillaId` nulo = la plantilla de la que salio ya no existe.
 */
final readonly class NormaMarcadaData
{
    public function __construct(
        public int $id,
        public ?int $plantillaId,
        public string $texto,
    ) {}
}
