<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Exceptions;

use RuntimeException;

/**
 * Un dato de la peticion no cuadra con el estado del examen o del aula.
 * Se responde como un error de validacion del campo.
 */
final class DatoNoValidoException extends RuntimeException
{
    public function __construct(
        public readonly string $campo,
        string $mensaje,
    ) {
        parent::__construct($mensaje);
    }
}
