<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Exceptions;

use RuntimeException;

/**
 * Solo se cargan inscritos en un periodo vigente.
 */
final class PeriodoCerradoException extends RuntimeException
{
    public function __construct(string $mensaje = 'El período de este grupo no está vigente.')
    {
        parent::__construct($mensaje);
    }
}
