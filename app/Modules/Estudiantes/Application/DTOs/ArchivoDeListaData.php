<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\DTOs;

/**
 * Un archivo listo para descargar.
 */
final readonly class ArchivoDeListaData
{
    public function __construct(
        public string $nombre,
        public string $contenido,
    ) {}
}
