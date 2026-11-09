<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Exceptions;

use RuntimeException;

/**
 * El cambio de contrasena no procede. `campo` nombra el dato del
 * formulario que lo impide.
 */
final class ContrasenaNoAceptadaException extends RuntimeException
{
    private function __construct(
        public readonly string $campo,
        string $mensaje,
    ) {
        parent::__construct($mensaje);
    }

    public static function actualIncorrecta(): self
    {
        return new self(
            'contrasena_actual',
            'La contraseña actual no es correcta.',
        );
    }

    public static function repetida(): self
    {
        return new self(
            'contrasena_nueva',
            'La contraseña nueva debe ser distinta de la actual.',
        );
    }
}
