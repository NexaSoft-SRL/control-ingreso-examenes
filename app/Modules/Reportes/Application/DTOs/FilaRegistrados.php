<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

/**
 * Habilitados y registrados de un examen en un aula.
 */
final readonly class FilaRegistrados
{
    public function __construct(
        public int $examenId,
        public string $fecha,
        public string $examen,
        public ?string $facultad,
        public string $docente,
        public ?string $aula,
        public int $habilitados,
        public int $registrados,
    ) {}
}
