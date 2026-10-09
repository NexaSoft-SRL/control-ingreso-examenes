<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\DTOs;

/**
 * Filtros del listado de docentes. `facultad` es la clave (`fcyt`);
 * `periodoId` nulo significa «los periodos vigentes».
 */
final readonly class FiltroDocentesData
{
    public function __construct(
        public ?int $periodoId,
        public ?string $facultad,
        public bool $sinCuenta,
        public bool $variasFacultades,
        public ?string $buscar,
    ) {}
}
