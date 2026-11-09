<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

/**
 * Un reporte ya convertido en archivo, listo para descargarse.
 */
final readonly class ArchivoReporte
{
    public function __construct(
        public string $nombre,
        public string $tipo,
        public string $contenido,
    ) {}
}
