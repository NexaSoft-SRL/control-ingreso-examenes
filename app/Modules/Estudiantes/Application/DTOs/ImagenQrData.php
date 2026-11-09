<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\DTOs;

/**
 * Los bytes de un PNG con el codigo QR y el nombre con que se descarga
 * (`qr-<codigo-sis>-<fecha>.png`).
 */
final readonly class ImagenQrData
{
    public function __construct(
        public string $nombre,
        public string $png,
    ) {}
}
