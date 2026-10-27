<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Un dato que no se acepta, con el campo del formulario al que pertenece.
 */
final class DatoNoValidoException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $campo,
        string $mensaje,
    ) {
        parent::__construct($mensaje);
    }
}
