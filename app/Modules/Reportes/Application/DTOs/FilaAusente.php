<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

/**
 * Un habilitado que no ingreso al examen.
 */
final readonly class FilaAusente
{
    public function __construct(
        public string $codigoUniversitario,
        public string $nombre,
        public ?string $grupo,
        public ?string $carrera,
        public ?string $aula,
    ) {}
}
