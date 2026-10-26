<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

/**
 * Lo que el aviso de un examen dice ademas de la fecha y el aula: las
 * normas marcadas, las normas en texto libre y las recomendaciones que el
 * docente escribe al enviarlo.
 */
final readonly class AvisoData
{
    /**
     * @param  list<string>  $normasMarcadas
     */
    public function __construct(
        public array $normasMarcadas = [],
        public ?string $normas = null,
        public ?string $recomendaciones = null,
    ) {}
}
