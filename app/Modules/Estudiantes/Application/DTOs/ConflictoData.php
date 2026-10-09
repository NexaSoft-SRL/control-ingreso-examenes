<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\DTOs;

/**
 * Un conflicto del padron al momento de resolverlo.
 */
final readonly class ConflictoData
{
    public function __construct(
        public int $id,
        public int $estudianteId,
        public string $codigoUniversitario,
        public ?string $documentoNuevo,
        public bool $resuelto,
    ) {}
}
