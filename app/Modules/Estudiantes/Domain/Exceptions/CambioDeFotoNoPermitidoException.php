<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Exceptions;

use RuntimeException;

/**
 * El docente no puede habilitar el cambio de foto: ya esta habilitado o la
 * foto es de hace menos de un año. El mensaje dice hasta o desde cuando.
 */
final class CambioDeFotoNoPermitidoException extends RuntimeException
{
    public function __construct(
        string $mensaje,
        public readonly string $codigo,
    ) {
        parent::__construct($mensaje);
    }
}
