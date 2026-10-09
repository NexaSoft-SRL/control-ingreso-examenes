<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Domain\Exceptions;

use RuntimeException;

/**
 * No se inhabilita a quien ya ingreso al examen: el ingreso es inmutable.
 */
final class EstudianteConIngresoException extends RuntimeException
{
    public function __construct(public readonly int $cantidad)
    {
        parent::__construct(
            $cantidad === 1
                ? 'Un estudiante de la selección ya ingresó al examen y no se puede inhabilitar.'
                : sprintf('%d estudiantes de la selección ya ingresaron al examen y no se pueden inhabilitar.', $cantidad),
        );
    }
}
