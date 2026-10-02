<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

final readonly class OcupacionExamenData
{
    public function __construct(
        public int $capacidadTotal,
        public int $asignados,
        public bool $aforoExcedido,
    ) {}
}