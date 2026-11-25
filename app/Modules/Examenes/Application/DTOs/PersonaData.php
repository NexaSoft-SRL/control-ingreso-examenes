<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Una cuenta que puede atender un aula. En el apoyo lleva ademas quien la
 * sumo (`agregadoPor`).
 */
final readonly class PersonaData
{
    public function __construct(
        public int $id,
        public string $nombre,
        public bool $esDocente,
        public bool $esAuxiliar,
        // Solo en el apoyo de un aula: quien sumo a esta persona.
        public ?int $agregadoPor = null,
        public ?string $agregadoPorNombre = null,
    ) {}
}
