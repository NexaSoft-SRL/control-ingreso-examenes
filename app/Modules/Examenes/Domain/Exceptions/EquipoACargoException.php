<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Domain\Exceptions;

use RuntimeException;

/**
 * Quien se quiere sacar del equipo esta a cargo de un aula.
 */
final class EquipoACargoException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Está a cargo de un aula: cámbielo antes.');
    }
}
