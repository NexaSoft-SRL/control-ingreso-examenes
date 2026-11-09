<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Exceptions;

use RuntimeException;

/**
 * El aula no tiene ningun examen abierto a esta hora: la puerta no
 * verifica ni admite.
 */
final class AulaSinExamenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El aula no tiene un examen abierto a esta hora.');
    }
}
