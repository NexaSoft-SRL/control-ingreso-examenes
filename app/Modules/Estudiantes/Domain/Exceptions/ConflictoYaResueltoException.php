<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Exceptions;

use RuntimeException;

/**
 * Un conflicto se resuelve una sola vez.
 */
final class ConflictoYaResueltoException extends RuntimeException
{
    public function __construct(string $mensaje = 'El conflicto ya fue resuelto.')
    {
        parent::__construct($mensaje);
    }
}
