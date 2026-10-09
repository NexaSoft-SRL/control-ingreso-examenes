<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Exceptions;

use RuntimeException;

/**
 * El usuario o el correo pedidos para la cuenta del docente ya son de
 * otra cuenta. `campo` es el campo del formulario que hay que corregir.
 */
final class DatoDeCuentaEnUsoException extends RuntimeException
{
    public function __construct(
        public readonly string $campo,
        string $mensaje,
    ) {
        parent::__construct($mensaje);
    }

    public static function usuario(): self
    {
        return new self('usuario', 'Ya existe una cuenta con ese usuario.');
    }

    public static function correo(): self
    {
        return new self('correo', 'Ya existe una cuenta con ese correo.');
    }
}
