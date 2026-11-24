<?php

declare(strict_types=1);

namespace App\Modules\Estudiantes\Domain\Exceptions;

use RuntimeException;

/**
 * El archivo no es una imagen que el sistema pueda leer.
 */
final class ImagenNoValidaException extends RuntimeException
{
    public function __construct(string $mensaje = 'No se pudo leer la imagen · use JPG, PNG o WebP')
    {
        parent::__construct($mensaje);
    }
}
