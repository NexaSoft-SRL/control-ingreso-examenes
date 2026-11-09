<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * El codigo de acceso de un aula del examen. `codigo` nulo = todavia no
 * se genero.
 */
final readonly class CodigoAulaData
{
    public function __construct(
        public ?string $codigo,
        public ?string $generadoEn,
        public string $encargado,
        public string $examen,
    ) {}
}
