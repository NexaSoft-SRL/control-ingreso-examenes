<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\DTOs;

/**
 * Un estudiante de la lista. `hora` y `expulsado` solo tienen sentido en quienes ingresaron.
 */
final readonly class FilaAsistenciaData
{
    public function __construct(
        public string $codigo,
        public string $nombre,
        public ?string $grupo,
        public ?string $hora,
        public bool $expulsado,
    ) {}
}
