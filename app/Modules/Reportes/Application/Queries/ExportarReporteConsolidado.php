<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Queries;

use App\Modules\Reportes\Application\Contracts\ExportadorReporte;
use App\Modules\Reportes\Application\DTOs\ArchivoReporte;

final readonly class ExportarReporteConsolidado
{
    public function __construct(
        private ArmarReporteConsolidado $armar,
        private ExportadorReporte $exportador,
    ) {}

    /**
     * @param  list<int>  $examenIds
     */
    public function execute(array $examenIds, int $usuarioId, string $formato): ArchivoReporte
    {
        $reporte = $this->armar->execute($examenIds, $usuarioId);
        $contenido = $formato === ExportarTabla::XLSX
            ? $this->exportador->hojaConsolidada($reporte->hojas)
            : $this->exportador->pdf($reporte->resumen);
        $extension = $formato === ExportarTabla::XLSX ? 'xlsx' : 'pdf';
        $tipo = $formato === ExportarTabla::XLSX
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'application/pdf';

        return new ArchivoReporte(
            nombre: $reporte->resumen->clave.'.'.$extension,
            tipo: $tipo,
            contenido: $contenido,
        );
    }
}
