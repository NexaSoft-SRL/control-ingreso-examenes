<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Domain\Exceptions;

use RuntimeException;

/**
 * La lista del examen son los inscritos de sus grupos: no se registra la
 * condicion de nadie mas.
 */
final class EstudianteNoInscritoException extends RuntimeException
{
    public function __construct(public readonly int $cantidad)
    {
        parent::__construct(
            $cantidad === 1
                ? 'Un estudiante no está inscrito en ningún grupo del examen.'
                : sprintf('%d estudiantes no están inscritos en ningún grupo del examen.', $cantidad),
        );
    }
}
