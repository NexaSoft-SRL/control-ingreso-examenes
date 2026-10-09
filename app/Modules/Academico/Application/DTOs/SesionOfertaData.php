<?php

declare(strict_types=1);

namespace App\Modules\Academico\Application\DTOs;

/**
 * Una sesion semanal valida de un grupo de la oferta.
 */
final readonly class SesionOfertaData
{
    public function __construct(
        public string $dia,
        public string $horaInicio,
        public string $horaFin,
        public ?string $aula,
        public bool $esAuxiliatura,
    ) {}
}
