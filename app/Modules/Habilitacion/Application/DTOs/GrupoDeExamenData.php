<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Application\DTOs;

/**
 * Un grupo del examen, para el filtro de la lista.
 */
final readonly class GrupoDeExamenData
{
    public function __construct(
        public int $id,
        public string $codigo,
        /** El grupo es de quien consulta. */
        public bool $propio,
    ) {}
}
