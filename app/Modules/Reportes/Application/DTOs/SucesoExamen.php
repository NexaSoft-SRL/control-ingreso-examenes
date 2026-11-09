<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

/**
 * Un suceso de la bitacora de un examen. `categoria`: ingreso, rechazo,
 * incidencia, acceso, examen o ausente (un habilitado que no se presento).
 * `instante` es `AAAA-MM-DD HH:MM:SS`, hora
 * del servidor. `estudiante` y `aula` faltan cuando el suceso no es de uno.
 * `gravedad` (`LEVE`, `GRAVE` o `MUY_GRAVE`) solo en rechazos e incidencias.
 */
final readonly class SucesoExamen
{
    public function __construct(
        public string $instante,
        public string $categoria,
        public string $titulo,
        public string $detalle,
        public ?string $aula,
        public ?string $estudiante,
        public ?string $codigo,
        public ?string $por,
        public ?string $gravedad = null,
    ) {}
}
