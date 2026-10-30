<?php

declare(strict_types=1);

namespace App\Modules\Administracion\Domain\Exceptions;

use RuntimeException;

final class RespaldoIncompatibleException extends RuntimeException
{
    public function __construct(
        string $message = 'El respaldo corresponde a una versión anterior incompatible.',
        public readonly ?int $respaldoId = null,
    ) {
        parent::__construct($message);
    }
}
