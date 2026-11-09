<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\DTOs;

use App\Modules\Estudiantes\Domain\Enums\TipoConflicto;

/**
 * Un estudiante que el docente agrega suelto a la lista de su grupo, ya
 * contrastado con el padron. `estudianteId` null = no existe y se crea;
 * con `conflicto` sus datos difieren del padron y la inscripcion queda en
 * espera de la administracion, igual que en una carga.
 */
final readonly class AltaDeInscritoData
{
    public function __construct(
        public int $usuarioId,
        public int $grupoId,
        public int $facultadId,
        public string $codigoUniversitario,
        public string $documentoIdentidad,
        public string $nombres,
        public string $apellidos,
        public ?int $estudianteId = null,
        public ?TipoConflicto $conflicto = null,
    ) {}
}
