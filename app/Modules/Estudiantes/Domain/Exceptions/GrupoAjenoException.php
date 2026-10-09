<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Exceptions;

use RuntimeException;

/**
 * El docente solo ve y carga la lista de sus propios grupos.
 */
final class GrupoAjenoException extends RuntimeException
{
    public function __construct(string $mensaje = 'Este grupo no es tuyo.')
    {
        parent::__construct($mensaje);
    }
}
