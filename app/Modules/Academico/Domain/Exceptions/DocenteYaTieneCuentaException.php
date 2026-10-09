<?php

declare(strict_types=1);

namespace App\Modules\Academico\Domain\Exceptions;

use RuntimeException;

/**
 * Un docente tiene una sola cuenta: no se le activa otra.
 */
final class DocenteYaTieneCuentaException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El docente ya tiene una cuenta.');
    }
}
