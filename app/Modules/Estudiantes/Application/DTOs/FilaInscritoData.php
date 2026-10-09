<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\DTOs;

use App\Modules\Estudiantes\Domain\Enums\TipoConflicto;

/**
 * Una fila valida del archivo, ya con el grupo al que inscribe.
 * `conflicto` solo viene en las filas que no coinciden con el padron.
 */
final readonly class FilaInscritoData
{
    public function __construct(
        public int $fila,
        public string $codigoUniversitario,
        public string $documentoIdentidad,
        public string $nombres,
        public string $apellidos,
        public int $grupoId,
        public int $facultadId,
        public ?int $carreraId = null,
        public ?TipoConflicto $conflicto = null,
    ) {}

    public function conConflicto(TipoConflicto $tipo): self
    {
        return new self(
            $this->fila,
            $this->codigoUniversitario,
            $this->documentoIdentidad,
            $this->nombres,
            $this->apellidos,
            $this->grupoId,
            $this->facultadId,
            $this->carreraId,
            $tipo,
        );
    }
}
