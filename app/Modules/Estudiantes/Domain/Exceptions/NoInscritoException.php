<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Exceptions;

use RuntimeException;

/**
 * Solo se quita a quien sigue inscrito en el grupo.
 */
final class NoInscritoException extends RuntimeException
{
    public function __construct(string $mensaje = 'El estudiante no está inscrito en este grupo.')
    {
        parent::__construct($mensaje);
    }
}
