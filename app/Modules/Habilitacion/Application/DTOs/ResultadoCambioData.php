<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\DTOs;

final readonly class ResultadoCambioData
{
    public function __construct(
        public bool $habilitado,
        public int $afectados,
        public CifrasHabilitacionData $cifras,
    ) {}
}
