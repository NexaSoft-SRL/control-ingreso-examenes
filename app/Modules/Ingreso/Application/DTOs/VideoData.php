<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

/**
 * Un video de evidencia recien subido: su contenido y su tipo (video/mp4,
 * video/webm o video/quicktime).
 */
final readonly class VideoData
{
    public function __construct(
        public string $contenido,
        public string $tipo,
    ) {}
}
