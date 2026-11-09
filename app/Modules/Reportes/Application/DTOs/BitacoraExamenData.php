<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

/**
 * La bitacora de un examen: quien controlo cada aula y los sucesos en orden.
 */
final readonly class BitacoraExamenData
{
    /**
     * @param  list<ControlDeAula>  $aulas
     * @param  list<SucesoExamen>  $sucesos
     */
    public function __construct(
        public array $aulas,
        public array $sucesos,
    ) {}
}
