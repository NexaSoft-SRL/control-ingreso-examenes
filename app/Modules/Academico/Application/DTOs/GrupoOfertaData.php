<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\DTOs;

/**
 * Un grupo como viene en el archivo: el docente sin interpretar (puede ser
 * un marcador de «por designar») y solo las sesiones que se pudieron leer.
 */
final readonly class GrupoOfertaData
{
    /**
     * @param  list<SesionOfertaData>  $sesiones
     */
    public function __construct(
        public string $codigo,
        public ?string $docente,
        public array $sesiones,
        public int $sesionesDescartadas,
    ) {}
}
