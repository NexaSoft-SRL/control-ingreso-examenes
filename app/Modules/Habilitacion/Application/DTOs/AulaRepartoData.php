<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\DTOs;

/**
 * Un aula del examen con los estudiantes que tiene asignados.
 */
final readonly class AulaRepartoData
{
    public function __construct(
        public int $aulaId,
        public string $nombre,
        public int $asignados,
    ) {}
}
