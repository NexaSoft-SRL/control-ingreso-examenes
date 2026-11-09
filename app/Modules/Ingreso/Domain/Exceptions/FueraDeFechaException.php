<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Exceptions;

use RuntimeException;

/**
 * Las incidencias se reportan solo el dia del examen.
 */
final class FueraDeFechaException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Las incidencias se registran solo el día del examen.');
    }
}
