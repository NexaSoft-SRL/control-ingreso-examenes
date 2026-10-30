<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Application\DTOs;

final readonly class ArchivoReporte
{
    public function __construct(
        public string $nombre,
        public string $contenido,
        public string $tipoContenido,
    ) {}
}
