<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Exceptions;

use RuntimeException;

/**
 * El carnet lo escribe el estudiante una sola vez; despues no lo cambia.
 */
final class CarnetYaRegistradoException extends RuntimeException
{
    public function __construct(string $mensaje = 'Su carnet ya está registrado y no se puede modificar.')
    {
        parent::__construct($mensaje);
    }
}
