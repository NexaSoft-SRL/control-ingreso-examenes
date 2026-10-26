<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Exceptions;

use RuntimeException;

/**
 * La contrasena nueva es la misma que la cuenta ya tiene.
 */
final class ContrasenaRepetidaException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La contraseña nueva debe ser distinta de la actual.');
    }
}
