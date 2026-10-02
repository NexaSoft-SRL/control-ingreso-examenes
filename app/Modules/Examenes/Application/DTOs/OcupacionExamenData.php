<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

final readonly class OcupacionExamenData
{
    public function __construct(
        public int $capacidadAsignada,
        public int $habilitados,
    ) {}

    public function alcanza(): bool
    {
        return $this->capacidadAsignada >= $this->habilitados;
    }
}
