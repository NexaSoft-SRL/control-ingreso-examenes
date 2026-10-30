<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Contracts;

use App\Modules\Reportes\Application\DTOs\ArchivoReporte;

/**
 * @phpstan-import-type ExamenReporte from ConsultaReportesGateway
 */
interface GeneradorArchivoReporte
{
    /**
     * @param  ExamenReporte  $reporte
     */
    public function generar(array $reporte, string $tipo, string $formato): ArchivoReporte;
}
