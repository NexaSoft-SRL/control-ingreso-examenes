<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

use App\Modules\Ingreso\Domain\Enums\MedioIdentificacion;

final readonly class NuevoIngresoData
{
    public function __construct(
        public int $examenId,
        public int $estudianteId,
        public int $aulaId,
        public MedioIdentificacion $medio,
        public bool $esExcepcion,
        public OperadorData $operador,
        // Solo el ingreso que llega de una sesion sin conexion trae su hora.
        public ?string $registradoEn = null,
    ) {}
}
