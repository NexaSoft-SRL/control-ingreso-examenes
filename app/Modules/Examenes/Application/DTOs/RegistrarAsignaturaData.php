<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

final readonly class RegistrarAsignaturaData
{
    /**
     * @param  list<GrupoAsignaturaData>  $grupos
     */
    public function __construct(
        public string $codigo,
        public string $nombre,
        public ?string $semestre,
        public ?string $descripcion,
        public array $grupos,
    ) {}
}
