<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Exceptions;

use RuntimeException;

/**
 * Quien pide tiene el permiso, pero el examen o el aula no son suyos.
 */
final class FueraDeAlcanceException extends RuntimeException
{
    public static function examen(): self
    {
        return new self('Este examen no está a su cargo.');
    }

    public static function aula(): self
    {
        return new self('Esta aula no está a su cargo.');
    }
}
