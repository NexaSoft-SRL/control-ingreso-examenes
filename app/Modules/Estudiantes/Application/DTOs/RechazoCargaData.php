<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\DTOs;

/**
 * Una fila del archivo que no se pudo cargar, con su numero y el motivo.
 */
final readonly class RechazoCargaData
{
    public function __construct(
        public int $fila,
        public string $motivo,
    ) {}
}
