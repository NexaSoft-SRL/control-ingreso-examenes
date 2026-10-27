<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Exceptions;

use RuntimeException;

/**
 * La foto propia solo se sube dentro del plazo que fija la administracion.
 */
final class FueraDePlazoDeFotoException extends RuntimeException
{
    public function __construct(string $mensaje = 'Fuera del plazo de fotos.')
    {
        parent::__construct($mensaje);
    }
}
