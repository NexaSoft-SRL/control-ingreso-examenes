<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Los grupos de la asignatura: los del docente y los de los demas.
 */
final readonly class OpcionesDeGruposData
{
    /**
     * @param  list<GrupoDeExamenData>  $propios
     * @param  list<GrupoDeExamenData>  $otros
     */
    public function __construct(
        public array $propios,
        public array $otros,
    ) {}
}
