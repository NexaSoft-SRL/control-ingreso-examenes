<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Application\DTOs;

/**
 * Un aula de un examen que una cuenta atiende, como encargada o de apoyo.
 */
final readonly class AulaAtendidaData
{
    public function __construct(
        public int $examenId,
        public int $aulaId,
        public bool $esEncargado,
    ) {}
}
