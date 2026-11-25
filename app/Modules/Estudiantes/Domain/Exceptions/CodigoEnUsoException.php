<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Exceptions;

use RuntimeException;

/**
 * El codigo SIS indicado ya es de otro estudiante del padron.
 */
final class CodigoEnUsoException extends RuntimeException
{
    public function __construct(string $mensaje = 'Ese código SIS ya pertenece a otro estudiante.')
    {
        parent::__construct($mensaje);
    }
}
