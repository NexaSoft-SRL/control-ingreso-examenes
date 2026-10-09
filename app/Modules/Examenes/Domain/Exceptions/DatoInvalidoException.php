<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Exceptions;

use RuntimeException;

/**
 * Un dato del formulario no cumple una regla que depende de lo ya
 * registrado. Lleva el campo para que la respuesta lo senale.
 */
class DatoInvalidoException extends RuntimeException
{
    public function __construct(
        public readonly string $campo,
        string $mensaje,
    ) {
        parent::__construct($mensaje);
    }
}
