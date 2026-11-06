<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Exceptions;

use RuntimeException;

final class ArchivoRespaldoNoEncontradoException extends RuntimeException
{
    public function __construct(string $message = 'El archivo de ese respaldo ya no está en disco.')
    {
        parent::__construct($message);
    }
}
