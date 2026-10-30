<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

/**
 * El grupo cuya lista se descarga.
 */
final readonly class GrupoReporteData
{
    public function __construct(
        public int $id,
        public string $codigo,
        public string $asignaturaCodigo,
        public string $asignatura,
        public string $periodo,
    ) {}
}
