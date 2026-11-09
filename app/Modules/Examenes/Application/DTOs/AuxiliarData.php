<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Una cuenta de auxiliar que el docente puede poner en un aula.
 */
final readonly class AuxiliarData
{
    public function __construct(
        public int $id,
        public string $nombre,
        public string $usuario,
        public bool $mio,
    ) {}
}
