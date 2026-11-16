<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

final readonly class FilaComparacion
{
    public function __construct(
        public int $id,
        public int $periodoId,
        public string $periodo,
        public string $asignatura,
        public string $tipo,
        public string $fecha,
        public int $presentes,
        public int $habilitados,
        public int $ausentes,
        public int $incidencias,
        public bool $rendido,
    ) {}

    public function asistencia(): int
    {
        return $this->habilitados === 0
            ? 0
            : (int) round($this->presentes * 100 / $this->habilitados);
    }
}
