<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\DTOs;

/**
 * Las fechas (`AAAA-MM-DD`, ambas incluidas) en que los estudiantes suben
 * su foto.
 */
final readonly class PlazoFotoData
{
    public function __construct(
        public string $desde,
        public string $hasta,
    ) {}

    public function abiertoEl(string $dia): bool
    {
        return $this->desde <= $dia && $dia <= $this->hasta;
    }

    /**
     * `proximo` si todavia no empieza, `abierto` o `cerrado` si ya paso.
     */
    public function estadoEl(string $dia): string
    {
        return match (true) {
            $dia < $this->desde => 'proximo',
            $dia > $this->hasta => 'cerrado',
            default => 'abierto',
        };
    }
}
