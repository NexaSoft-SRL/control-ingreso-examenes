<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Exceptions;

use RuntimeException;

/**
 * Ese ingreso ya tiene su anulación: se asienta una sola vez.
 */
final class IngresoYaAnuladoException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Este ingreso ya está anulado.');
    }
}
