<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\Queries;

use App\Modules\Reportes\Application\Contracts\ExportadorReporte;
use App\Modules\Reportes\Application\DTOs\ArchivoReporte;
use App\Modules\Reportes\Application\DTOs\TablaReporte;

/**
 * Convierte la tabla de un reporte en el archivo que se descarga. El
 * nombre sale de la clave de la tabla.
 */
final readonly class ExportarTabla
{
    public const PDF = 'pdf';

    public const XLSX = 'xlsx';

    public function __construct(
        private ExportadorReporte $exportador,
    ) {}

    public function execute(TablaReporte $tabla, string $formato): ArchivoReporte
    {
        if ($formato === self::XLSX) {
            return new ArchivoReporte(
                nombre: $tabla->clave.'.xlsx',
                tipo: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                contenido: $this->exportador->hojaDeCalculo($tabla),
            );
        }

        return new ArchivoReporte(
            nombre: $tabla->clave.'.pdf',
            tipo: 'application/pdf',
            contenido: $this->exportador->pdf($tabla),
        );
    }
}
