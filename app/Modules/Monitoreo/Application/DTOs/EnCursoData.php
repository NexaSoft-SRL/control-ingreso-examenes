<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\DTOs;

/**
 * Lo que muestra «En curso» de un examen.
 */
final readonly class EnCursoData
{
    /**
     * @param  list<AulaEnCursoData>  $aulas
     */
    public function __construct(
        public ExamenSeguidoData $examen,
        public CifrasEnCursoData $cifras,
        public array $aulas,
    ) {}
}
