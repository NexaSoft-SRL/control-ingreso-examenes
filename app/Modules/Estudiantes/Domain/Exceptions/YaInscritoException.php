<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Exceptions;

use RuntimeException;

/**
 * El estudiante que se quiere agregar ya esta en la lista del grupo.
 */
final class YaInscritoException extends RuntimeException
{
    public function __construct(string $mensaje = 'El estudiante ya está inscrito en este grupo.')
    {
        parent::__construct($mensaje);
    }
}
