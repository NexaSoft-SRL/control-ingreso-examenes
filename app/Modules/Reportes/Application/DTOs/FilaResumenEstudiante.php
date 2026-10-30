<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

/**
 * Lo que un estudiante tuvo en los examenes del alcance, en todas sus
 * materias.
 */
final readonly class FilaResumenEstudiante
{
    /**
     * @param  int  $examenes  examenes del alcance que le tocaban o a los que ingreso
     * @param  int  $intentos  rechazos de la puerta
     * @param  int  $incidencias  lo que reporto una persona, sin las expulsiones
     */
    public function __construct(
        public string $codigoUniversitario,
        public string $nombre,
        public ?string $carrera,
        public string $materias,
        public int $examenes,
        public int $ingresos,
        public int $intentos,
        public int $expulsiones,
        public int $incidencias,
    ) {}
}
