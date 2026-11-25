<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Exceptions;

use RuntimeException;

/**
 * No se emite una contraseña temporal nueva: el docente no tiene cuenta, su
 * cuenta ya tiene contraseña propia o está bloqueada.
 */
final class ContrasenaTemporalNoAplicaException extends RuntimeException
{
    private function __construct(string $mensaje, public readonly string $codigo)
    {
        parent::__construct($mensaje);
    }

    public static function sinCuenta(): self
    {
        return new self('El docente no tiene cuenta.', 'SIN_CUENTA');
    }

    public static function contrasenaPropia(): self
    {
        return new self('La cuenta ya tiene contraseña propia.', 'CONTRASENA_PROPIA');
    }

    public static function bloqueada(): self
    {
        return new self('La cuenta está bloqueada.', 'BLOQUEADA');
    }
}
