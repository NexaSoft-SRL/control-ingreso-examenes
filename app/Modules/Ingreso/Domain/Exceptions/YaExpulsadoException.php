<?php

declare(strict_types=1);

namespace App\Modules\Ingreso\Domain\Exceptions;

use RuntimeException;

/**
 * Ese ingreso ya tiene una expulsion: se asienta una sola vez.
 */
final class YaExpulsadoException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El estudiante ya fue expulsado de este examen.');
    }
}
