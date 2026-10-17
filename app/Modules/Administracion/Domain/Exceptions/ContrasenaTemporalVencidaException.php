<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Exceptions;

use RuntimeException;

final class ContrasenaTemporalVencidaException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La contraseña temporal venció.');
    }
}
