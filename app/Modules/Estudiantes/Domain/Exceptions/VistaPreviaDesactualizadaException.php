<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Exceptions;

use RuntimeException;

final class VistaPreviaDesactualizadaException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La información cambió desde la vista previa. Revísela antes de confirmar.');
    }
}
