<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

/**
 * Un ingreso del examen, para el reporte de asistencia.
 */
final readonly class FilaAsistencia
{
    public function __construct(
        public string $codigoUniversitario,
        public string $nombre,
        public ?string $grupo,
        public ?string $aula,
        public string $hora,
        public string $medio,
        public bool $expulsado,
        public bool $excepcion,
    ) {}
}
