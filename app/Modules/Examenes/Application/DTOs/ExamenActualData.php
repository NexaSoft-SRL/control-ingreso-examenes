<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Lo que el examen tiene guardado hoy, para saber que cambia al
 * modificarlo.
 */
final readonly class ExamenActualData
{
    /**
     * @param  list<int>  $grupos
     * @param  list<int>  $aulas
     */
    public function __construct(
        public int $id,
        public int $periodoId,
        public int $asignaturaId,
        public string $tipo,
        public string $fecha,
        public string $horaInicio,
        public int $duracionMinutos,
        public array $grupos,
        public array $aulas,
    ) {}
}
