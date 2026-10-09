<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Exceptions;

use RuntimeException;

/**
 * El grupo no tiene inscritos: no hay lista que descargar.
 */
final class GrupoSinListaException extends RuntimeException
{
    public function __construct(string $mensaje = 'El grupo no tiene lista cargada.')
    {
        parent::__construct($mensaje);
    }
}
