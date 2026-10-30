<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

/**
 * Algo que quedo asentado en un examen ademas de los ingresos: un rechazo
 * de la puerta, una situacion excepcional, una expulsion o lo que reporto
 * una persona.
 */
final readonly class FilaIncidencia
{
    /**
     * @param  string  $origen  `SISTEMA` (la puerta rechazo) | `PERSONA`
     * @param  ?string  $causa  solo en las del sistema
     * @param  ?string  $registradoPor  la cuenta o, con codigo de aula, el nombre que dio
     */
    public function __construct(
        public string $hora,
        public ?string $aula,
        public ?string $codigoUniversitario,
        public ?string $estudiante,
        public string $tipo,
        public string $origen,
        public ?string $causa,
        public string $detalle,
        public ?string $registradoPor,
        public bool $conCodigoDeAula,
    ) {}
}
