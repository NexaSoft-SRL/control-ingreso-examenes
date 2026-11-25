<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Application\DTOs;

/**
 * Un ingreso tal como está en el historial, anulado o no: lo que hace falta
 * para decidir si se puede anular.
 */
final readonly class IngresoAnulableData
{
    public function __construct(
        public int $id,
        public int $examenId,
        public int $aulaId,
        public bool $anulado,
    ) {}
}
