<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\DTOs;

/**
 * Las cifras del examen entero, sin filtros.
 */
final readonly class CifrasHabilitacionData
{
    public function __construct(
        public int $inscritos,
        public int $habilitados,
        public int $noHabilitados,
        public int $sinRevisar,
        /** Habilitados que todavia no tienen aula. */
        public int $sinAula,
    ) {}
}
