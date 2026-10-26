<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Exceptions;

use RuntimeException;

/**
 * La cuenta esta desactivada y la contrasena es correcta. Solo entonces se
 * avisa: con una contrasena equivocada la respuesta es la generica, para no
 * revelar el estado de cuentas ajenas.
 */
final class CuentaDesactivadaException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La cuenta está desactivada.');
    }
}
