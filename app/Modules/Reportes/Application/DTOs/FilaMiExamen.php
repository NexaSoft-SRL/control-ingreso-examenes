<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

/**
 * Un examen del docente con sus cifras de asistencia y lo que quedo
 * asentado en el (`incidencias`: las filas de su reporte de incidencias). `rendido`: la fecha
 * del examen ya llego.
 */
final readonly class FilaMiExamen
{
    public function __construct(
        public int $id,
        public int $asignaturaId,
        public string $asignatura,
        public string $tipo,
        public string $fecha,
        public string $hora,
        public int $presentes,
        public int $habilitados,
        public int $noHabilitados,
        public int $ausentes,
        public int $incidencias,
        public bool $rendido,
    ) {}

    /**
     * Porcentaje de habilitados que se presentaron.
     */
    public function asistencia(): int
    {
        return $this->habilitados === 0
            ? 0
            : (int) round($this->presentes * 100 / $this->habilitados);
    }
}
