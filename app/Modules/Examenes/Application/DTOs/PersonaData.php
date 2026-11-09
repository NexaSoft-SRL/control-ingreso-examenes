<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Una cuenta que puede atender un aula.
 */
final readonly class PersonaData
{
    public function __construct(
        public int $id,
        public string $nombre,
        public bool $esDocente,
        public bool $esAuxiliar,
    ) {}
}
