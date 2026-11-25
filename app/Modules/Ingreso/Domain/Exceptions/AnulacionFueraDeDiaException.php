<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Exceptions;

use RuntimeException;

/**
 * Un ingreso solo se anula el día del examen: después, el registro queda
 * como está para los reportes.
 */
final class AnulacionFueraDeDiaException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Un ingreso solo se anula el día del examen.');
    }
}
