<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Exceptions;

use RuntimeException;

/**
 * La universidad no publica calendario para el periodo: no hay a qué volver.
 */
final class CalendarioDeLaUniversidadNoDisponibleException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La universidad no publica calendario para este período.');
    }
}
