<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Exceptions;

use RuntimeException;

/**
 * El estudiante sube su foto una sola vez; despues solo la cambia la administracion.
 */
final class FotoYaSubidaException extends RuntimeException
{
    public function __construct(string $mensaje = 'La foto ya fue registrada.')
    {
        parent::__construct($mensaje);
    }
}
