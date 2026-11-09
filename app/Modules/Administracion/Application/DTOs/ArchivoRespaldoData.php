<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Application\DTOs;

final readonly class ArchivoRespaldoData
{
    public function __construct(
        public string $nombre,
        public string $contenido,
    ) {}
}
