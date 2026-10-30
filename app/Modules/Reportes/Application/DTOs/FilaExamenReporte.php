<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

/**
 * Un examen en el listado de reportes de la universidad.
 */
final readonly class FilaExamenReporte
{
    public function __construct(
        public int $id,
        public string $asignatura,
        public string $tipo,
        public string $docente,
        public ?string $facultad,
        public string $fecha,
        public string $hora,
        public int $aulas,
        public int $ingresados,
        public int $habilitados,
    ) {}
}
