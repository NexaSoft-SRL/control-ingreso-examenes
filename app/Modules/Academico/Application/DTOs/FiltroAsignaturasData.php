<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\DTOs;

/**
 * Filtros del listado de asignaturas de la oferta. `facultad` es la clave
 * (`fcyt`); `periodoId` nulo significa «los periodos vigentes».
 */
final readonly class FiltroAsignaturasData
{
    public function __construct(
        public ?int $periodoId,
        public ?string $facultad,
        public ?int $carreraId,
        public ?string $nivel,
        public ?string $buscar,
        // Solo las que tienen algun grupo sin lista de inscritos.
        public bool $sinLista = false,
    ) {}
}
