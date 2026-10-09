<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Un grupo que rinde el examen o que se puede sumar a el.
 */
final readonly class GrupoDeExamenData
{
    public function __construct(
        public int $id,
        public string $codigo,
        public ?string $docente,
        public int $inscritos,
        public bool $propio,
        public int $periodoId,
        public string $periodoCodigo,
        public ?string $periodoInicio,
        public ?string $periodoFin,
    ) {}
}
