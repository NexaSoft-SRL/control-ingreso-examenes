<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\DTOs;

/**
 * Un edificio de `locations.json` con sus aulas.
 */
final readonly class EdificioData
{
    /**
     * @param  string  $id  el de la fuente (`blk_0_50366`)
     * @param  list<array{0: float, 1: float}>  $poligono  `[longitud, latitud]`, anillo cerrado
     * @param  list<array{nombre: string, piso: string|null}>  $aulas
     */
    public function __construct(
        public string $id,
        public string $nombre,
        public array $poligono,
        public array $aulas,
    ) {}
}
