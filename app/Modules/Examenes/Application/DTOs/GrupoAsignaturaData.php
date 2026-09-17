<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

final readonly class GrupoAsignaturaData
{
    public function __construct(
        public string $codigoGrupo,
        public int $docenteId,
        public int $cupo,
    ) {}
}
