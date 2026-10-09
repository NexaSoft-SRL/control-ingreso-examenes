<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\DTOs;

/**
 * Una fila que no coincide con el estudiante guardado: su inscripcion
 * queda en espera hasta que la administracion resuelva.
 */
final readonly class ConflictoCargaData
{
    public function __construct(
        public int $fila,
        public string $codigo,
        public string $motivo,
    ) {}
}
