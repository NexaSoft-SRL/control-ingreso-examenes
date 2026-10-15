<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

use App\Modules\Estudiantes\Application\DTOs\EstudianteData;
use App\Modules\Habilitacion\Application\DTOs\HabilitacionData;

final readonly class CandidatoIngresoData
{
    public function __construct(
        public EstudianteData $estudiante,
        public bool $inscrito,
        public ?HabilitacionData $habilitacion,
    ) {}
}
