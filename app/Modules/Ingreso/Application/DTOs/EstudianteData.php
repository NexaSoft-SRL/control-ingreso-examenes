<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

final readonly class EstudianteData
{
    public function __construct(
        public int $id,
        public string $codigo,
        public string $nombre,
    ) {}

    /**
     * @return array{id: int, codigo: string, nombre: string}
     */
    public function comoArreglo(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
        ];
    }
}
