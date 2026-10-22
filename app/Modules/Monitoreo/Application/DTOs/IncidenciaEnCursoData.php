<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\DTOs;

/**
 * Una incidencia reportada en un aula del examen. Los instantes van en
 * ISO 8601; `atendidaEn` nulo es una incidencia sin atender.
 */
final readonly class IncidenciaEnCursoData
{
    public function __construct(
        public int $id,
        public int $examenId,
        public int $aulaId,
        public string $aula,
        public string $tipo,
        public string $detalle,
        public ?string $estudiante,
        public string $reportante,
        public string $rolReportante,
        public string $reportadaEn,
        public ?string $atendidaPor,
        public ?string $atendidaEn,
    ) {}

    public function atendida(): bool
    {
        return $this->atendidaEn !== null;
    }
}
