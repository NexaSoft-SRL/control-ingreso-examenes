<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Application\DTOs;

use App\Modules\Estudiantes\Domain\Enums\MotivoBaja;

/**
 * El docente quita a un inscrito de su grupo, con motivo.
 */
final readonly class BajaDeInscritoData
{
    public function __construct(
        public int $usuarioId,
        public int $grupoId,
        public int $estudianteId,
        public MotivoBaja $motivo,
        public ?string $detalle = null,
    ) {}
}
