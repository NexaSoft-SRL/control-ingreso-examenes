<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\DTOs;

/**
 * Una fila de la lista del examen: un inscrito con su condicion.
 */
final readonly class InscritoData
{
    public function __construct(
        public int $estudianteId,
        public string $codigo,
        /** «Apellidos, Nombres». */
        public string $nombre,
        public ?string $documento,
        /** Codigo del grupo por el que figura en el examen. */
        public string $grupo,
        /** `habilitado` | `no` | `pendiente`. */
        public string $estado,
        public ?string $aula,
        public ?string $motivo,
    ) {}
}
