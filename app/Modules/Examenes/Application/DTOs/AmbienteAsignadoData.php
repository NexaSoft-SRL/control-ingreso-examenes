<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

final readonly class AmbienteAsignadoData
{
    public function __construct(
        public int $id,
        public int $ambienteId,
        public string $nombre,
        public ?string $ubicacion,
        public int $capacidad,
        public string $estado,
    ) {}
}
