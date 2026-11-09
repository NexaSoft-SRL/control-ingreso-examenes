<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

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
     * «Edificio Academico 2 · 1° Piso»; nulo si el aula esta sin ubicar.
     */
    public function ubicacion(): ?string
    {
        $partes = array_filter(
            [$this->edificio, $this->piso],
            static fn (?string $parte): bool => $parte !== null && $parte !== '',
        );

        return $partes === [] ? null : implode(' · ', $partes);
    }

    /**
     * @return array{id: int, nombre: string, edificio: string|null, piso: string|null, edificio_id: int|null}
     */
    public function comoArreglo(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'edificio' => $this->edificio,
            'piso' => $this->piso,
            'edificio_id' => $this->edificioId,
        ];
    }
}
