<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

/**
 * Lo que un reporte necesita saber del examen elegido.
 */
final readonly class ExamenReporteData
{
    public function __construct(
        public int $id,
        public string $asignatura,
        public string $tipo,
        public string $fecha,
        public string $hora,
    ) {}
}
