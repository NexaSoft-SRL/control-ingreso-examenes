<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\DTOs;

/**
 * Lo que una carga y la descarga de la lista necesitan saber del grupo.
 */
final readonly class GrupoDeCargaData
{
    public function __construct(
        public int $id,
        public int $periodoId,
        public int $facultadId,
        public bool $periodoVigente,
        public string $codigo = '',
        public string $asignaturaCodigo = '',
    ) {}
}
