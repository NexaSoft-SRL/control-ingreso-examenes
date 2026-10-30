<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Exceptions;

use RuntimeException;

final class RespaldoNoEncontradoException extends RuntimeException
{
    public function __construct(string $message = 'Respaldo no encontrado.')
    {
        parent::__construct($message);
    }
}
