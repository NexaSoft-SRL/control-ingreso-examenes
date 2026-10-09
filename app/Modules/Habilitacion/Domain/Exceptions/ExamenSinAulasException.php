<?php

declare(strict_types=1);

namespace App\Modules\Habilitacion\Domain\Exceptions;

use RuntimeException;

final class ExamenSinAulasException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El examen no tiene aulas: no hay dónde repartir.');
    }
}
