<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Exceptions;

use RuntimeException;

/**
 * La excepcion de cambio de aula no puede admitir al estudiante: no esta
 * inscrito, no esta habilitado, ya ingreso o fue expulsado.
 */
final class IngresoNoPermitidoException extends RuntimeException
{
    public function __construct(
        string $mensaje,
        public readonly string $codigo,
    ) {
        parent::__construct($mensaje);
    }
}
