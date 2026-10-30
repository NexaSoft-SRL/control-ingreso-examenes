<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

/**
 * Un inscrito del grupo, para la lista del docente.
 */
final readonly class FilaEstudianteGrupo
{
    public function __construct(
        public string $codigoUniversitario,
        public string $nombre,
        public ?string $documento,
        public bool $verificado,
    ) {}
}
