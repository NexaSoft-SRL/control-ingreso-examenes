<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

/**
 * Donde rinde el estudiante: el aula, su edificio y su piso.
 */
final readonly class AulaData
{
    public function __construct(
        public int $id,
        public string $nombre,
        public ?string $edificio,
        public ?string $piso,
        public ?int $edificioId,
    ) {}

    /**
     * «Edificio Académico 2 · 1° Piso»; nulo si el aula esta sin ubicar.
     */
    public function ubicacion(): ?string
    {
        $partes = array_filter(
            [$this->edificio, $this->piso],
            static fn (?string $parte): bool => $parte !== null && $parte !== '',
        );

        return $partes === [] ? null : implode(' · ', $partes);
    }
}
