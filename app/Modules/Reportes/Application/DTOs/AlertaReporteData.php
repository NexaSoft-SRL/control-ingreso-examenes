<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

final readonly class AlertaReporteData
{
    public function __construct(
        public int $id,
        public int $examenId,
        public string $asignatura,
        public string $tipoExamen,
        public string $fecha,
        public string $tipo,
        public string $titulo,
        public string $mensaje,
        public string $correoEstado,
        public ?string $leidaEn,
        public string $creadaEn,
    ) {}
}
