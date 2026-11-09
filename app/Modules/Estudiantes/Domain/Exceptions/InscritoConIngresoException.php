<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Exceptions;

use RuntimeException;

/**
 * Quien ya ingreso a un examen del grupo es parte de su historia: no se lo
 * quita de la lista.
 */
final class InscritoConIngresoException extends RuntimeException
{
    public function __construct(string $mensaje = 'No se puede quitar: ya tiene un ingreso registrado en un examen de este grupo.')
    {
        parent::__construct($mensaje);
    }
}
