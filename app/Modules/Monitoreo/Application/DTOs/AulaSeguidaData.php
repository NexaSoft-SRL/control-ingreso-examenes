<?php

declare(strict_types=1);

namespace App\Modules\Monitoreo\Application\DTOs;

/**
 * Un aula de un examen, como la nombra el seguimiento.
 */
final readonly class AulaSeguidaData
{
    public function __construct(
        public int $id,
        public string $nombre,
    ) {}
}
