<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Exceptions;

use RuntimeException;
use Throwable;

final class RespaldoFallidoException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $respaldoId = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

