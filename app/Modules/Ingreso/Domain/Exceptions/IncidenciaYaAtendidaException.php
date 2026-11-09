<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Exceptions;

use RuntimeException;

/**
 * Atender no se deshace ni se repite.
 */
final class IncidenciaYaAtendidaException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La incidencia ya estaba atendida.');
    }
}
