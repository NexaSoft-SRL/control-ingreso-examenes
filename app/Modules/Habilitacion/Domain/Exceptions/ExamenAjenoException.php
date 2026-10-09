<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Domain\Exceptions;

use RuntimeException;

/**
 * La cuenta tiene el permiso de la pantalla, pero no es docente del examen.
 */
final class ExamenAjenoException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Este examen no es tuyo.');
    }
}
