<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\DTOs;

/**
 * Una asignatura dentro de un nivel de una carrera de la oferta.
 */
final readonly class AsignaturaOfertaData
{
    /**
     * @param  list<GrupoOfertaData>  $grupos
     */
    public function __construct(
        public string $codigo,
        public string $nombre,
        public string $nivel,
        public array $grupos,
    ) {}
}
