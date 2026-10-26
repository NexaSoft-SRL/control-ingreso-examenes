<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Exceptions;

use RuntimeException;

/**
 * El codigo que llego al correo no coincide, ya se uso o vencio.
 */
final class CodigoDeAccesoInvalidoException extends RuntimeException
{
    public function __construct(string $mensaje = 'Código no válido o vencido.')
    {
        parent::__construct($mensaje);
    }
}
