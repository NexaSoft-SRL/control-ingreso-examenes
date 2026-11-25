<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Exceptions;

use RuntimeException;

/**
 * La persona no esta en el equipo del examen.
 */
final class NoEstaEnElEquipoException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Esa persona no está en el equipo del examen.');
    }
}
