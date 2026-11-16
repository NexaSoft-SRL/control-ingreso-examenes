<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Contracts;

use App\Modules\Reportes\Application\DTOs\AlertaReporteData;
use App\Modules\Reportes\Application\DTOs\PreferenciasAlertaData;

interface AlertaReporteGateway
{
    public function preferencias(int $usuarioId): PreferenciasAlertaData;

    public function guardarPreferencias(
        int $usuarioId,
        int $asistenciaMinima,
        int $incidenciasMinimas,
    ): PreferenciasAlertaData;

    /**
     * @return list<AlertaReporteData>
     */
    public function alertas(int $usuarioId): array;

    public function marcarLeida(int $usuarioId, int $alertaId): bool;

    public function procesarCierres(): int;
}
