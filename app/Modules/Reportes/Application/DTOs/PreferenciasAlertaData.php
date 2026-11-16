<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

final readonly class PreferenciasAlertaData
{
    public function __construct(
        public int $asistenciaMinima,
        public int $incidenciasMinimas,
    ) {}
}
